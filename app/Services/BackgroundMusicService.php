<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackgroundMusicService
{
    private const ORIGINAL_DIRECTORY = 'bg-music/original';
    private const DEFAULT_EXPIRES_MINUTES = 60 * 24;

    public function __construct(private AudioSecurityService $audioSecurityService)
    {
    }

    public function listTracks(int $expiresInMinutes = self::DEFAULT_EXPIRES_MINUTES): array
    {
        return collect($this->originalAudioPaths())
            ->filter(fn (string $path): bool => $this->isSupportedAudioFile($path))
            ->map(fn (string $path): ?array => $this->trackPayloadFromOriginalPath($path, $expiresInMinutes))
            ->filter()
            ->sortBy('name')
            ->values()
            ->all();
    }

    public function findTrack(string $track, int $expiresInMinutes = self::DEFAULT_EXPIRES_MINUTES): ?array
    {
        $track = trim($track);
        if ($track === '') {
            return null;
        }

        $wantedStem = strtolower(pathinfo($track, PATHINFO_FILENAME));

        foreach ($this->originalAudioPaths() as $path) {
            if (!$this->isSupportedAudioFile($path)) {
                continue;
            }

            if (strtolower(pathinfo($path, PATHINFO_FILENAME)) !== $wantedStem) {
                continue;
            }

            return $this->trackPayloadFromOriginalPath($path, $expiresInMinutes);
        }

        return null;
    }

    public function availableTrackNames(): array
    {
        return collect($this->originalAudioPaths())
            ->filter(fn (string $path): bool => $this->isSupportedAudioFile($path))
            ->map(fn (string $path): string => pathinfo($path, PATHINFO_FILENAME))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function trackPayloadFromOriginalPath(string $path, int $expiresInMinutes): ?array
    {
        $filename = basename($path);
        $stem = pathinfo($filename, PATHINFO_FILENAME);

        try {
            $encryptedPath = $this->audioSecurityService->encryptBgMusicFile($filename);
        } catch (\Throwable) {
            return null;
        }

        return [
            'id' => $stem,
            'name' => Str::headline(str_replace(['-', '_'], ' ', $stem)),
            'filename' => $filename,
            'extension' => strtolower(pathinfo($filename, PATHINFO_EXTENSION)),
            'mime_type' => Storage::disk('local')->mimeType($path) ?: 'audio/aac',
            'size_bytes' => Storage::disk('local')->size($path),
            'stream_url' => $this->audioSecurityService->generateSignedUrl($encryptedPath, null, $expiresInMinutes),
            'expires_in_minutes' => $expiresInMinutes,
            'loop' => true,
        ];
    }

    private function isSupportedAudioFile(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), [
            'aac',
            'm4a',
            'mp3',
            'ogg',
            'wav',
        ], true);
    }

    private function originalAudioPaths(): array
    {
        $directory = storage_path('app/' . self::ORIGINAL_DIRECTORY);
        if (!is_dir($directory)) {
            return [];
        }

        $paths = [];
        foreach ((array) scandir($directory) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = self::ORIGINAL_DIRECTORY . '/' . $item;
            $realPath = realpath($directory . DIRECTORY_SEPARATOR . $item);
            if ($realPath && is_file($realPath)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
