<?php

namespace App\Console\Commands;

use App\Models\TtsAudiobook;
use App\Models\TtsAudiobookChapter;
use App\Models\TtsAudioProduct;
use App\Models\TtsMotivationMessage;
use App\Services\AudioSecurityService;
use App\Services\TtsAudioGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class NormalizeAudioToAac extends Command
{
    protected $signature = 'audio:normalize-aac
        {--dry-run : Show planned conversions and DB updates without writing}
        {--keep-originals : Keep source mp3/wav files after successful AAC conversion}';

    protected $description = 'Convert legacy mp3/wav audio assets to AAC and rewrite canonical database references';

    private bool $dryRun;
    private bool $keepOriginals;

    /** @var array<string,int> */
    private array $stats = [
        'converted_files' => 0,
        'deleted_legacy_files' => 0,
        'updated_message_rows' => 0,
        'updated_audiobooks' => 0,
        'updated_audiobook_chapters' => 0,
        'updated_product_tracks' => 0,
        'warnings' => 0,
    ];

    public function handle(TtsAudioGeneratorService $tts, AudioSecurityService $security): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->keepOriginals = (bool) $this->option('keep-originals');

        $this->info(($this->dryRun ? '[dry-run] ' : '') . 'Normalizing legacy audio assets to AAC');

        $this->normalizeBackgroundMusic($tts, $security);
        $this->normalizeStorageRoots($tts);
        $this->normalizeMotivationMessages($tts, $security);
        $this->normalizeAudiobooks($tts, $security);
        $this->normalizeAudiobookChapters($tts, $security);

        $this->newLine();
        $this->info('Summary');
        foreach ($this->stats as $label => $value) {
            $this->line(sprintf('  %-24s %d', str_replace('_', ' ', $label) . ':', $value));
        }

        return Command::SUCCESS;
    }

    private function normalizeBackgroundMusic(TtsAudioGeneratorService $tts, AudioSecurityService $security): void
    {
        foreach ($this->listRelativeFiles('bg-music/original') as $relative) {
            if (!$this->isLegacyPath($relative)) {
                continue;
            }

            $newStoragePath = $this->convertManagedFile(
                'bg-music/original/' . $relative,
                $tts,
                ['bitrate' => '128k', 'channels' => 2, 'sample_rate' => 44100],
            );

            if (!$newStoragePath) {
                continue;
            }

            $newRelative = ltrim((string) preg_replace('#^bg-music/original/#', '', $newStoragePath), '/');

            $oldFilename = basename($relative);
            $newFilename = basename($newRelative);

            if ($oldFilename !== $newFilename) {
                $products = TtsAudioProduct::query()
                    ->where('background_music_track', $oldFilename)
                    ->get();

                foreach ($products as $product) {
                    if ($this->dryRun) {
                        $this->line("[dry-run] update tts_audio_products.background_music_track {$oldFilename} -> {$newFilename} (product #{$product->id})");
                    } else {
                        $product->background_music_track = $newFilename;
                        if (is_string($product->background_music_url) && str_contains($product->background_music_url, $oldFilename)) {
                            $product->background_music_url = str_replace($oldFilename, $newFilename, $product->background_music_url);
                        }
                        $product->save();
                    }
                    $this->stats['updated_product_tracks']++;
                }
            }

            $this->syncBgMusicArtifacts($relative, $newRelative, $security);
        }
    }

    private function normalizeStorageRoots(TtsAudioGeneratorService $tts): void
    {
        foreach (['audio/original', 'audio-cache', 'products-audio', 'audiobook'] as $root) {
            foreach ($this->listRelativeFiles($root) as $relative) {
                $storagePath = $root . '/' . $relative;
                if (!$this->isLegacyPath($storagePath)) {
                    continue;
                }

                $this->convertManagedFile($storagePath, $tts, ['bitrate' => '96k', 'channels' => 1, 'sample_rate' => 48000]);
            }
        }
    }

    private function normalizeMotivationMessages(TtsAudioGeneratorService $tts, AudioSecurityService $security): void
    {
        TtsMotivationMessage::query()->orderBy('id')->chunkById(50, function ($messages) use ($tts, $security) {
            foreach ($messages as $message) {
                $paths = is_array($message->audio_paths) ? $message->audio_paths : [];
                $urls = is_array($message->audio_urls) ? $message->audio_urls : [];
                $changed = false;

                foreach ($paths as $index => $path) {
                    if (!is_string($path) || !$this->isLegacyPath($path)) {
                        continue;
                    }

                    $newPath = $this->convertManagedFile($path, $tts, ['bitrate' => '96k', 'channels' => 1, 'sample_rate' => 48000]);
                    if (!$newPath) {
                        continue;
                    }

                    $paths[$index] = $newPath;
                    if (!$this->dryRun) {
                        try {
                            $urls[$index] = $security->encryptRawAudioAndSign(storage_path('app/' . $newPath), $newPath, true);
                        } catch (\Throwable $e) {
                            $this->warnOnce("Failed to refresh encrypted message audio for {$newPath}: {$e->getMessage()}");
                        }
                    }

                    $changed = true;
                }

                if (!$changed) {
                    continue;
                }

                if ($this->dryRun) {
                    $this->line("[dry-run] update tts_motivation_messages.audio_paths row #{$message->id}");
                } else {
                    $message->forceFill([
                        'audio_paths' => array_values($paths),
                        'audio_urls' => array_values($urls),
                    ])->save();
                }

                $this->stats['updated_message_rows']++;
            }
        });
    }

    private function normalizeAudiobooks(TtsAudioGeneratorService $tts, AudioSecurityService $security): void
    {
        TtsAudiobook::query()->orderBy('id')->chunkById(50, function ($books) use ($tts, $security) {
            foreach ($books as $book) {
                $path = $book->preview_audio_path;
                if (!is_string($path) || !$this->isLegacyPath($path)) {
                    continue;
                }

                $newPath = $this->convertManagedFile($path, $tts, ['bitrate' => '96k', 'channels' => 1, 'sample_rate' => 48000]);
                if (!$newPath) {
                    continue;
                }

                if ($this->dryRun) {
                    $this->line("[dry-run] update tts_audiobooks.preview_audio_path row #{$book->id}");
                } else {
                    $book->preview_audio_path = $newPath;
                    $book->preview_audio_url = $security->encryptRawAudioAndSign(storage_path('app/' . $newPath), $newPath, true);
                    $book->save();
                }

                $this->stats['updated_audiobooks']++;
            }
        });
    }

    private function normalizeAudiobookChapters(TtsAudioGeneratorService $tts, AudioSecurityService $security): void
    {
        TtsAudiobookChapter::query()->orderBy('id')->chunkById(50, function ($chapters) use ($tts, $security) {
            foreach ($chapters as $chapter) {
                $path = $chapter->audio_path;
                if (!is_string($path) || !$this->isLegacyPath($path)) {
                    continue;
                }

                $newPath = $this->convertManagedFile($path, $tts, ['bitrate' => '96k', 'channels' => 1, 'sample_rate' => 48000]);
                if (!$newPath) {
                    continue;
                }

                if ($this->dryRun) {
                    $this->line("[dry-run] update tts_audiobook_chapters.audio_path row #{$chapter->id}");
                } else {
                    $chapter->audio_path = $newPath;
                    $chapter->audio_url = $security->encryptRawAudioAndSign(storage_path('app/' . $newPath), $newPath, true);
                    $chapter->save();
                }

                $this->stats['updated_audiobook_chapters']++;
            }
        });
    }

    private function convertManagedFile(string $storagePath, TtsAudioGeneratorService $tts, array $options): ?string
    {
        if (!$this->isLegacyPath($storagePath)) {
            return $storagePath;
        }

        $newPath = preg_replace('/\.(mp3|wav)$/i', '.aac', $storagePath);
        if (!is_string($newPath) || $newPath === '') {
            return null;
        }

        $oldAbsolute = storage_path('app/' . ltrim($storagePath, '/'));
        $newAbsolute = storage_path('app/' . ltrim($newPath, '/'));

        if (!file_exists($oldAbsolute)) {
            if (file_exists($newAbsolute)) {
                return $newPath;
            }

            $this->warnOnce("Missing source audio file: {$storagePath}");
            return null;
        }

        if ($this->dryRun) {
            $this->line("[dry-run] convert {$storagePath} -> {$newPath}");
            return $newPath;
        }

        if (!file_exists($newAbsolute)) {
            $dir = dirname($newAbsolute);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            try {
                $tts->convertAudioToAac($oldAbsolute, $newAbsolute, $options);
                $this->stats['converted_files']++;
            } catch (\Throwable $e) {
                $this->warnOnce("Failed converting {$storagePath}: {$e->getMessage()}");
                return null;
            }
        }

        if (!$this->keepOriginals && file_exists($oldAbsolute)) {
            unlink($oldAbsolute);
            $this->stats['deleted_legacy_files']++;
        }

        return $newPath;
    }

    private function syncBgMusicArtifacts(string $oldRelative, string $newRelative, AudioSecurityService $security): void
    {
        $oldPublic = public_path('bg-music/' . $oldRelative);
        $newPublic = public_path('bg-music/' . $newRelative);
        $newOriginalAbsolute = storage_path('app/bg-music/original/' . $newRelative);
        $encryptedRelative = 'bg-music/encrypted/' . preg_replace('/\.[^.]+$/', '.enc', $newRelative);

        if ($this->dryRun) {
            $this->line("[dry-run] refresh bg-music public/encrypted artifacts for {$newRelative}");
            return;
        }

        $publicDir = dirname($newPublic);
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0775, true);
        }

        copy($newOriginalAbsolute, $newPublic);
        if (!$this->keepOriginals && file_exists($oldPublic)) {
            unlink($oldPublic);
        }

        Storage::disk('local')->delete($encryptedRelative);
        $security->encryptBgMusicFile($newRelative);
    }

    private function isLegacyPath(string $path): bool
    {
        return (bool) preg_match('/\.(mp3|wav)$/i', $path);
    }

    /**
     * @return array<int,string>
     */
    private function listRelativeFiles(string $root): array
    {
        $absoluteRoot = storage_path('app/' . trim($root, '/'));
        if (!is_dir($absoluteRoot)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absoluteRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $pathname = $item->getPathname();
            $realPath = realpath($pathname);
            if ($realPath === false || !is_file($realPath)) {
                continue;
            }

            $relative = ltrim(str_replace($absoluteRoot, '', $pathname), DIRECTORY_SEPARATOR);
            $files[] = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }

        sort($files);

        return $files;
    }

    private function warnOnce(string $message): void
    {
        $this->warn($message);
        $this->stats['warnings']++;
    }
}