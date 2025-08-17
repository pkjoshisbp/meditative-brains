<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TtsAudioProduct;
use Illuminate\Support\Str;

class BackfillTtsGroupKeys extends Command
{
    protected $signature = 'tts:backfill-group-keys {--dry-run}';
    protected $description = 'Derive and persist group_key for existing TTS audio products';

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $updated = 0; $skipped = 0;
        $this->info('Scanning TTS audio products...');
        TtsAudioProduct::chunk(200, function($chunk) use (&$updated,&$skipped,$dry){
            foreach ($chunk as $p) {
                if ($p->group_key) { $skipped++; continue; }
                $base = $p->slug ? preg_replace('/-(en|hi|es|fr|de|it|pt|ja|ko|zh)(_[A-Z]{2})?(-[a-z0-9]+)?$/i','',$p->slug) : null;
                $candidate = $base ?: ($p->name ? Str::slug($p->name) : null);
                if (!$candidate && is_array($p->sample_messages) && count($p->sample_messages)) {
                    $candidate = substr(sha1($p->sample_messages[0]),0,16);
                }
                if (!$candidate) { $skipped++; continue; }
                if (!$dry) {
                    $p->group_key = $candidate;
                    $p->save();
                }
                $updated++;
            }
        });
        $this->info("Updated: $updated | Skipped: $skipped" . ($dry? ' (dry-run)':''));
        return Command::SUCCESS;
    }
}
