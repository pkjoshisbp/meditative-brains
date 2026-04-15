<?php

namespace App\Console\Commands;

use App\Services\AudioSecurityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportBgMusic extends Command
{
    protected $signature = 'bg-music:import
        {source : Absolute path to an audio file or a directory of audio files}
        {--encrypt : Encrypt imported files immediately}
        {--dry-run : Show what would be copied without writing files}';

    protected $description = 'Copy background music into storage/app/bg-music/original and optionally encrypt it';

    public function handle(AudioSecurityService $audioSecurityService): int
    {
        $source = (string) $this->argument('source');
        $dryRun = (bool) $this->option('dry-run');
        $encrypt = (bool) $this->option('encrypt');

        if (!file_exists($source)) {
            $this->error('Source path does not exist: ' . $source);
            return Command::FAILURE;
        }

        $files = is_dir($source)
            ? array_values(array_filter(glob(rtrim($source, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*') ?: [], 'is_file'))
            : [$source];

        $allowedExtensions = ['mp3', 'wav', 'm4a', 'flac', 'aac', 'ogg'];
        $copied = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions, true)) {
                $skipped++;
                continue;
            }

            $baseName = Str::slug(pathinfo($file, PATHINFO_FILENAME));
            $targetRelative = $baseName . '.' . $extension;
            $targetPath = 'bg-music/original/' . $targetRelative;

            if ($dryRun) {
                $this->line('[dry-run] copy ' . $file . ' -> ' . $targetPath);
                if ($encrypt) {
                    $this->line('[dry-run] encrypt bg-music/original/' . $targetRelative);
                }
                $copied++;
                continue;
            }

            Storage::disk('local')->put($targetPath, file_get_contents($file));
            if ($encrypt) {
                $audioSecurityService->encryptBgMusicFile($targetRelative);
            }
            $copied++;
        }

        $this->info("Imported: {$copied} | Skipped: {$skipped}" . ($dryRun ? ' (dry-run)' : ''));
        return Command::SUCCESS;
    }
}