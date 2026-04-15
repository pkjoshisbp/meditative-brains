<?php

namespace App\Console\Commands;

use App\Models\TtsAudioProduct;
use App\Services\AudioSecurityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Migrates audio from the Node.js tts-backend audio-cache into Laravel's
 * encrypted storage so the app has zero dependency on the Node backend.
 *
 * Usage:
 *   php artisan tts:migrate-audio             # migrate all products
 *   php artisan tts:migrate-audio --product=71 # single product
 *   php artisan tts:migrate-audio --dry-run    # preview only, no writes
 *
 * After this command completes successfully you can:
 *   - Remove/disable the fetchBestNodeAudio fallback in TtsBackendController
 *   - Stop the Node tts-backend process
 */
class MigrateAudioToLaravel extends Command
{
    protected $signature = 'tts:migrate-audio
                            {--product=          : Migrate a single product by ID}
                            {--dry-run           : Scan and report without writing anything}
                            {--force             : Re-process products that already have audio_urls}
                            {--generate-missing  : Generate missing audio tracks via Azure TTS}';

    protected $description = 'Migrate all TTS audio from Node audio-cache into Laravel encrypted storage';

    /** Base directory of the Node audio-cache */
    private string $audioCacheBase;

    private AudioSecurityService $security;

    public function handle(): int
    {
        $this->audioCacheBase = base_path('tts-backend/audio-cache');
        $this->security = app(AudioSecurityService::class);

        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');

        $query = TtsAudioProduct::query();

        if ($pid = $this->option('product')) {
            $ids = array_filter(array_map('intval', explode(',', $pid)));
            if (count($ids) === 1) {
                $query->where('id', $ids[0]);
            } else {
                $query->whereIn('id', $ids);
            }
        }

        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('audio_urls')
                  ->orWhere('audio_urls', '[]')
                  ->orWhere('audio_urls', 'null')
                  ->orWhere('audio_urls', '')
                  ->orWhere('audio_urls', '""');
            });
        }

        $products = $query->orderBy('id')->get();

        $this->info("Found {$products->count()} product(s) to process." . ($dryRun ? ' (DRY RUN)' : ''));

        $migrated  = 0;
        $skipped   = 0;
        $missing   = [];

        foreach ($products as $product) {
            $result = $this->migrateProduct($product, $dryRun);

            if ($result === null) {
                $missing[] = $product->id . ' - ' . $product->name;
                $skipped++;
            } elseif ($result === 0) {
                $missing[] = $product->id . ' - ' . $product->name . ' (folder found but 0 MP3s)';
                $skipped++;
            } else {
                $migrated++;
            }
        }

        $this->newLine();
        $this->info("✅ Migrated:  {$migrated} products");
        $this->warn("⚠  Skipped:   {$skipped} products (no local audio)");

        if (!empty($missing)) {
            $this->newLine();
            $this->warn('Products with no local audio (will need Azure TTS generation):');
            foreach ($missing as $m) {
                $this->line("  - {$m}");
            }
        }

        $this->newLine();
        if (!$dryRun && $migrated > 0) {
            $this->info('Migration complete. You can now remove the Node fallback in TtsBackendController::getTtsProductDetail().');
        }

        // Phase 2: Generate missing audio via Azure TTS if requested
        if (!$dryRun && $this->option('generate-missing') && !empty($missing)) {
            $this->newLine();
            $this->info('--- Phase 2: Generating missing audio via Azure TTS ---');
            $generated = 0;
            $stillMissing = [];
            foreach ($missing as $m) {
                $id = (int) explode(' ', $m)[0];
                $product = TtsAudioProduct::find($id);
                if (!$product) continue;
                $count = $this->generateMissingAudio($product);
                if ($count > 0) {
                    $generated++;
                } else {
                    $stillMissing[] = $m;
                }
            }
            $this->info("✅ Generated audio for: {$generated} products");
            if (!empty($stillMissing)) {
                $this->warn('Still missing (check category mapping):');
                foreach ($stillMissing as $s) $this->line("  - {$s}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Migrate a single product.
     * Returns the number of tracks stored, or null if no matching folder was found.
     */
    private function migrateProduct(TtsAudioProduct $product, bool $dryRun): ?int
    {
        $lang         = $this->normalizeLang($product->backend_language ?: $product->language ?: 'en-IN');
        $categorySlug = $this->deriveCategorySlug($product);

        $folder = $this->audioCacheBase . '/' . $lang . '/' . $categorySlug;

        if (!is_dir($folder)) {
            // Try fallback langs: en-IN → en-US, en → en-IN, en → en-US
            $fallbacks = $this->langFallbacks($lang);
            $found = null;
            foreach ($fallbacks as $fl) {
                $candidate = $this->audioCacheBase . '/' . $fl . '/' . $categorySlug;
                if (is_dir($candidate)) {
                    $found = $candidate;
                    $lang  = $fl;
                    break;
                }
            }
            if (!$found) {
                $this->line("  [SKIP] #{$product->id} {$product->name} — no folder: {$lang}/{$categorySlug}");
                return null;
            }
            $folder = $found;
        }

        // Collect all audio files (mp3 + aac) from speaker subfolders and direct layout
        $allFiles = array_unique(array_merge(
            glob($folder . '/*/*.mp3') ?: [],
            glob($folder . '/*/*.aac') ?: [],
            glob($folder . '/*.mp3') ?: [],
            glob($folder . '/*.aac') ?: []
        ));

        if (empty($allFiles)) {
            $this->line("  [EMPTY] #{$product->id} {$product->name} — folder exists but 0 audio files: {$folder}");
            return 0;
        }

        // Group by speaker subfolder
        $filesBySpeaker = [];
        foreach ($allFiles as $f) {
            $speakerDir = basename(dirname($f));
            $filesBySpeaker[$speakerDir][] = $f;
        }

        // Select correct speaker: prefer DB backend_speaker, then defaultSpeaker(), then most files
        if (count($filesBySpeaker) > 1 || !empty($product->backend_speaker)) {
            $explicitSpeaker = $product->backend_speaker ?: null;
            $preferredSpeaker = $explicitSpeaker ?: $this->defaultSpeaker($product);
            if (isset($filesBySpeaker[$preferredSpeaker])) {
                $speakerFiles = $filesBySpeaker[$preferredSpeaker];
            } elseif ($explicitSpeaker) {
                // backend_speaker explicitly set but that speaker folder is empty/missing → treat as no cache
                $this->line("  [EMPTY] #{$product->id} {$product->name} — no files for explicit speaker: {$explicitSpeaker}");
                return 0;
            } else {
                uasort($filesBySpeaker, fn($a, $b) => count($b) - count($a));
                $speakerFiles = reset($filesBySpeaker);
            }
        } else {
            $speakerFiles = $allFiles;
        }

        // Deduplicate by filename stem (prefer .mp3 over .aac when same content exists)
        $filesBySlug = [];
        foreach ($speakerFiles as $f) {
            $stem = pathinfo($f, PATHINFO_FILENAME);
            $ext  = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (!isset($filesBySlug[$stem]) || $ext === 'mp3') {
                $filesBySlug[$stem] = $f;
            }
        }
        $mp3Files = array_values($filesBySlug);

        sort($mp3Files); // deterministic order

        $this->line("  [OK] #{$product->id} {$product->name} — {$lang}/{$categorySlug} — " . count($mp3Files) . " tracks");

        if ($dryRun) {
            foreach ($mp3Files as $i => $mp3) {
                $this->line("       " . ($i + 1) . ". " . basename(dirname($mp3)) . '/' . basename($mp3));
            }
            return count($mp3Files);
        }

        $signedUrls = [];

        foreach ($mp3Files as $index => $mp3AbsPath) {
            $signedUrl = $this->processTrack($product, $index, $mp3AbsPath, $lang, $categorySlug);
            if ($signedUrl) {
                $signedUrls[] = $signedUrl;
            }
        }

        if (!empty($signedUrls)) {
            $product->update([
                'audio_urls'       => array_values($signedUrls),
                'preview_audio_url' => $signedUrls[0],
                'backend_language' => $product->backend_language ?: $lang,
            ]);
        }

        return count($signedUrls);
    }

    /**
     * Copy an MP3 to Laravel original storage, encrypt it, and return a signed URL.
     */
    private function processTrack(TtsAudioProduct $product, int $index, string $mp3AbsPath, string $lang, string $categorySlug, bool $force = false): ?string
    {
        try {
            $locale      = str_replace('-', '_', $lang); // en-IN → en_IN
            $productSlug = Str::slug($product->slug ?: $product->name ?: ('product-' . $product->id));
            $speaker     = basename(dirname($mp3AbsPath)); // folder above the file = speaker name
            // If no speaker sub-folder the dirname IS the category folder, use 'default-speaker'
            if ($speaker === $categorySlug) {
                $speaker = 'default-speaker';
            }

            $fileName = pathinfo($mp3AbsPath, PATHINFO_BASENAME);

            $originalRelative = sprintf(
                'tts-products/%s/%s/%s/%s/%s',
                $locale,
                $categorySlug,
                $productSlug,
                $speaker,
                $fileName
            );
            $originalStoragePath = 'audio/original/' . $originalRelative;

            // Copy to Laravel original storage — always overwrite when $force (fresh generation)
            if ($force || !Storage::disk('local')->exists($originalStoragePath)) {
                $content = file_get_contents($mp3AbsPath);
                if ($content === false) {
                    $this->warn("    Could not read: {$mp3AbsPath}");
                    return null;
                }
                Storage::disk('local')->put($originalStoragePath, $content);
            }

            // Encrypt
            // Encrypt — delete stale .enc first so encryptOriginalFile doesn't skip re-encryption
            if ($force) {
                $encRelative = str_replace('audio/original/', '', $originalStoragePath);
                $encRelative = preg_replace('/\.[^.]+$/', '.enc', $encRelative);
                Storage::disk('local')->delete('audio/encrypted/' . $encRelative);
            }
            $encryptedPath = $this->security->encryptOriginalFile($originalRelative, $product->id);

            // Generate signed URL (24-hour expiry; auto-refreshed by ensureEncryptedProductTracks on every request)
            return $this->security->generateSignedUrl($encryptedPath, null, 60 * 24);
        } catch (\Throwable $e) {
            $this->warn("    Track {$index} error: " . $e->getMessage());
            return null;
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    /**
     * Replicates the Node slugifyText(text, maxLength=40) function:
     *   lowercase → replace & with 'and' → spaces→'-' → strip non-alphanumeric → trim '-' → truncate 40
     */
    private function nodeSlug(string $text, int $maxLength = 40): string
    {
        $s = strtolower(trim($text));
        $s = str_replace('&', 'and', $s);
        $s = preg_replace('/\s+/', '-', $s);
        $s = preg_replace('/[^a-z0-9-]/', '', $s);
        $s = trim($s, '-');
        return substr($s, 0, $maxLength);
    }

    /**
     * Derives the audio-cache category folder slug for a product.
     *
     * For sub-categories (e.g. "Brain & Nervous System Balance" in "Quit Smoking"):
     *   tries "{parent-slug}---{name-slug}" (Node compound) first, then name alone.
     *
     * For products 56-79 (cat="motivation"): extract base name from product.name.
     *
     * For products 80+ (cat = actual category): use cat field or compound.
     */
    private function deriveCategorySlug(TtsAudioProduct $product): string
    {
        $cat  = trim($product->category ?? '');
        $name = trim($product->name ?? '');

        $genericCategories = ['motivation', 'default', '', 'uncategorized'];

        if (in_array(strtolower($cat), $genericCategories, true)) {
            // Strip language suffix " (English India - En US Ava  )"
            $baseName = preg_replace('/\s*\([^)]+\)\s*$/', '', $name);
            return $baseName !== '' ? $this->nodeSlug(trim($baseName)) : 'default';
        }

        $catSlug  = $this->nodeSlug($cat);
        $nameSlug = $this->nodeSlug($name);

        if ($catSlug !== $nameSlug) {
            // Try Node-style compound slug: slugify("Quit Smoking - Brain & Nervous System Balance")
            $compound = $this->nodeSlug($cat . ' - ' . $name);
            $lang = $this->normalizeLang($product->backend_language ?: $product->language ?: 'en-IN');
            foreach (array_unique(array_merge([$lang], $this->langFallbacks($lang))) as $fl) {
                if (is_dir($this->audioCacheBase . '/' . $fl . '/' . $compound)) {
                    return $compound;
                }
            }
            // Also try just the name slug (e.g., "hormonal-balance-and-calm")
            foreach (array_unique(array_merge([$lang], $this->langFallbacks($lang))) as $fl) {
                if (is_dir($this->audioCacheBase . '/' . $fl . '/' . $nameSlug)) {
                    return $nameSlug;
                }
            }
        }

        return $catSlug;
    }

    private function normalizeLang(string $lang): string
    {
        $lang = trim($lang);
        // Normalise: accept "en_IN" → "en-IN"
        $lang = str_replace('_', '-', $lang);
        // Lowercase base, uppercase region: en-in → en-IN
        $parts = explode('-', $lang, 2);
        if (count($parts) === 2) {
            return strtolower($parts[0]) . '-' . strtoupper($parts[1]);
        }
        return strtolower($lang);
    }

    private function langFallbacks(string $lang): array
    {
        $base = strtolower(explode('-', $lang)[0]); // 'en', 'hi', etc.
        $fallbacks = [];
        if ($lang !== 'en-IN') $fallbacks[] = 'en-IN';
        if ($lang !== 'en-US') $fallbacks[] = 'en-US';
        if ($base !== 'en')    $fallbacks[] = 'en-IN';
        return array_unique($fallbacks);
    }

    // ─── Azure TTS Generation ────────────────────────────────────────────────

    /**
     * Generate audio for a product from predefined messages via Azure TTS.
     * Returns number of tracks generated.
     */
    private function generateMissingAudio(TtsAudioProduct $product): int
    {
        $messages = $this->getMessagesForProduct($product);
        if (empty($messages)) {
            $this->warn("  [NO MESSAGES] #{$product->id} {$product->name}");
            return 0;
        }

        $azureKey    = config('services.azure_tts.key');
        $azureRegion = config('services.azure_tts.region', 'centralindia');
        $speaker     = $product->backend_speaker ?: $this->defaultSpeaker($product);
        $lang        = $this->normalizeLang($product->backend_language ?: $product->language ?: 'en-US');
        $categorySlug = $this->deriveCategorySlug($product);

        if (!$azureKey) {
            $this->error("  AZURE_KEY not set in .env — cannot generate audio");
            return 0;
        }

        $this->line("  [GENERATE] #{$product->id} {$product->name} — {$lang}/{$categorySlug} — " . count($messages) . " messages using {$speaker}");

        \Illuminate\Support\Facades\Log::info('[Azure TTS] Starting generation', [
            'product_id'    => $product->id,
            'product_name'  => $product->name,
            'lang'          => $lang,
            'speaker'       => $speaker,
            'category_slug' => $categorySlug,
            'message_count' => count($messages),
            'ssml_sample'   => $this->buildAzureSsml($messages[0] ?? 'test', $speaker, $lang),
        ]);

        $signedUrls = [];

        foreach ($messages as $index => $messageText) {
            $mp3Content = $this->callAzureTts($messageText, $speaker, $lang, $azureKey, $azureRegion);
            if (!$mp3Content) {
                $this->warn("    Track {$index}: Azure TTS failed for: " . Str::limit($messageText, 60));
                continue;
            }

            // Save to audio-cache so future migrations find it
            $slug      = $this->nodeSlug($messageText) . '-' . substr(md5($messageText), 0, 32);
            $cacheDir  = $this->audioCacheBase . '/' . $lang . '/' . $categorySlug . '/' . $speaker;
            @mkdir($cacheDir, 0755, true);
            $mp3Path = $cacheDir . '/' . $slug . '.mp3';
            file_put_contents($mp3Path, $mp3Content);

            $signedUrl = $this->processTrack($product, $index, $mp3Path, $lang, $categorySlug, true);
            if ($signedUrl) {
                $signedUrls[] = $signedUrl;
            }

            // Brief pause to respect Azure rate limits
            usleep(200000); // 200ms
        }

        if (!empty($signedUrls)) {
            $product->update([
                'audio_urls'      => array_values($signedUrls),
                'preview_audio_url' => $signedUrls[0],
                'backend_language' => $lang,
                'backend_speaker'  => $speaker,
            ]);
        }

        $this->line("    → Generated " . count($signedUrls) . "/" . count($messages) . " tracks");
        return count($signedUrls);
    }

    /**
     * Build SSML that replicates the Node audioGenerator.js buildSSML() approach.
     * Root xml:lang is always "en-US" (hardcoded for multilingual voices).
     * The inner <lang> element drives the accent (e.g. en-IN).
     */
    private function buildAzureSsml(string $text, string $speaker, string $targetLang): string
    {
        $cleanText = htmlspecialchars(trim($text), ENT_XML1 | ENT_COMPAT, 'UTF-8');

        // Mirror the Node.js buildSSML() in tts-backend/utils/audioGenerator.js exactly:
        // - Root xml:lang is always "en-US" (hardcoded, not derived from speaker)
        // - The <lang xml:lang="..."> element is what drives accent switching
        // - XML declaration included (as Node does)
        return <<<XML
<?xml version="1.0"?>
<speak version="1.0"
       xmlns="http://www.w3.org/2001/10/synthesis"
       xmlns:mstts="http://www.w3.org/2001/mstts"
       xmlns:emo="http://www.w3.org/2009/10/emotionml"
       xml:lang="en-US">
  <voice name="{$speaker}">
    <lang xml:lang="{$targetLang}">{$cleanText}</lang>
  </voice>
</speak>
XML;
    }

    private function callAzureTts(string $text, string $speaker, string $lang, string $key, string $region): ?string
    {
        $ssml     = $this->buildAzureSsml($text, $speaker, $lang);
        $endpoint = "https://{$region}.tts.speech.microsoft.com/cognitiveservices/v1";

        \Illuminate\Support\Facades\Log::info('[Azure TTS] Request', [
            'speaker'  => $speaker,
            'lang'     => $lang,
            'text'     => mb_substr($text, 0, 120),
            'endpoint' => $endpoint,
            'ssml'     => $ssml,
        ]);

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $key,
            'X-Microsoft-OutputFormat'  => 'audio-48khz-96kbitrate-mono-mp3',
        ])->timeout(30)->withBody($ssml, 'application/ssml+xml')->post($endpoint);

        $httpStatus = $response->status();
        $body       = $response->body();

        if ($httpStatus < 200 || $httpStatus >= 300) {
            \Illuminate\Support\Facades\Log::error('[Azure TTS] Failed', [
                'status'  => $httpStatus,
                'body'    => mb_substr($body, 0, 500),
                'speaker' => $speaker,
                'lang'    => $lang,
            ]);
            return null;
        }

        \Illuminate\Support\Facades\Log::info('[Azure TTS] Success', [
            'speaker' => $speaker,
            'lang'    => $lang,
            'bytes'   => strlen($body),
            'text'    => mb_substr($text, 0, 80),
        ]);

        return $body;
    }

    private function defaultSpeaker(TtsAudioProduct $product): string
    {
        $lang = strtolower($this->normalizeLang($product->backend_language ?: $product->language ?: 'en-US'));
        return match (true) {
            str_starts_with($lang, 'hi-in') => 'hi-IN-KavyaNeural',
            str_starts_with($lang, 'en-gb') => 'en-GB-AdaMultilingualNeural',
            str_starts_with($lang, 'en-in') => 'en-GB-AdaMultilingualNeural',
            default                         => 'en-US-AriaNeural',
        };
    }

    /**
     * Map product category/name to the predefined messages array.
     * Returns array of message strings, or [] if not found.
     */
    private function getMessagesForProduct(TtsAudioProduct $product): array
    {
        $slug = $this->deriveCategorySlug($product);
        $map  = $this->predefinedMessages();
        return $map[$slug] ?? [];
    }

    /**
     * Build slug → messages map.
     * Primary: loads from storage/app/tts-messages.json (exported from MongoDB).
     * Fallback: hardcoded array below for standard categories.
     */
    private function predefinedMessages(): array
    {
        // Load from MongoDB export if available
        $jsonPath = storage_path('app/tts-messages.json');
        if (file_exists($jsonPath)) {
            $raw = json_decode(file_get_contents($jsonPath), true);
            if (is_array($raw)) {
                $map = [];
                foreach ($raw as $catName => $messages) {
                    if (!is_array($messages) || empty($messages)) continue;
                    $slug = $this->nodeSlug($catName);
                    if (!isset($map[$slug])) {
                        $map[$slug] = array_values(array_filter($messages));
                    } else {
                        $map[$slug] = array_values(array_unique(array_filter(array_merge($map[$slug], $messages))));
                    }
                }
                // Merge with hardcoded fallbacks for categories not in JSON
                return array_merge($this->hardcodedMessages(), $map);
            }
        }
        return $this->hardcodedMessages();
    }

    private function hardcodedMessages(): array
    {
        return [
            'positive-attitude' => [
                'I keep my face toward the sunshine.',
                'I cultivate a positive attitude to make my dreams come true.',
                'I love what I do and it shows in my work.',
                'My optimism leads to achievement and success.',
                'I celebrate life and find joy in every moment.',
                'I rise after every fall and keep moving forward.',
                'My attitude is a powerful tool for positive change.',
                'I believe in myself and my potential.',
                'I focus on what I have and appreciate it.',
                'I choose positivity over negativity.',
                'I replace negative thoughts with positive ones.',
                'Each day brings new strength and new thoughts.',
                'My positive attitude sparks extraordinary results.',
                'My optimism multiplies my success.',
                'I choose to have a good day through my positive attitude.',
                'I radiate positivity and attract positive outcomes.',
                'I am grateful for all the good in my life.',
                'I see the best in every situation.',
                'I am a positive thinker and only attract positivity in my life.',
                'I approach life with a positive mindset and open heart.',
                'Keep your face always toward the sunshine and shadows will fall behind you. Walt Whitman',
                'A positive attitude can really make dreams come true. David Bailey',
                'The only way to do great work is to love what you do. Steve Jobs',
                'Optimism is the faith that leads to achievement. Helen Keller',
                'The more you praise and celebrate your life, the more there is in life to celebrate. Oprah Winfrey',
                'The only time you fail is when you fall down and stay down. Stephen Richards',
                'Attitude is a little thing that makes a big difference. Winston Churchill',
                'If you look at what you have in life, you will always have more. Oprah Winfrey',
                'Positive anything is better than negative nothing. Elbert Hubbard',
                'Once you replace negative thoughts with positive ones, you will start having positive results. Willie Nelson',
                'With the new day comes new strength and new thoughts. Eleanor Roosevelt',
                'Perpetual optimism is a force multiplier. Colin Powell',
                'The only difference between a good day and a bad day is your attitude. Dennis S. Brown',
            ],
            'supreme-confidence' => [
                'I am confident in my abilities and strengths.',
                'I trust myself to make the right decisions.',
                'I am worthy of success and happiness.',
                'I radiate confidence, self-respect, and inner harmony.',
                'I believe in my potential to achieve anything I set my mind to.',
                'I am secure in who I am.',
                'My confidence grows stronger each day.',
                'I face challenges with confidence and courage.',
                'I am self-assured and poised in every situation.',
                'I project confidence and inspire others.',
                'I am proud of who I am and what I stand for.',
                'I embrace my uniqueness and individuality.',
                'My confidence attracts success and opportunities.',
                'I walk tall and speak with clarity.',
                'I handle any situation with ease and confidence.',
                'I am bold, brave, and unstoppable.',
                'I have the courage to be myself.',
                'I am deserving of all the amazing things life has to offer.',
                'I step into my power with grace and confidence.',
                'I am enough just as I am.',
            ],
            'setting-goals-and-achieving-them' => [
                'I set clear, achievable goals for myself.',
                'I turn my goals into actionable plans.',
                'I am committed to achieving my goals.',
                'I take consistent action towards achieving my goals.',
                'I stay focused on my goals despite any obstacles.',
                'I celebrate my progress and the steps I take towards my goals.',
                'I visualize my success and work towards it every day.',
                'I break my goals into small, manageable steps.',
                'I set goals that align with my values and passions.',
                'I am successful in achieving my goals.',
                'I set ambitious goals and achieve them with determination.',
                'I plan my work and work my plan.',
                'I set goals that inspire and excite me.',
                'I believe in my ability to turn my dreams into reality.',
                'I am resilient and overcome any challenges in my way.',
                'I am proud of my accomplishments and the progress I make.',
                'I take action today to create the future I desire.',
                'Setting goals is the first step in turning the invisible into the visible.',
                'The future belongs to those who believe in the beauty of their dreams. Eleanor Roosevelt',
                'You are never too old to set another goal or to dream a new dream. C.S. Lewis',
            ],
            'instant-motivation' => [
                'I am capable of achieving great things.',
                'I am courageous and I push through any challenges.',
                'I believe in myself and my abilities.',
                'I keep moving forward with confidence.',
                'My imagination is limitless and so are my opportunities.',
                'I motivate myself to take action and achieve my goals.',
                'I thrive outside my comfort zone.',
                'I dream big and take action to make those dreams come true.',
                'I am proactive and go after what I want.',
                'The harder I work, the more I accomplish.',
                'I start where I am and use what I have to do what I can.',
                'I am led by the dreams in my heart.',
                'Everything I desire is within my reach.',
                'My actions make a positive difference.',
                'I am unstoppable and motivated to succeed.',
                'I have the power to create the life I want.',
                'I am dedicated to achieving my goals.',
                'I embrace challenges as opportunities for growth.',
                'I am filled with energy and enthusiasm.',
                'The only limit to our realization of tomorrow is our doubts of today. Franklin D. Roosevelt',
                'Believe you can and you are halfway there. Theodore Roosevelt',
                'Great things never come from comfort zones.',
                'Dream it. Wish it. Do it.',
                'The harder you work for something, the greater you will feel when you achieve it.',
                'Start where you are. Use what you have. Do what you can. Arthur Ashe',
                'Everything you have ever wanted is on the other side of fear. George Addair',
                'Act as if what you do makes a difference. It does. William James',
            ],
            'time-management' => [
                'I schedule my priorities and manage my time effectively.',
                'I use my time wisely and efficiently.',
                'I make the most of every moment.',
                'I take charge of my time and use it effectively.',
                'I manage my time to live a balanced and fulfilling life.',
                'I make time for the things that matter most.',
                'My future self thanks me for the decisions I make today.',
                'I value my time and use it wisely.',
                'I control my day and make it productive.',
                'I start today with purpose and intention.',
                'I am disciplined and organized with my time.',
                'I allocate my time to what truly matters.',
                'I respect my time and the time of others.',
                'I make every moment count in my journey to success.',
                'The key is not to prioritize what is on your schedule, but to schedule your priorities. Stephen Covey',
                'Lost time is never found again. Benjamin Franklin',
                'Time management is life management.',
                'It is not about having time. It is about making time.',
                'Do something today that your future self will thank you for.',
                'Either you run the day, or the day runs you. Jim Rohn',
                'A year from now you may wish you had started today. Karen Lamb',
                'Time is more valuable than money. You can get more money, but you cannot get more time. Jim Rohn',
            ],
            'overcoming-failure-and-resilience' => [
                'I learn and grow from every challenge I face.',
                'I am resilient and can overcome any obstacle.',
                'Every setback is a setup for a comeback.',
                'I embrace failure as a part of my journey to success.',
                'I rise stronger and wiser after every fall.',
                'My resilience is stronger than any challenge.',
                'I turn every failure into a valuable lesson.',
                'I am not defined by my failures, but by how I rise after them.',
                'I am persistent, patient, and resilient in the face of adversity.',
                'Each failure brings me closer to my goals.',
                'I am capable of overcoming anything that comes my way.',
                'I face challenges with courage and determination.',
                'Every mistake is an opportunity to learn and improve.',
                'I am stronger than any challenge that comes my way.',
                'I bounce back from setbacks with renewed energy and purpose.',
                'I see failure as a stepping stone to success.',
                'I am committed to my growth and learning.',
                'I overcome difficulties with grace and strength.',
                'I have the power to turn my failures into successes.',
                'I am unshakable in my resolve to succeed.',
                'Failure is simply the opportunity to begin again, this time more intelligently. Henry Ford',
                'The greatest glory in living lies not in never falling, but in rising every time we fall. Nelson Mandela',
                'I have not failed. I have just found ten thousand ways that will not work. Thomas Edison',
                'Only those who dare to fail greatly can ever achieve greatly. Robert F. Kennedy',
                'The only real mistake is the one from which we learn nothing. Henry Ford',
                'Resilience is knowing that you are the only one that has the power and the responsibility to pick yourself up. Mary Holloway',
            ],
            'relaxation-and-bliss' => [
                'I give myself permission to rest and recharge.',
                'I make time to relax and unwind.',
                'I am most productive when I take time to relax.',
                'I cultivate a calm and peaceful mind.',
                'I release tension and embrace relaxation.',
                'I balance work and rest for a healthy life.',
                'I make decisions with a calm and relaxed mind.',
                'I am my true self when I am relaxed.',
                'I value the simple joy of doing nothing.',
                'I care for myself and replenish my spirit.',
                'I find strength in calmness.',
                'I unplug and recharge when needed.',
                'I am enough just as I am.',
                'I maintain a calm mind for good health.',
                'I am deserving of rest and relaxation.',
                'I find peace in the present moment.',
                'I nurture my body and mind with relaxation.',
                'I am calm, centered, and focused.',
                'I create a tranquil space in my life.',
                'Take rest; a field that has rested gives a bountiful crop. Ovid',
                'Sometimes the most productive thing you can do is relax. Mark Black',
                'Tension is who you think you should be. Relaxation is who you are. Chinese Proverb',
                'Calmness is the cradle of power. Josiah Gilbert Holland',
                'Relax. You are enough. You do enough. Breathe extra deep, let go, and just live right now in the moment.',
                'A calm mind brings inner strength and self-confidence. Dalai Lama',
            ],
            'inspirational-quotes' => [
                'The only limit to our realization of tomorrow is our doubts of today. Franklin D. Roosevelt',
                'Believe you can and you are halfway there. Theodore Roosevelt',
                'Your limitation is only your imagination.',
                'Push yourself, because no one else is going to do it for you.',
                'Great things never come from comfort zones.',
                'Dream it. Wish it. Do it.',
                'The harder you work for something, the greater you will feel when you achieve it.',
                'Start where you are. Use what you have. Do what you can. Arthur Ashe',
                'Everything you have ever wanted is on the other side of fear. George Addair',
                'Act as if what you do makes a difference. It does. William James',
                'Keep your face always toward the sunshine and shadows will fall behind you. Walt Whitman',
                'The only way to do great work is to love what you do. Steve Jobs',
                'Optimism is the faith that leads to achievement. Helen Keller',
                'The more you praise and celebrate your life, the more there is in life to celebrate. Oprah Winfrey',
                'Attitude is a little thing that makes a big difference. Winston Churchill',
                'Positive anything is better than negative nothing. Elbert Hubbard',
                'Perpetual optimism is a force multiplier. Colin Powell',
                'The only difference between a good day and a bad day is your attitude. Dennis S. Brown',
                'Either you run the day, or the day runs you. Jim Rohn',
                'Time is more valuable than money. You can get more money, but you cannot get more time. Jim Rohn',
                'Lost time is never found again. Benjamin Franklin',
                'It always seems impossible until it is done. Nelson Mandela',
                'The secret of getting ahead is getting started. Mark Twain',
                'The journey of a thousand miles begins with one step. Lao Tzu',
                'Health is not valued until sickness comes. Thomas Fuller',
                'A year from now you may wish you had started today. Karen Lamb',
            ],
            'quit-smoking' => [
                'I am stronger than my cravings.',
                'I am capable of quitting smoking and living a healthier life.',
                'Every day, I get closer to being smoke-free.',
                'I can overcome any challenge, including quitting smoking.',
                'I am taking the first step toward a healthier life.',
                'I believe in myself and my ability to quit smoking.',
                'I stay committed to my decision to quit smoking.',
                'I am persistent and resilient in my journey to quit smoking.',
                'The best time to quit is now, and I am doing it.',
                'I am worth the effort to quit smoking.',
                'I value my health and choose to quit smoking.',
                'I am moving towards a healthier, smoke-free life.',
                'I prioritize my health and well-being.',
                'I am proud of my decision to quit smoking.',
                'I am free from the grip of smoking.',
                'I breathe easily and feel healthy.',
                'I have the power to change my habits.',
                'I choose health and happiness over smoking.',
                'The journey of a thousand miles begins with one step. Lao Tzu',
                'It always seems impossible until it is done. Nelson Mandela',
                'The secret of getting ahead is getting started. Mark Twain',
                'Believe you can and you are halfway there. Theodore Roosevelt',
                'You are stronger than you think. Stay committed to your decision to quit.',
                'Health is not valued until sickness comes. Thomas Fuller',
            ],
        ];
    }
}
