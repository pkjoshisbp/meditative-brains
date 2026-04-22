<?php

namespace App\WebSocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use App\Models\TtsSourceCategory;
use App\Models\TtsMotivationMessage;
use App\Models\TtsLanguage;
use App\Models\TtsAudiobook;
use App\Models\TtsAudiobookChapter;
use App\Models\TtsAudioProduct;
use App\Services\TtsAudioGeneratorService;
use App\Services\AudioSecurityService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * TtsWebSocketServer
 *
 * Replaces the Node.js HTTP API with a Ratchet bi-directional WebSocket.
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │  Authenticate: send {"action":"auth","token":"<sanctum-token>"}        │
 * │  All subsequent messages require a prior successful auth.              │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * Actions & responses are documented in NODE_TO_LARAVEL_MIGRATION.md
 */
class TtsWebSocketServer implements MessageComponentInterface
{
    /** @var \SplObjectStorage<ConnectionInterface, array> */
    protected \SplObjectStorage $clients;

    private TtsAudioGeneratorService $tts;
    private AudioSecurityService $security;

    public function __construct()
    {
        $this->clients  = new \SplObjectStorage();
        $this->tts      = app(TtsAudioGeneratorService::class);
        $this->security = app(AudioSecurityService::class);
        Log::info('[WS] TtsWebSocketServer started');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Ratchet lifecycle
    // ─────────────────────────────────────────────────────────────────────────

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn, ['authed' => false, 'user' => null]);
        Log::info("[WS] New connection #{$conn->resourceId}");
        $this->send($conn, ['event' => 'connected', 'message' => 'MentalFitness TTS WebSocket ready. Send auth action.']);
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
        Log::info("[WS] Connection #{$conn->resourceId} closed");
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        Log::error("[WS] Error on #{$conn->resourceId}: " . $e->getMessage());
        $conn->close();
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $payload = json_decode($msg, true);
        if (!is_array($payload) || empty($payload['action'])) {
            $this->sendError($from, 'Invalid message format. Expecting JSON with "action" key.');
            return;
        }

        $action = $payload['action'];

        // Auth is always allowed un-gated
        if ($action === 'auth') {
            $this->handleAuth($from, $payload);
            return;
        }

        // All other actions require authentication
        $meta = $this->clients[$from];
        if (!$meta['authed']) {
            Log::warning("[WS] #{$from->resourceId} sent '{$action}' without auth");
            $this->sendError($from, 'Not authenticated. Send {"action":"auth","token":"<sanctum-token>"} first.');
            return;
        }

        // Log every incoming action with a safe payload summary
        $userId = $meta['user']?->id ?? '?';
        $logPayload = array_diff_key($payload, array_flip(['token', 'ssml', 'plain_content', 'ssml_content']));
        if (isset($logPayload['text'])) $logPayload['text'] = substr($logPayload['text'], 0, 80) . (strlen($payload['text'] ?? '') > 80 ? '…' : '');
        Log::info("[WS] ← #{$from->resourceId} user#{$userId} action:{$action}", $logPayload);

