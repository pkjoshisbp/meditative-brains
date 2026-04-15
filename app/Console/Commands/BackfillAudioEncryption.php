<?php

namespace App\Console\Commands;

use App\Services\AudioSecurityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class BackfillAudioEncryption extends Command
{
    protected $signature = 'audio:backfill-encryption
        {--scope=all : all, products, bg-music}
        {--dry-run : Show what would be encrypted without writing files}';

    protected $description = 'Encrypt existing original audio and background-music files into the structured encrypted storage tree';

    public function handle(AudioSecurityService $audioSecurityService): int
    {
        $scope = (string) $this->option('scope');
        $dryRun = (bool) $this->option('dry-run');
        $validScopes = ['all', 'products', 'bg-music'];

        if (!in_array($scope, $validScopes, true)) {
            $this->error('Invalid --scope. Use one of: ' . implode(', ', $validScopes));
            return Command::INVALID;
        }

        $targets = [];
        if ($scope === 'all' || $scope === 'products') {
            $targets[] = ['label' => 'product audio', 'root' => 'audio/original', 'type' => 'product'];
        }
        if ($scope === 'all' || $scope === 'bg-music') {
            $targets[] = ['label' => 'background music', 'root' => 'bg-music/original', 'type' => 'bg'];
        }

        $seen = 0;
        $encrypted = 0;
        $skipped = 0;
        $extensions = ['mp3', 'wav', 'm4a', 'flac', 'aac', 'ogg'];

        foreach ($targets as $target) {
            $this->info('Scanning ' . $target['label'] . ' under ' . $target['root']);
            foreach ($this->listRelativeFiles($target['root']) as $relative) {
                $path = $target['root'] . '/' . ltrim($relative, '/');
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (!in_array($extension, $extensions, true)) {
                    $skipped++;
                    continue;
                }

                $seen++;
                $targetEncryptedPath = $target['type'] === 'product'
                    ? 'audio/encrypted/' . preg_replace('/\.[^.]+$/', '.enc', $relative)
                    : 'bg-music/encrypted/' . preg_replace('/\.[^.]+$/', '.enc', $relative);

                if (Storage::disk('local')->exists($targetEncryptedPath)) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->line('[dry-run] encrypt ' . $path . ' -> ' . $targetEncryptedPath);
                    $encrypted++;
                    continue;
                }

                if ($target['type'] === 'product') {
                    $audioSecurityService->encryptOriginalFile($relative, 0);
                } else {
                    $audioSecurityService->encryptBgMusicFile($relative);
                }
                $encrypted++;
            }
        }

        $this->info("Scanned: {$seen} | Encrypted: {$encrypted} | Skipped: {$skipped}" . ($dryRun ? ' (dry-run)' : ''));
        return Command::SUCCESS;
    }

    /**
     * @return array<int, string>
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
            RecursiveIteratorIterator::LEAVES_ONLY
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
}