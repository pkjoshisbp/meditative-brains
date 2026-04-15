<?php

namespace App\Console\Commands;

use App\Models\TtsMotivationMessage;
use App\Models\TtsSourceCategory;
use App\Models\TtsAudioProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportMongoToMysql extends Command
{
    protected $signature = 'tts:import-mongo
                            {--dry-run : Show what would be imported without making changes}
                            {--category= : Import only a specific MongoDB category name}';

    protected $description = 'Import MongoDB categories and motivation messages into MySQL (one-time migration)';

    public function handle(): int
    {
        $this->info('Exporting from MongoDB via Node.js...');

        $dryRun = $this->option('dry-run');
        $filterCategory = $this->option('category');

        if ($dryRun) {
            $this->warn('DRY RUN — no data will be written');
        }

        $categories = $this->loadMongoCategories();
        $messages   = $this->loadMongoMessages();

        $this->info(sprintf('Found %d MongoDB categories, %d message records', count($categories), count($messages)));

        $importedCats  = 0;
        $importedMsgs  = 0;
        $skippedMsgs   = 0;

        // ── Step 1: Import categories ────────────────────────────────────────
        // Track in-memory map for dry-run (where MySQL rows aren't written yet)
        $catMemoryMap = []; // mongoId => TtsSourceCategory (or mock object)

        foreach ($categories as $cat) {
            $mongoId  = (string) $cat['_id'];
            $catName  = $cat['category'] ?? '';

            if ($filterCategory && strtolower($catName) !== strtolower($filterCategory)) {
                continue;
            }

            if (!$dryRun) {
                $row = TtsSourceCategory::updateOrCreate(
                    ['mongo_id' => $mongoId],
                    ['category' => $catName]
                );
                $catMemoryMap[$mongoId] = $row;
            } else {
                // Simulate for dry-run
                $mock = new \stdClass();
                $mock->id    = -1;
                $mock->mongo_id  = $mongoId;
                $mock->category  = $catName;
                $catMemoryMap[$mongoId] = $mock;
            }
            $importedCats++;
        }

        $this->info("Categories imported/synced: {$importedCats}");

        // ── Step 2: Import message records ───────────────────────────────────
        foreach ($messages as $msg) {
            $mongoId     = (string) $msg['_id'];
            $mongoCatId  = (string) ($msg['categoryId'] ?? '');
            $language    = $msg['language'] ?? null;
            $speaker     = $msg['speaker'] ?? null;

            // Find category name for this record
            $cat = collect($categories)->first(fn($c) => (string) $c['_id'] === $mongoCatId);
            $catName = $cat['category'] ?? 'Unknown';

            if ($filterCategory && strtolower($catName) !== strtolower($filterCategory)) {
                continue;
            }

            // Resolve MySQL source_category_id — use in-memory map to support dry-run
            $sourceCategory = $catMemoryMap[$mongoCatId] ?? null;
            if (!$sourceCategory) {
                // Also try MySQL directly (for non-dry-run re-runs)
                $sourceCategory = TtsSourceCategory::where('mongo_id', $mongoCatId)->first();
            }
            if (!$sourceCategory) {
                $this->warn("  Skipping record {$mongoId}: source category {$mongoCatId} ({$catName}) not mapped");
                $skippedMsgs++;
                continue;
            }

            // Find matching TtsAudioProduct to copy existing audio_urls (no regeneration!)
            $audioUrls = $this->resolveExistingAudioUrls($catName, $language, $speaker);

            $messages_arr   = array_values(array_filter((array) ($msg['messages'] ?? []), fn($m) => is_string($m) && trim($m) !== ''));
            $ssml_messages  = array_values(array_filter((array) ($msg['ssmlMessages'] ?? []), fn($m) => is_string($m) && trim($m) !== ''));
            $ssml           = (array) ($msg['ssml'] ?? []);
            $audio_paths    = (array) ($msg['audioPaths'] ?? []);
            $existing_urls  = (array) ($msg['audioUrls'] ?? []);

            // Prefer MySQL product's signed URLs (they work), fall back to raw mongo urls
            $final_audio_urls = !empty($audioUrls) ? $audioUrls : $existing_urls;

            $data = [
                'source_category_id' => $sourceCategory->id,
                'messages'           => $messages_arr,
                'ssml_messages'      => $ssml_messages ?: $messages_arr,
                'ssml'               => $ssml,
                'engine'             => $msg['engine'] ?? 'azure',
                'language'           => $language ?? 'en-US',
                'speaker'            => $speaker ?? 'en-US-AriaNeural',
                'speaker_style'      => $msg['speakerStyle'] ?? null,
                'speaker_personality'=> $msg['speakerPersonality'] ?? null,
                'prosody_pitch'      => $msg['prosodyPitch'] ?? 'medium',
                'prosody_rate'       => $msg['prosodyRate'] ?? 'medium',
                'prosody_volume'     => $msg['prosodyVolume'] ?? 'medium',
                'audio_paths'        => $audio_paths,
                'audio_urls'         => $final_audio_urls,
                'editable'           => (bool) ($msg['editable'] ?? true),
            ];

            if ($dryRun) {
                $this->line("  [DRY] {$catName} | {$language} | {$speaker} | " . count($messages_arr) . " msgs | audio: " . count($final_audio_urls));
                $importedMsgs++;
                continue;
            }

            TtsMotivationMessage::updateOrCreate(
                ['mongo_id' => $mongoId],
                $data
            );

            $importedMsgs++;
            $this->line("  [OK] {$catName} | {$language} | {$speaker} | " . count($messages_arr) . " msgs | audio: " . count($final_audio_urls));
        }

        $this->info("Messages imported/synced: {$importedMsgs}");
        if ($skippedMsgs) {
            $this->warn("Messages skipped: {$skippedMsgs}");
        }

        if (!$dryRun) {
            $this->info('Import complete. MySQL tts_motivation_messages now has ' . TtsMotivationMessage::count() . ' records.');
        }

        return 0;
    }

    /**
     * Match a MongoDB record to a MySQL TtsAudioProduct to copy existing signed URLs.
     * No audio is regenerated — we only reuse what's already there.
     */
    private function resolveExistingAudioUrls(string $catName, ?string $language, ?string $speaker): array
    {
        if (!$language) {
            return [];
        }

        $query = TtsAudioProduct::where('is_active', 1)->where('language', $language);

        // Match by backend_category_name first
        $product = (clone $query)->where('backend_category_name', $catName)->first();

        // Fall back: match by product name containing category name
        if (!$product) {
            $product = (clone $query)
                ->where('name', 'like', '%' . $catName . '%')
                ->when($speaker, fn($q) => $q->where('backend_speaker', $speaker))
                ->first();
        }

        // Fallback without speaker constraint
        if (!$product && $speaker) {
            $product = (clone $query)
                ->where('name', 'like', '%' . $catName . '%')
                ->first();
        }

        if (!$product) {
            return [];
        }

        $raw = $product->audio_urls;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return is_array($raw) ? $raw : [];
    }

    private function loadMongoCategories(): array
    {
        return $this->exportCollection('categories');
    }

    private function loadMongoMessages(): array
    {
        return $this->exportCollection('motivationmessages');
    }

    /**
     * Use Node.js (already on this server) to dump a MongoDB collection as a JSON array.
     * This avoids needing the MongoDB PHP extension.
     */
    private function exportCollection(string $collection): array
    {
        $js = implode('', [
            'const mongoose = require("mongoose");',
            'mongoose.connect("mongodb://pawan:pragati123..@127.0.0.1:27017/motivation").then(async () => {',
            '  const docs = await mongoose.connection.db.collection("' . $collection . '").find({}).toArray();',
            '  const cleaned = docs.map(d => {',
            '    d._id = d._id.toString();',
            '    if (d.categoryId && typeof d.categoryId === "object") d.categoryId = d.categoryId.toString();',
            '    return d;',
            '  });',
            '  console.log(JSON.stringify(cleaned));',
            '  process.exit(0);',
            '}).catch(e => { process.stderr.write(e.message); process.exit(1); });',
        ]);

        $nodeDir = base_path('tts-backend');
        $tmpFile  = sys_get_temp_dir() . '/mongo_export_' . $collection . '.js';
        $outFile  = sys_get_temp_dir() . '/mongo_export_' . $collection . '.json';
        file_put_contents($tmpFile, $js);

        $cmd = "NODE_PATH=" . escapeshellarg($nodeDir . '/node_modules') .
               " node " . escapeshellarg($tmpFile) .
               " > " . escapeshellarg($outFile) . " 2>/dev/null";
        exec($cmd, $ignored, $exitCode);
        @unlink($tmpFile);

        if ($exitCode !== 0 || !file_exists($outFile) || filesize($outFile) === 0) {
            @unlink($outFile);
            throw new \RuntimeException("Failed to export MongoDB collection: {$collection}.");
        }

        $output = file_get_contents($outFile);
        @unlink($outFile);

        $data = json_decode(trim($output), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON from MongoDB export ({$collection}): " . json_last_error_msg());
        }

        return $data;
    }
}