        try {
            match ($action) {
                'ping'                         => $this->handlePing($from),

                // Languages
                'language.list'                => $this->handleLanguageList($from),

                // Categories
                'category.list'                => $this->handleCategoryList($from, $payload),
                'category.create'              => $this->handleCategoryCreate($from, $payload),
                'category.update'              => $this->handleCategoryUpdate($from, $payload),
                'category.delete'              => $this->handleCategoryDelete($from, $payload),

                // Messages
                'message.list'                 => $this->handleMessageList($from, $payload),
                'message.listByCategory'       => $this->handleMessageListByCategory($from, $payload),
                'message.create'               => $this->handleMessageCreate($from, $payload),
                'message.update'               => $this->handleMessageUpdate($from, $payload),
                'message.delete'               => $this->handleMessageDelete($from, $payload),

                // Audio generation
                'audio.generate'               => $this->handleAudioGenerate($from, $payload),
                'audio.generateCategory'       => $this->handleAudioGenerateCategory($from, $payload),
                'audio.attentionGuide'         => $this->handleAttentionGuideAudio($from, $payload),
                'audio.reminder'               => $this->handleReminderAudio($from, $payload),

                // Audiobooks
                'audiobook.list'               => $this->handleAudiobookList($from),
                'audiobook.get'                => $this->handleAudiobookGet($from, $payload),
                'audiobook.upsert'             => $this->handleAudiobookUpsert($from, $payload),
                'audiobook.delete'             => $this->handleAudiobookDelete($from, $payload),
                'audiobook.generateChapter'    => $this->handleAudiobookGenerateChapter($from, $payload),

                // Logs
                'logs.submit'                  => $this->handleLogsSubmit($from, $payload),

                // ── Mental Fitness TTS catalog (replaces REST /api/tts/…) ──
                'tts.language.list'            => $this->handleTtsLanguageList($from),
                'tts.product.listByLanguage'   => $this->handleTtsProductListByLanguage($from, $payload),
                'tts.product.detail'           => $this->handleTtsProductDetail($from, $payload),
                'tts.backgroundMusic.list'     => $this->handleTtsBackgroundMusicList($from),
                'tts.backgroundMusic.upload'   => $this->handleTtsBackgroundMusicUpload($from, $payload),

                default => $this->sendError($from, "Unknown action: {$action}"),
            };
        } catch (\Throwable $e) {
            Log::error("[WS] Action {$action} threw: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->sendError($from, $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Auth
    // ─────────────────────────────────────────────────────────────────────────

    private function handleAuth(ConnectionInterface $conn, array $payload): void
    {
        $token = $payload['token'] ?? null;
        if (!$token) {
            $this->sendError($conn, 'token required for auth');
            return;
        }

        // Validate Sanctum token
        $tokenRecord = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if (!$tokenRecord || !$tokenRecord->tokenable) {
            $this->sendError($conn, 'Invalid or expired token');
            return;
        }

        $user = $tokenRecord->tokenable;
        $this->clients[$conn] = ['authed' => true, 'user' => $user];

        $this->send($conn, [
            'event' => 'auth.success',
            'user'  => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);

        Log::info("[WS] Auth success for user #{$user->id} on conn #{$conn->resourceId}");
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Ping
    // ─────────────────────────────────────────────────────────────────────────

    private function handlePing(ConnectionInterface $conn): void
    {
        $this->send($conn, ['event' => 'pong', 'time' => now()->toISOString()]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Languages
    // ─────────────────────────────────────────────────────────────────────────

    private function handleLanguageList(ConnectionInterface $conn): void
    {
        $languages = TtsLanguage::where('is_active', true)->get();
        $this->send($conn, ['event' => 'language.list', 'data' => $languages]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Categories
    // ─────────────────────────────────────────────────────────────────────────

    private function handleCategoryList(ConnectionInterface $conn, array $payload): void
    {
        $user = $this->clients[$conn]['user'];
        $cats = TtsSourceCategory::where(function ($q) use ($user) {
            $q->whereNull('user_id')->orWhere('user_id', $user->id);
        })->get(['id', 'mongo_id', 'category', 'user_id', 'created_at', 'updated_at']);

        $this->send($conn, ['event' => 'category.list', 'data' => $cats]);
    }

    private function handleCategoryCreate(ConnectionInterface $conn, array $payload): void
    {
        $user     = $this->clients[$conn]['user'];
        $category = trim($payload['category'] ?? '');
        if (!$category) {
            $this->sendError($conn, 'category field required');
            return;
        }

        $cat = TtsSourceCategory::create([
            'category' => $category,
            'user_id'  => $user->id,
        ]);

        $this->send($conn, ['event' => 'category.created', 'data' => $cat]);
    }

    private function handleCategoryUpdate(ConnectionInterface $conn, array $payload): void
    {
        $cat = TtsSourceCategory::findOrFail($payload['id']);
        $cat->update(['category' => $payload['category'] ?? $cat->category]);
        $this->send($conn, ['event' => 'category.updated', 'data' => $cat]);
    }

    private function handleCategoryDelete(ConnectionInterface $conn, array $payload): void
    {
        TtsSourceCategory::findOrFail($payload['id'])->delete();
        $this->send($conn, ['event' => 'category.deleted', 'id' => $payload['id']]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Messages
    // ─────────────────────────────────────────────────────────────────────────

    private function handleMessageList(ConnectionInterface $conn, array $payload): void
    {
        $messages = TtsMotivationMessage::with('sourceCategory')->get();
        $data = $messages->map(fn ($msg) => $this->withRefreshedAudioUrls($msg->toArray()))->values();
        $this->send($conn, ['event' => 'message.list', 'data' => $data]);
    }

    private function handleMessageListByCategory(ConnectionInterface $conn, array $payload): void
    {
        $catId = $payload['categoryId'] ?? null;
        if (!$catId) { $this->sendError($conn, 'categoryId required'); return; }

        // Support mongo_id (legacy) or MySQL id
        $cat = is_numeric($catId)
            ? TtsSourceCategory::find($catId)
            : TtsSourceCategory::where('mongo_id', $catId)->first();

        if (!$cat) { $this->sendError($conn, 'Category not found'); return; }

        $messages = TtsMotivationMessage::where('source_category_id', $cat->id)->get();
        $data = $messages->map(fn ($msg) => $this->withRefreshedAudioUrls($msg->toArray()))->values();
        $this->send($conn, ['event' => 'message.listByCategory', 'categoryId' => $catId, 'data' => $data]);
    }

    private function handleMessageCreate(ConnectionInterface $conn, array $payload): void
    {
        $user      = $this->clients[$conn]['user'];
        $catId     = $payload['categoryId'] ?? null;
        $messages  = $payload['messages']   ?? [];
        $engine    = $payload['engine']     ?? 'azure';
        $language  = $this->tts->normaliseLanguageCode($payload['language'] ?? 'en-US');
        $speaker   = $payload['speaker']    ?? ($engine === 'vits' ? 'p225' : 'en-US-AriaNeural');

        // Resolve category
        $cat = is_numeric($catId)
            ? TtsSourceCategory::find($catId)
            : TtsSourceCategory::where('mongo_id', $catId)->first();

        if (!$cat) { $this->sendError($conn, 'Category not found'); return; }

        $record = TtsMotivationMessage::create([
            'source_category_id'  => $cat->id,
            'user_id'             => $user->id,
            'messages'            => is_array($messages) ? $messages : explode('$$', $messages),
            'ssml_messages'       => $payload['ssmlMessages'] ?? [],
            'ssml'                => $payload['ssml'] ?? [],
            'engine'              => $engine,
            'language'            => $language,
            'speaker'             => $speaker,
            'speaker_style'       => $payload['speakerStyle'] ?? null,
            'speaker_personality' => $payload['speakerPersonality'] ?? null,
            'prosody_rate'        => $payload['prosodyRate'] ?? 'medium',
            'prosody_pitch'       => $payload['prosodyPitch'] ?? 'medium',
            'prosody_volume'      => $payload['prosodyVolume'] ?? 'medium',
            'audio_paths'         => [],
            'audio_urls'          => [],
        ]);

        $this->send($conn, ['event' => 'message.created', 'data' => $record]);
    }

    private function handleMessageUpdate(ConnectionInterface $conn, array $payload): void
    {
        $record = TtsMotivationMessage::findOrFail($payload['id']);
        $record->update(array_intersect_key($payload, array_flip([
            'messages', 'ssml_messages', 'ssml', 'engine', 'language', 'speaker',
            'speaker_style', 'speaker_personality', 'prosody_rate', 'prosody_pitch', 'prosody_volume',
        ])));
        $this->send($conn, ['event' => 'message.updated', 'data' => $record->fresh()]);
    }

    private function handleMessageDelete(ConnectionInterface $conn, array $payload): void
    {
        TtsMotivationMessage::findOrFail($payload['id'])->delete();
        $this->send($conn, ['event' => 'message.deleted', 'id' => $payload['id']]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Audio generation
    // ─────────────────────────────────────────────────────────────────────────

    private function handleAudioGenerate(ConnectionInterface $conn, array $payload): void
    {
        $text    = $payload['text']     ?? '';
        $options = $this->extractAudioOptions($payload);

        if (!$text) { $this->sendError($conn, 'text required'); return; }

        $this->send($conn, ['event' => 'audio.generating', 'text' => substr($text, 0, 80)]);
        $result = $this->tts->generateForMessage($text, $options);
        $this->send($conn, ['event' => 'audio.generated', 'data' => $result]);
    }

    private function handleAudioGenerateCategory(ConnectionInterface $conn, array $payload): void
    {
        $catId = $payload['categoryId'] ?? null;
        if (!$catId) { $this->sendError($conn, 'categoryId required'); return; }

        $cat = is_numeric($catId)
            ? TtsSourceCategory::find($catId)
            : TtsSourceCategory::where('mongo_id', $catId)->first();
        if (!$cat) { $this->sendError($conn, 'Category not found'); return; }

        $messages = TtsMotivationMessage::where('source_category_id', $cat->id)->get();
        $options  = $this->extractAudioOptions($payload);

        $this->send($conn, ['event' => 'audio.generateCategory.start', 'total' => $messages->count()]);

        $filesGenerated = 0;
        foreach ($messages as $msgRecord) {
            $audioPaths = [];
            $audioUrls  = [];
            $errors     = [];

            foreach ($msgRecord->messages as $i => $text) {
                try {
                    $ssmlList = $msgRecord->ssml ?? [];
                    $opts     = array_merge($options, [
                        'ssml'               => $ssmlList[$i] ?? null,
                        'language'           => $msgRecord->language,
                        'speaker'            => $msgRecord->speaker,
                        'engine'             => $msgRecord->engine,
                        'prosodyRate'        => $msgRecord->prosody_rate,
                        'prosodyPitch'       => $msgRecord->prosody_pitch,
                        'prosodyVolume'      => $msgRecord->prosody_volume,
                        'speakerStyle'       => $msgRecord->speaker_style,
                        'speakerPersonality' => $msgRecord->speaker_personality,
                        'category'           => $cat->category,
                    ]);

                    $result       = $this->tts->generateForMessage($text, $opts);
                    $audioPaths[] = $result['relativePath'];
                    $audioUrls[]  = $result['audioUrl'];
                } catch (\Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }

            $msgRecord->update(['audio_paths' => $audioPaths, 'audio_urls' => $audioUrls]);

            // Push progress update to client
            $this->send($conn, [
                'event'   => 'audio.generateCategory.progress',
                'messageId' => $msgRecord->id,
                'done'    => ++$filesGenerated,
                'errors'  => $errors,
            ]);
        }

        $this->send($conn, ['event' => 'audio.generateCategory.complete', 'generated' => $filesGenerated]);
    }

    private function handleAttentionGuideAudio(ConnectionInterface $conn, array $payload): void
    {
        $text     = $payload['text']     ?? '';
        $language = $this->tts->normaliseLanguageCode($payload['language'] ?? 'en-US');
        $speaker  = $payload['speaker']  ?? 'en-US-AriaNeural';
        $speed    = $payload['speed']    ?? 'medium';
        $engine   = $payload['engine']   ?? 'azure';
        $category = $payload['category'] ?? 'attention-guide';

        if (!$text) { $this->sendError($conn, 'text required'); return; }

        $ssml = "<?xml version=\"1.0\"?>
<speak version=\"1.0\"
       xmlns=\"http://www.w3.org/2001/10/synthesis\"
       xmlns:mstts=\"http://www.w3.org/2001/mstts\"
       xml:lang=\"{$language}\">
  <voice name=\"{$speaker}\">
    <mstts:express-as role=\"assistant\">
      <lang xml:lang=\"{$language}\">
        <prosody rate=\"{$speed}\">{$text}</prosody>
      </lang>
    </mstts:express-as>
  </voice>
</speak>";

        $result = $this->tts->generateForMessage($text, [
            'language' => $language,
            'speaker'  => $speaker,
            'engine'   => $engine,
            'category' => $category,
            'ssml'     => $ssml,
        ]);

        // Encrypt the generated audio and produce a signed download URL for the Flutter client.
        // buildPaths() leaves audioUrl empty intentionally; callers must sign via AudioSecurityService.
        $signedUrl = $this->security->encryptRawAudioAndSign(
            $result['absolutePath'],
            $result['relativePath']
        );

        $this->send($conn, ['event' => 'audio.attentionGuide', 'data' => [
            'relativePath' => $result['relativePath'],
            'audioUrl'     => $signedUrl,
        ]]);
    }

    private function handleReminderAudio(ConnectionInterface $conn, array $payload): void
    {
        $text     = $payload['text']    ?? '';
        $language = $this->tts->normaliseLanguageCode($payload['language'] ?? 'en-US');
        $speaker  = $payload['speaker'] ?? 'en-US-AriaNeural';
        $engine   = $payload['engine']  ?? 'azure';

        if (!$text) { $this->sendError($conn, 'text required'); return; }

        $result = $this->tts->generateForMessage($text, [
            'language' => $language,
            'speaker'  => $speaker,
            'engine'   => $engine,
            'category' => 'reminders',
        ]);

        $this->send($conn, ['event' => 'audio.reminder', 'data' => $result]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Audiobooks
    // ─────────────────────────────────────────────────────────────────────────

    private function handleAudiobookList(ConnectionInterface $conn): void
    {
        $books = TtsAudiobook::with('chapters:id,audiobook_id,chapter_number,title,plain_content,status,audio_path,audio_url')->get();
        $data  = $books->map(fn($b) => $this->audiobookWithFreshUrls($b->toArray()))->values()->toArray();
        $this->send($conn, ['event' => 'audiobook.list', 'data' => $data]);
    }

    private function handleAudiobookGet(ConnectionInterface $conn, array $payload): void
    {
        $book = TtsAudiobook::with('chapters')->findOrFail($payload['id']);
        $this->send($conn, ['event' => 'audiobook.get', 'data' => $this->audiobookWithFreshUrls($book->toArray())]);
    }

    /** Re-sign chapter audio URLs so Flutter always gets unexpired links. */
    private function audiobookWithFreshUrls(array $book): array
    {
        if (empty($book['chapters'])) return $book;
        $book['chapters'] = array_map(function (array $ch) {
            $path = $ch['audio_path'] ?? null;
            if ($path && Storage::disk('local')->exists($path)) {
                try {
                    $ch['audio_url'] = URL::temporarySignedRoute(
                        'audio.signed-stream',
                        now()->addHours(24),
                        ['path' => base64_encode($path)]
                    );
                } catch (\Throwable $e) {
                    Log::warning('[WS] audiobook URL re-sign failed', ['path' => $path, 'err' => $e->getMessage()]);
                }
            }
            return $ch;
        }, $book['chapters']);
        return $book;
    }

    private function handleAudiobookUpsert(ConnectionInterface $conn, array $payload): void
    {
        $data = array_intersect_key($payload, array_flip([
            'book_title', 'book_author', 'language', 'speaker', 'engine',
            'speaker_style', 'expression_style', 'prosody_rate', 'prosody_pitch', 'prosody_volume',
        ]));

        $book = TtsAudiobook::updateOrCreate(['book_title' => $data['book_title']], $data);

        foreach (($payload['chapters'] ?? []) as $i => $ch) {
            TtsAudiobookChapter::updateOrCreate(
                ['audiobook_id' => $book->id, 'chapter_number' => $ch['chapter_number'] ?? ($i + 1)],
                [
                    'title'         => $ch['title']         ?? '',
                    'plain_content' => $ch['plain_content'] ?? $ch['plainContent'] ?? '',
                    'ssml_content'  => $ch['ssml_content']  ?? $ch['ssmlContent'] ?? '',
                    'audio_path'    => $ch['audio_path']    ?? $ch['audioPath'] ?? '',
                    'audio_url'     => $ch['audio_url']     ?? $ch['audioUrl'] ?? '',
                    'status'        => $ch['status']        ?? 'pending',
                ]
            );
        }

        $this->send($conn, ['event' => 'audiobook.upserted', 'data' => $book->load('chapters')]);
    }

    private function handleAudiobookDelete(ConnectionInterface $conn, array $payload): void
    {
        TtsAudiobook::findOrFail($payload['id'])->delete();
        $this->send($conn, ['event' => 'audiobook.deleted', 'id' => $payload['id']]);
    }

    private function handleAudiobookGenerateChapter(ConnectionInterface $conn, array $payload): void
    {
        $chapter = TtsAudiobookChapter::with('audiobook')->findOrFail($payload['chapterId']);
        $book    = $chapter->audiobook;

        $chapter->update(['status' => 'generating']);
        $this->send($conn, ['event' => 'audiobook.chapter.generating', 'chapterId' => $chapter->id]);

        try {
            $bookSlug = Str::slug($book->book_title);
            $result   = $this->tts->generateForMessage(
                !empty($chapter->ssml_content) ? $chapter->ssml_content : $chapter->plain_content,
                [
                    'language'     => $book->language,
                    'speaker'      => $book->speaker,
                    'engine'       => $book->engine,
                    'speakerStyle' => $book->speaker_style,
                    'ssml'         => $chapter->ssml_content ?: null,
                    'prosodyRate'  => $book->prosody_rate,
                    'prosodyPitch' => $book->prosody_pitch,
                    'prosodyVolume'=> $book->prosody_volume,
                    'storageType'  => 'audiobook',
                    'category'     => $bookSlug,
                ]
            );

            // Encrypt the plain AAC and generate a signed streaming URL
            $signedUrl   = $this->security->encryptRawAudioAndSign(
                $result['absolutePath'],
                $result['relativePath']
            );
            // Derive encrypted path for re-signing on subsequent requests
            $encRelative = 'audio/encrypted/tts-messages/' .
                preg_replace('/\.[^.]+$/', '', ltrim($result['relativePath'], '/')) . '.enc';

            $chapter->update([
                'audio_path' => $encRelative,
                'audio_url'  => $signedUrl,
                'status'     => 'done',
            ]);

            $this->send($conn, ['event' => 'audiobook.chapter.done', 'data' => $chapter->fresh()]);
        } catch (\Throwable $e) {
            $chapter->update(['status' => 'error']);
            $this->sendError($conn, 'Chapter generation failed: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Logs
    // ─────────────────────────────────────────────────────────────────────────

    private function handleLogsSubmit(ConnectionInterface $conn, array $payload): void
    {
        $logContent = $payload['logs'] ?? $payload['content'] ?? '';
        $device     = $payload['deviceInfo'] ?? 'unknown';
        $dir        = base_path('flutter_logs');
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $filename = 'app_logs_' . now()->format('Y-m-d\TH-i-s') . '.txt';
        $file     = $dir . '/' . $filename;
        $body     = "DEVICE: {$device}\n" . $logContent;
        file_put_contents($file, $body);

        // Email the log to the configured debug address
        $to = config('app.debug_log_email', env('DEBUG_LOG_EMAIL', ''));
        if ($to) {
            try {
                \Illuminate\Support\Facades\Mail::send(
                    [],
                    [],
                    function ($msg) use ($to, $filename, $file, $device) {
                        $msg->to($to)
                            ->subject("Flutter Debug Log: {$filename}")
                            ->text("Device: {$device}\n\nLog file attached.")
                            ->attach($file, ['as' => $filename, 'mime' => 'text/plain']);
                    }
                );
                Log::info("[WS] logs.submit: email sent to {$to}", ['file' => $filename]);
            } catch (\Throwable $e) {
                Log::warning("[WS] logs.submit: email failed", ['error' => $e->getMessage()]);
            }
        }

        $this->send($conn, ['event' => 'logs.submitted', 'file' => $filename]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Utilities
    // ─────────────────────────────────────────────────────────────────────────

    private function extractAudioOptions(array $payload): array
    {
        return array_filter([
            'engine'             => $payload['engine']          ?? null,
            'language'           => $payload['language']        ?? null,
            'speaker'            => $payload['speaker']         ?? null,
            'speakerStyle'       => $payload['speakerStyle']    ?? null,
            'speakerPersonality' => $payload['speakerPersonality'] ?? null,
            'ssml'               => $payload['ssml']            ?? null,
            'prosodyRate'        => $payload['prosodyRate']     ?? null,
            'prosodyPitch'       => $payload['prosodyPitch']    ?? null,
            'prosodyVolume'      => $payload['prosodyVolume']   ?? null,
            'category'           => $payload['category']        ?? null,
            'speed'              => $payload['speed']           ?? null,
            'noise'              => $payload['noise']           ?? null,
            'noiseW'             => $payload['noiseW']          ?? null,
        ], fn ($v) => $v !== null);
    }

    private function send(ConnectionInterface $conn, array $data): void
    {
        $event = $data['event'] ?? 'unknown';
        // Build a compact log summary — omit large data arrays, truncate audio_urls
        $summary = [];
        if (isset($data['data']) && is_array($data['data'])) {
            $summary['data_keys'] = array_keys($data['data']);
            $summary['data_count'] = count($data['data']);
        }
        foreach (['id', 'total', 'done', 'generated', 'messageId', 'chapterId', 'categoryId', 'message'] as $k) {
            if (isset($data[$k])) $summary[$k] = $data[$k];
        }
        Log::info("[WS] → #{$conn->resourceId} event:{$event}", $summary);
        $conn->send(json_encode($data));
    }

    private function sendError(ConnectionInterface $conn, string $message): void
    {
        Log::warning("[WS] → #{$conn->resourceId} event:error", ['message' => $message]);
        $conn->send(json_encode(['event' => 'error', 'message' => $message]));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Mental Fitness TTS catalog  (replaces REST /api/tts/… endpoints)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * action: tts.language.list
     * Returns languages in which the authenticated user has at least one accessible product.
     */
    private function handleTtsLanguageList(ConnectionInterface $conn): void
    {
        // Return ALL languages that have at least one active product.
        // Per-product access control is enforced in tts.product.listByLanguage
        // and tts.product.detail — so it is safe to expose the language list
        // to every authenticated user (they will see locked products but cannot
        // stream audio they have not purchased).
        $languages = TtsAudioProduct::active()
            ->whereNotNull('language')
            ->pluck('language')
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $this->send($conn, [
            'event'     => 'tts.language.list',
            'success'   => true,
            'languages' => $languages,
            'total'     => count($languages),
        ]);
    }

    /**
     * action: tts.product.listByLanguage
     * Payload: { "language": "en-IN", "preview": false }
     */
    private function handleTtsProductListByLanguage(ConnectionInterface $conn, array $payload): void
    {
        $user     = $this->clients[$conn]['user'];
        $language = trim($payload['language'] ?? '');
        $preview  = !empty($payload['preview']);

        if ($language === '') {
            $this->sendError($conn, 'tts.product.listByLanguage requires language');
            return;
        }

        $products = TtsAudioProduct::active()
            ->where('language', $language)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $items = [];
        foreach ($products as $product) {
            $catAccess  = $user->hasTtsCategoryAccessExtended($product->category);
            $hasProduct = $user->hasTtsProductAccess($product->id);
            if (!$preview && !$hasProduct && !$catAccess['has_access']) {
                continue;
            }
            $items[] = [
                'id'               => $product->id,
                'name'             => $product->name,
                'display_name'     => $product->display_name,
                'category'         => $product->category,
                'language'         => $product->language,
                'price'            => $product->price,
                'formatted_price'  => $product->formatted_price,
                'has_access'       => $hasProduct || $catAccess['has_access'],
                'access_type'      => $hasProduct ? 'product_purchase' : ($catAccess['has_access'] ? $catAccess['access_type'] : 'none'),
                'preview_available' => $product->hasPreviewSamples(),
                'sample_messages'  => $product->hasPreviewSamples() ? $product->sample_messages : [],
            ];
        }

        $this->send($conn, [
            'event'        => 'tts.product.listByLanguage',
            'success'      => true,
            'language'     => $language,
            'products'     => $items,
            'count'        => count($items),
            'preview_mode' => $preview,
        ]);
    }

    /**
     * action: tts.product.detail
     * Payload: { "productId": 123 }
     * Delegates to TtsBackendController to reuse encryption / signed-URL logic.
     */
    private function handleTtsProductDetail(ConnectionInterface $conn, array $payload): void
    {
        $user      = $this->clients[$conn]['user'];
        $productId = (int)($payload['productId'] ?? 0);

        if (!$productId) {
            $this->sendError($conn, 'tts.product.detail requires productId');
            return;
        }

        $product = TtsAudioProduct::active()->find($productId);
        if (!$product) {
            $this->sendError($conn, "Product {$productId} not found");
            return;
        }

        $catAccess  = $user->hasTtsCategoryAccessExtended($product->category);
        $hasProduct = $user->hasTtsProductAccess($product->id);
        if (!$hasProduct && !$catAccess['has_access']) {
            $this->send($conn, [
                'event'                  => 'error',
                'code'                   => 'access_denied',
                'product_id'             => $product->id,
                'category'               => $product->category,
                'available_for_purchase' => true,
                'message'                => 'No entitlement for this product or category',
            ]);
            return;
        }

        // Delegate to controller to reuse audio encryption / signed-URL logic
        Auth::setUser($user);
        try {
            $controller = app(\App\Http\Controllers\Api\TtsBackendController::class);
            $response   = $controller->getTtsProductDetail(new \Illuminate\Http\Request(), $productId);
            $data       = json_decode($response->getContent(), true) ?? [];
            $data['event'] = 'tts.product.detail';
            // Debug: log keys present on the first track so the Flutter log
            // can confirm whether message_text is being delivered.
            $firstTrack = $data['tracks'][0] ?? null;
            if ($firstTrack) {
                Log::info('[WS][tts.product.detail] first track keys: ' . implode(', ', array_keys($firstTrack))
                    . ' | title=' . substr($firstTrack['title'] ?? '', 0, 60)
                    . ' | message_text=' . substr($firstTrack['message_text'] ?? 'MISSING', 0, 80));
            }
            $this->send($conn, $data);
        } catch (\Throwable $e) {
            Log::error("[WS] tts.product.detail error", ['id' => $productId, 'err' => $e->getMessage()]);
            $this->sendError($conn, 'Failed to load product detail: ' . $e->getMessage());
        }
    }

    /**
     * action: tts.backgroundMusic.list
     * Delegates to TtsBackendController to reuse secure-URL signing logic.
     */
    private function handleTtsBackgroundMusicList(ConnectionInterface $conn): void
    {
        $user = $this->clients[$conn]['user'];

        Auth::setUser($user);
        try {
            $controller = app(\App\Http\Controllers\Api\TtsBackendController::class);
            $response   = $controller->listBackgroundMusic(new \Illuminate\Http\Request());
            $data       = json_decode($response->getContent(), true) ?? [];
            $data['event'] = 'tts.backgroundMusic.list';
            $this->send($conn, $data);
        } catch (\Throwable $e) {
            Log::error("[WS] tts.backgroundMusic.list error", ['err' => $e->getMessage()]);
            $this->sendError($conn, 'Failed to load background music: ' . $e->getMessage());
        }
    }

    /**
     * action: tts.backgroundMusic.upload
     * Admin-only. Receives a base64-encoded audio file and stores it in:
     *   storage/app/bg-music/original/<slug>.<ext>   (private, signed-stream source)
     *   public/bg-music/<slug>.<ext>                 (publicly served, home screen)
     *   storage/app/bg-music/encrypted/<slug>.enc    (AES-256-CBC encrypted for Flutter)
     */
    private function handleTtsBackgroundMusicUpload(ConnectionInterface $conn, array $payload): void
    {
        $user = $this->clients[$conn]['user'];
        if (!$user || $user->role !== 'admin') {
            $this->sendError($conn, 'Admin access required for background music upload.');
            return;
        }

        // ── Validate inputs ──────────────────────────────────────────────────
        $rawName = trim($payload['name'] ?? '');
        $ext     = strtolower(trim($payload['extension'] ?? 'mp3'));
        $data    = $payload['data'] ?? '';   // base64-encoded binary

        if ($rawName === '') {
            $this->sendError($conn, 'Field "name" is required.');
            return;
        }
        $allowedExtensions = ['mp3', 'aac', 'm4a', 'wav', 'ogg'];
        if (!in_array($ext, $allowedExtensions, true)) {
            $this->sendError($conn, 'Extension must be one of: ' . implode(', ', $allowedExtensions));
            return;
        }
        if (empty($data)) {
            $this->sendError($conn, 'Field "data" (base64 audio) is required.');
            return;
        }

        // Decode binary
        $binary = base64_decode($data, true);
        if ($binary === false || strlen($binary) < 128) {
            $this->sendError($conn, 'Invalid base64 audio data.');
            return;
        }
        $maxBytes = 30 * 1024 * 1024; // 30 MB
        if (strlen($binary) > $maxBytes) {
            $this->sendError($conn, 'File too large. Maximum 30 MB.');
            return;
        }

        // Build a safe slug filename
        $slug     = \Illuminate\Support\Str::slug($rawName);
        $filename = $slug . '.' . $ext;

        // Prevent path traversal
        if ($filename !== basename($filename) || str_contains($filename, '..')) {
            $this->sendError($conn, 'Invalid filename.');
            return;
        }

        // ── Write original file ──────────────────────────────────────────────
        $originalRelative = 'bg-music/original/' . $filename;
        \Illuminate\Support\Facades\Storage::disk('local')->put($originalRelative, $binary);

        // ── Write public copy (for home screen, not encrypted) ───────────────
        $publicDir  = public_path('bg-music');
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0755, true);
        }
        file_put_contents($publicDir . '/' . $filename, $binary);

        // ── Encrypt for secure Flutter streaming ─────────────────────────────
        $encryptedPath = null;
        try {
            $audioSecurityService = app(\App\Services\AudioSecurityService::class);
            $encryptedPath = $audioSecurityService->encryptBgMusicFile($filename);
        } catch (\Throwable $e) {
            Log::error('[WS] BG music encryption failed', ['file' => $filename, 'err' => $e->getMessage()]);
            // Non-fatal: original was saved; encryption can be retried via list action
        }

        Log::info('[WS] BG music uploaded', [
            'filename' => $filename,
            'size'     => strlen($binary),
            'encrypted' => $encryptedPath ?? 'failed',
        ]);

        $this->send($conn, [
            'event'          => 'tts.backgroundMusic.uploaded',
            'success'        => true,
            'filename'       => $filename,
            'slug'           => $slug,
            'size_bytes'     => strlen($binary),
            'encrypted_path' => $encryptedPath,
            'public_url'     => config('app.url') . '/bg-music/' . $filename,
        ]);
    }

    /**
     * Re-sign expired audio_urls in a message array so Flutter can download them.
     * Signed URLs expire after ~60 min; this regenerates fresh 24-hour URLs.
     */
    private function withRefreshedAudioUrls(array $message): array
    {
        $audioUrls = $message['audio_urls'] ?? [];
        if (empty($audioUrls) || !is_array($audioUrls)) {
            return $message;
        }

        $refreshed = [];
        foreach ($audioUrls as $url) {
            if (!is_string($url) || $url === '' || !str_contains($url, '/audio/signed-stream')) {
                $refreshed[] = $url;
                continue;
            }

            $queryStr = parse_url($url, PHP_URL_QUERY);
            if (!$queryStr) { $refreshed[] = $url; continue; }

            parse_str($queryStr, $params);
            $encodedPath = $params['path'] ?? null;
            if (!is_string($encodedPath) || $encodedPath === '') { $refreshed[] = $url; continue; }

            $encryptedPath = base64_decode($encodedPath, true);
            if (!is_string($encryptedPath) || !Storage::disk('local')->exists($encryptedPath)) {
                // Encrypted file is gone — signal client to generate TTS on the fly
                $refreshed[] = null;
                continue;
            }

            try {
                $previewParam  = $params['preview'] ?? null;
                $previewLength = is_numeric($previewParam) ? (int) $previewParam : null;
                $parameters    = ['path' => base64_encode($encryptedPath)];
                if ($previewLength !== null) {
                    $parameters['preview'] = $previewLength;
                }
                $refreshed[] = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                    'audio.signed-stream',
                    now()->addHours(24),
                    $parameters
                );
            } catch (\Throwable $e) {
                Log::warning('[WS] Failed refreshing message audio URL', [
                    'error' => $e->getMessage(),
                    'path'  => $encryptedPath ?? 'unknown',
                ]);
                $refreshed[] = $url;
            }
        }

        $message['audio_urls'] = $refreshed;
        return $message;
    }
}
