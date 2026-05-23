<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * TtsAudioGeneratorService
 *
 * Drop-in PHP replacement for Node.js utils/audioGenerator.js
 *
 * Supports:
 *  - engine: "azure"  → Azure Cognitive Services TTS (SSML, multi-language, styles)
 *  - engine: "vits"   → Local VITS Python model (English p225/p227, Hindi hi-female/hi-male)
 *
 * Output format: AAC (.aac) stored under storage/app/audio-cache/…
 * Audio URLs are routed via the existing Laravel /audio/stream signed-URL endpoint.
 */
class TtsAudioGeneratorService
{
    private string $audioCacheBase;
    private string $productsAudioBase;
    private string $tempTextDir;
    private string $azureKey;
    private string $azureRegion;
    private string $azureEndpoint;
    private string $vitsVenv;
    private string $vitsScriptPath;

    private string $audiobooksBase;

    public function __construct()
    {
        $this->audioCacheBase   = storage_path('app/audio-cache');
        $this->productsAudioBase = storage_path('app/products-audio');
        $this->audiobooksBase   = storage_path('app/audiobook');
        $this->tempTextDir      = storage_path('app/temp-texts');
        $this->azureKey         = config('services.azure_tts.key', env('AZURE_KEY', ''));
        $this->azureRegion      = config('services.azure_tts.region', env('AZURE_REGION', 'centralindia'));
        $this->azureEndpoint    = "https://{$this->azureRegion}.tts.speech.microsoft.com/cognitiveservices/v1";
        $this->vitsVenv         = base_path('../tts-backend/tts-venv/bin/python3');
        $this->vitsScriptPath   = base_path('../tts-backend/run_infer.py');

        foreach ([$this->audioCacheBase, $this->productsAudioBase, $this->audiobooksBase, $this->tempTextDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate audio for a single text string.
     *
     * @param string $text
     * @param array  $options  engine, language, speaker, speakerStyle, speakerPersonality,
     *                         ssml, prosodyRate, prosodyPitch, prosodyVolume,
     *                         category, storageType (cache|products)
     * @return array{relativePath: string, audioUrl: string}
     */
    public function generateForMessage(string $text, array $options = []): array
    {
        $options = $this->normaliseOptions($options);
        $paths   = $this->buildPaths($text, $options);

        // Return cached result if file already present
        if (file_exists($paths['absolutePath'])) {
            Log::info('[TTS] CACHE HIT — skipping Azure call, returning existing audio', [
                'path'         => $paths['relativePath'],
                'speaker'      => $options['speaker'],
                'rate'         => $options['prosodyRate'],
                'pitch'        => $options['prosodyPitch'],
                'volume'       => $options['prosodyVolume'],
                'style'        => $options['speakerStyle'] ?? null,
                'personality'  => $options['speakerPersonality'] ?? null,
            ]);
            return ['relativePath' => $paths['relativePath'], 'audioUrl' => $paths['audioUrl'], 'absolutePath' => $paths['absolutePath']];
        }

        $dir = dirname($paths['absolutePath']);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        if ($options['engine'] === 'azure') {
            $this->generateAzure($text, $paths['absolutePath'], $options);
        } else {
            $this->generateVits($text, $paths['absolutePath'], $options);
        }

        return ['relativePath' => $paths['relativePath'], 'audioUrl' => $paths['audioUrl'], 'absolutePath' => $paths['absolutePath']];
    }

    /**
     * Generate audio for every sentence in $messages, return array of results.
     */
    public function generateBatch(array $messages, array $options = []): array
    {
        $results = [];
        foreach ($messages as $msg) {
            try {
                $results[] = $this->generateForMessage($msg, $options);
            } catch (\Throwable $e) {
                Log::error('TTS batch item failed', ['text' => substr($msg, 0, 80), 'error' => $e->getMessage()]);
                $results[] = ['relativePath' => null, 'audioUrl' => null, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  SSML helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Convert custom markup notation (same set as Node.js convertMarkupToSSML).
     */
    public function convertMarkupToSSML(string $text): string
    {
        // [pause:N] / [silence:N]
        $text = preg_replace('/\[pause:(\d+)\]/i', '<break time="$1ms"/>', $text);
        $text = preg_replace('/\[silence:(\d+)\]/i', '<break time="$1ms"/>', $text);

        // [personality:X]…[/personality]  (strips surrounding quotes from value)
        $text = preg_replace_callback(
            '/\[personality:([^\]]+)\]([\s\S]*?)\[\/personality\]/i',
            fn ($m) => '<mstts:express-as style="' . strtolower(trim($m[1], " \t\r\n\"'")) . '">' . $m[2] . '</mstts:express-as>',
            $text
        );

        // [rate:X]…[/rate]  — strips quotes; absolute % (e.g. "90%") auto-converted to relative ("-10%")
        $text = preg_replace_callback(
            '/\[rate:([^\]]+)\]([\s\S]*?)\[\/rate\]/i',
            function ($m) {
                $val = $this->convertToRelativePercent(trim($m[1], " \t\r\n\"'")) ?? 'medium';
                return '<prosody rate="' . $val . '">' . $m[2] . '</prosody>';
            },
            $text
        );

        // [pitch:X]…[/pitch]  — strips quotes; absolute % auto-converted to relative
        $text = preg_replace_callback(
            '/\[pitch:([^\]]+)\]([\s\S]*?)\[\/pitch\]/i',
            function ($m) {
                $val = $this->convertToRelativePercent(trim($m[1], " \t\r\n\"'")) ?? 'medium';
                return '<prosody pitch="' . $val . '">' . $m[2] . '</prosody>';
            },
            $text
        );

        // [volume:X]…[/volume]  — strips quotes; absolute % auto-converted to relative
        $text = preg_replace_callback(
            '/\[volume:([^\]]+)\]([\s\S]*?)\[\/volume\]/i',
            function ($m) {
                $val = $this->convertToRelativePercent(trim($m[1], " \t\r\n\"'")) ?? 'medium';
                return '<prosody volume="' . $val . '">' . $m[2] . '</prosody>';
            },
            $text
        );

        // **strong**
        $text = preg_replace('/\*\*([^*]+)\*\*/u', '<emphasis level="strong">$1</emphasis>', $text);
        // *moderate*
        $text = preg_replace('/\*([^*]+)\*/u', '<emphasis level="moderate">$1</emphasis>', $text);

        // Strip unrecognised tags
        $text = preg_replace('/\[[^\]]*\]/', '', $text);

        return $text;
    }

    /**
     * Convert an absolute-percentage prosody value to Azure's relative-change format.
     *
     * Azure SSML interprets rate/pitch percentages as relative offsets from the default:
     *   rate="90%"  → +90% faster (190% of normal)
     *   rate="-10%" → 10% slower  (90% of normal)
     *
     * Our UI stores values as absolute percentages (90 = 90% of normal speed),
     * so we convert:  90% → -10%,  100% → medium,  110% → +10%
     *
     * Named values (medium, slow, fast, x-slow, x-fast, default) are passed through.
     */
    private function convertToRelativePercent(?string $value): ?string
    {
        if ($value === null || $value === '') return null;

        // 'default' means "do not set this attribute" — return null so no prosody attr is emitted
        if (strtolower(trim($value)) === 'default') return null;

        // Match values like "90%", "110%", "100", "90" (with or without %-sign)
        if (preg_match('/^(\d+(?:\.\d+)?)%?$/', trim($value), $m)) {
            $rel = (float)$m[1] - 100.0;
            if ($rel == 0.0) return 'medium';
            return ($rel > 0 ? '+' : '') . rtrim(rtrim(number_format($rel, 1), '0'), '.') . '%';
        }

        return $value; // named value (slow, medium, fast, etc.) — pass through
    }

    /**
     * Build a full Azure SSML document.
     * Same logic as Node.js buildSSML / keeps root xml:lang="en-US" for multilingual voices.
     */
    public function buildSSML(array $opts): string
    {
        if (!empty($opts['ssml'])) {
            return $opts['ssml'];
        }

        $text       = $this->convertMarkupToSSML($opts['text'] ?? '');
        $language   = $opts['language'] ?? 'en-US';
        $speaker    = $opts['speaker']  ?? 'en-US-AriaNeural';
        $style      = $opts['speakerStyle']       ?? null;
        $personality = $opts['speakerPersonality'] ?? null;
        // Convert absolute-percentage values (e.g. "90%") to Azure's relative-change
        // format (e.g. "-10%"). Azure treats rate/pitch percentages as relative offsets
        // from the default, so "90%" would mean +90% (=190% speed), not 90% of normal.
        $rate       = $this->convertToRelativePercent($opts['prosodyRate']   ?? null);
        $pitch      = $this->convertToRelativePercent($opts['prosodyPitch']  ?? null);
        $volume     = $this->convertToRelativePercent($opts['prosodyVolume'] ?? null);

        $needsProsody = ($rate && $rate !== 'medium')
                     || ($pitch && $pitch !== 'medium')
                     || ($volume && $volume !== 'medium');

        $ssml  = "<?xml version=\"1.0\"?>\n";
        $ssml .= "<speak version=\"1.0\"\n";
        $ssml .= "       xmlns=\"http://www.w3.org/2001/10/synthesis\"\n";
        $ssml .= "       xmlns:mstts=\"http://www.w3.org/2001/mstts\"\n";
        $ssml .= "       xmlns:emo=\"http://www.w3.org/2009/10/emotionml\"\n";
        $ssml .= "       xml:lang=\"en-US\">\n";
        $ssml .= "  <voice name=\"{$speaker}\">\n";

        if ($personality) {
            $ssml .= "    <mstts:express-as role=\"{$personality}\">\n";
        }
        if ($style) {
            $ssml .= "    <mstts:express-as style=\"{$style}\">\n";
        }
        if ($needsProsody) {
            $attrs = '';
            if ($rate && $rate !== 'medium')   $attrs .= " rate=\"{$rate}\"";
            if ($pitch && $pitch !== 'medium') $attrs .= " pitch=\"{$pitch}\"";
            if ($volume && $volume !== 'medium') $attrs .= " volume=\"{$volume}\"";
            $ssml .= "      <prosody{$attrs}>\n";
        }

        $ssml .= "      <lang xml:lang=\"{$language}\">{$text}</lang>\n";

        if ($needsProsody) $ssml .= "      </prosody>\n";
        if ($style)        $ssml .= "    </mstts:express-as>\n";
        if ($personality)  $ssml .= "    </mstts:express-as>\n";

        $ssml .= "  </voice>\n</speak>";

        return $ssml;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Azure generation
    // ─────────────────────────────────────────────────────────────────────────

    private function generateAzure(string $text, string $absolutePath, array $options): void
    {
        $ssml = $this->buildSSML(array_merge($options, ['text' => $text]));

        Log::info('[TTS][AZURE] Sending request to Azure', [
            'speaker'      => $options['speaker'],
            'language'     => $options['language'],
            'style'        => $options['speakerStyle']       ?? null,
            'personality'  => $options['speakerPersonality'] ?? null,
            'prosodyRate'  => $options['prosodyRate']   ?? 'medium',
            'prosodyPitch' => $options['prosodyPitch']  ?? 'medium',
            'prosodyVolume'=> $options['prosodyVolume'] ?? 'medium',
            'text_length'  => strlen($text),
            'ssml_full'    => $ssml,
        ]);

        $response = Http::timeout(180)->withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->azureKey,
            'Content-Type'              => 'application/ssml+xml',
            'X-Microsoft-OutputFormat'  => 'riff-48khz-16bit-mono-pcm',
            'User-Agent'                => 'MentalFitnessLaravelClient',
        ])->withBody($ssml, 'application/ssml+xml')
          ->post($this->azureEndpoint);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Azure TTS failed: HTTP ' . $response->status() . ' - ' . $response->body()
            );
        }

        $wavContent = $response->body();
        if (empty($wavContent)) {
            throw new \RuntimeException('Azure TTS returned empty audio data');
        }

        // Write temp WAV, convert to AAC via FFmpeg
        $tempWav = $absolutePath . '.wav';
        file_put_contents($tempWav, $wavContent);

        $this->wavToAac($tempWav, $absolutePath);

        if (file_exists($tempWav)) {
            unlink($tempWav);
        }

        Log::info('Azure TTS generated', ['path' => $absolutePath, 'bytes' => filesize($absolutePath)]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  VITS generation
    // ─────────────────────────────────────────────────────────────────────────

    private function generateVits(string $text, string $absolutePath, array $options): void
    {
        $speaker  = $this->resolveVitsSpeaker($options);
        $language = $options['language'] ?? 'en';

        // Determine model path
        if (in_array($language, ['hi', 'hi-IN', 'hi_IN'])) {
            $modelDir = str_contains($speaker, 'female')
                ? base_path('../tts-backend/tts_vits_coquiai_HindiFemale')
                : base_path('../tts-backend/tts_vits_coquiai_HindiMale');
        } else {
            $modelDir = base_path('../tts-backend/tts-venv/lib/python3.10/site-packages/TTS/models');
        }

        $textFile = $this->tempTextDir . '/' . md5($text) . '.txt';
        file_put_contents($textFile, $text);

        $tempWav = $absolutePath . '.wav';

        $process = new Process([
            $this->vitsVenv,
            $this->vitsScriptPath,
            '--text',     $text,
            '--speaker',  $speaker,
            '--out',      $tempWav,
            '--language', $language,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful() || !file_exists($tempWav)) {
            throw new \RuntimeException('VITS generation failed: ' . $process->getErrorOutput());
        }

        $this->wavToAac($tempWav, $absolutePath);
        if (file_exists($tempWav)) unlink($tempWav);
        if (file_exists($textFile)) unlink($textFile);

        Log::info('VITS generated', ['path' => $absolutePath, 'speaker' => $speaker]);
    }

    private function resolveVitsSpeaker(array $options): string
    {
        $speaker  = $options['speaker'] ?? 'p225';
        $language = $options['language'] ?? 'en';

        if (in_array($language, ['hi', 'hi-IN', 'hi_IN'])) {
            if (str_contains($speaker, 'female') || $speaker === 'hi-female') return 'hi-female';
            if (str_contains($speaker, 'male')   || $speaker === 'hi-male')   return 'hi-male';
            return 'hi-female';
        }

        $map = ['p225' => 'p225', 'p227' => 'p227', 'p230' => 'p230', 'p245' => 'p245'];
        foreach ($map as $key => $val) {
            if (str_contains($speaker, $key)) return $val;
        }

        return 'p225';
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Convert any FFmpeg-supported audio input (webm, ogg, wav, mp4, …) to AAC.
     * Used for browser voice recordings, background music imports, and format
     * normalization of legacy assets.
     *
     * Supported options:
     *  - bitrate: e.g. 96k, 128k, 192k
     *  - channels: 1 for mono, 2 for stereo
     *  - sample_rate: e.g. 44100, 48000
     */
    public function convertAudioToAac(string $inputPath, string $outputPath, array $options = []): void
    {
        $this->wavToAac($inputPath, $outputPath, $options);
    }

    private function wavToAac(string $wavPath, string $aacPath, array $options = []): void
    {
        $bitrate = (string) ($options['bitrate'] ?? '192k');
        $channels = (string) ($options['channels'] ?? '1');
        $sampleRate = (string) ($options['sample_rate'] ?? '48000');

        $process = new Process([
            'ffmpeg', '-i', $wavPath,
            '-c:a', 'aac', '-b:a', $bitrate, '-ac', $channels, '-ar', $sampleRate,
            $aacPath, '-y'
        ]);
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful() || !file_exists($aacPath)) {
            throw new \RuntimeException('ffmpeg WAV→AAC failed: ' . $process->getErrorOutput());
        }
    }

    private function normaliseOptions(array $opts): array
    {
        $defaults = [
            'engine'             => 'azure',
            'language'           => 'en-US',
            'speaker'            => 'en-US-AriaNeural',
            'speakerStyle'       => null,
            'speakerPersonality' => null,
            'ssml'               => null,
            'prosodyRate'        => 'medium',
            'prosodyPitch'       => 'medium',
            'prosodyVolume'      => 'medium',
            'category'           => 'default',
            'storageType'        => 'cache',
            'speed'              => 1.0,
            'noise'              => 0.667,
            'noiseW'             => 0.8,
        ];

        $opts = array_merge($defaults, array_filter($opts, fn ($v) => $v !== null && $v !== ''));

        // Normalise language code  (hn-IN → hi-IN, underscores → dashes)
        $opts['language'] = $this->normaliseLanguageCode($opts['language']);

        // For VITS engine keep language as 'en' not 'en-US'
        if ($opts['engine'] === 'vits') {
            $opts['speaker'] = $this->resolveVitsSpeaker($opts);
        }

        return $opts;
    }

    public function normaliseLanguageCode(string $lang): string
    {
        $lang = str_replace('_', '-', trim($lang));
        if ($lang === 'hn-IN') $lang = 'hi-IN';
        $parts = explode('-', $lang);
        if (count($parts) === 1) {
            $base   = strtolower($parts[0]);
            $region = ($base === 'en') ? 'US' : strtoupper($base);
            return "{$base}-{$region}";
        }
        return strtolower($parts[0]) . '-' . strtoupper($parts[1]);
    }

    private function buildPaths(string $text, array $options): array
    {
        $hash    = md5($text);
        $slug    = $this->slugify($text, 40);
        $ext     = 'aac';
        $engine  = $options['engine'];
        $lang    = $options['language'];
        $speaker = $options['speaker'];
        $cat     = $this->slugify($options['category'] ?? 'default', 60);

        if ($engine === 'vits') {
            $speaker = $this->resolveVitsSpeaker($options);
        }

        // Include a settings fingerprint so that changing rate/pitch/volume/style
        // produces a different file rather than reusing stale cached audio.
        $settingsFingerprint = substr(md5(implode('|', [
            $options['prosodyRate']        ?? 'medium',
            $options['prosodyPitch']       ?? 'medium',
            $options['prosodyVolume']      ?? 'medium',
            $options['speakerStyle']       ?? '',
            $options['speakerPersonality'] ?? '',
        ])), 0, 8);

        $relative = implode('/', [$lang, $cat, $speaker, "{$slug}-{$hash}-{$settingsFingerprint}.{$ext}"]);

        if ($options['storageType'] === 'products') {
            $base       = $this->productsAudioBase;
            $storageKey = 'products-audio/' . $relative;
        } elseif ($options['storageType'] === 'audiobook') {
            $base       = $this->audiobooksBase;
            $storageKey = 'audiobook/' . $relative;
        } else {
            $base       = $this->audioCacheBase;
            $storageKey = 'audio-cache/' . $relative;
        }

        $absolute = $base . '/' . $relative;
        // NOTE: audioUrl is intentionally left empty here; callers that serve audio
        // to Flutter must encrypt and sign the file via AudioSecurityService::encryptRawAudioAndSign().
        $audioUrl = '';

        return [
            'relativePath' => $storageKey,
            'absolutePath' => $absolute,
            'audioUrl'     => $audioUrl,
        ];
    }

    private function slugify(string $text, int $max = 60): string
    {
        $text = strtolower($text);
        $text = str_replace('&', 'and', $text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s]+/', '-', trim($text));
        $text = trim($text, '-');
        return substr($text, 0, $max);
    }
}
