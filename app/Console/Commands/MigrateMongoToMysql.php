<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\TtsSourceCategory;
use App\Models\TtsMotivationMessage;
use App\Models\TtsLanguage;
use App\Models\TtsAudiobook;
use App\Models\TtsAudiobookChapter;

/**
 * MigrateMongoToMysql
 *
 * One-time migration of MongoDB data (exported as JSON files) to MySQL.
 *
 * Usage:
 *   1. Export MongoDB collections as JSON files to storage/app/mongo-export/:
 *        mongoexport --db=mentalfitness --collection=categories   --jsonArray --out=categories.json
 *        mongoexport --db=mentalfitness --collection=motivationmessages --jsonArray --out=messages.json
 *        mongoexport --db=mentalfitness --collection=languages     --jsonArray --out=languages.json
 *        mongoexport --db=mentalfitness --collection=audiobooks    --jsonArray --out=audiobooks.json
 *
 *   2. php artisan tts:migrate-mongo
 */
class MigrateMongoToMysql extends Command
{
    protected $signature   = 'tts:migrate-mongo {--dir= : Folder containing JSON exports (default: storage/app/mongo-export)}';
    protected $description = 'Import MongoDB JSON exports into MySQL TTS tables';

    public function handle(): int
    {
        $dir = $this->option('dir') ?: storage_path('app/mongo-export');

        if (!is_dir($dir)) {
            $this->error("Export directory not found: {$dir}");
            return self::FAILURE;
        }

        $this->importLanguages($dir);
        $this->importCategories($dir);
        $this->importMessages($dir);
        $this->importAudiobooks($dir);

        $this->info('MongoDB → MySQL migration complete.');
        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function importLanguages(string $dir): void
    {
        $file = $dir . '/languages.json';
        if (!file_exists($file)) {
            $this->warn('languages.json not found, skipping. Seeding defaults…');
            $this->seedDefaultLanguages();
            return;
        }

        $rows = $this->loadJson($file);
        $this->withProgressBar($rows, function ($row) {
            TtsLanguage::firstOrCreate(
                ['code' => $row['code']],
                [
                    'name'       => $row['name'] ?? $row['code'],
                    'local_name' => $row['localName'] ?? $row['local_name'] ?? null,
                    'is_active'  => $row['isActive'] ?? $row['is_active'] ?? true,
                ]
            );
        });
        $this->line(' Languages imported (' . count($rows) . ')');
    }

    private function seedDefaultLanguages(): void
    {
        $defaults = [
            ['code' => 'en-US', 'name' => 'English (US)',    'local_name' => 'English',  'is_active' => true],
            ['code' => 'en-IN', 'name' => 'English (India)', 'local_name' => 'English',  'is_active' => true],
            ['code' => 'en-GB', 'name' => 'English (UK)',    'local_name' => 'English',  'is_active' => true],
            ['code' => 'hi-IN', 'name' => 'Hindi',           'local_name' => 'हिन्दी', 'is_active' => true],
            ['code' => 'mr-IN', 'name' => 'Marathi',         'local_name' => 'मराठी',  'is_active' => true],
            ['code' => 'en-AU', 'name' => 'English (Australia)', 'local_name' => 'English', 'is_active' => true],
        ];

        foreach ($defaults as $lang) {
            TtsLanguage::firstOrCreate(['code' => $lang['code']], $lang);
        }
        $this->line(' Seeded ' . count($defaults) . ' default languages');
    }

    private function importCategories(string $dir): void
    {
        $file = $dir . '/categories.json';
        if (!file_exists($file)) { $this->warn('categories.json not found, skipping'); return; }

        $rows = $this->loadJson($file);
        $this->withProgressBar($rows, function ($row) {
            TtsSourceCategory::firstOrCreate(
                ['mongo_id' => $this->extractId($row)],
                [
                    'category' => $row['category'],
                    'user_id'  => null,
                ]
            );
        });
        $this->line(' Categories imported (' . count($rows) . ')');
    }

    private function importMessages(string $dir): void
    {
        $file = $dir . '/messages.json';
        if (!file_exists($file)) { $this->warn('messages.json not found, skipping'); return; }

        $rows = $this->loadJson($file);
        $this->withProgressBar($rows, function ($row) {
            $mongoId  = $this->extractId($row);
            $catId    = $row['category'] ?? $row['categoryId'] ?? null;

            // Resolve category (by mongo_id)
            $cat = null;
            if ($catId) {
                $catMongoId = is_array($catId) ? ($catId['$oid'] ?? null) : $catId;
                $cat = TtsSourceCategory::where('mongo_id', $catMongoId)->first();
            }

            TtsMotivationMessage::firstOrCreate(
                ['mongo_id' => $mongoId],
                [
                    'source_category_id'  => $cat?->id,
                    'messages'            => is_array($row['messages'] ?? null) ? $row['messages'] : [],
                    'ssml_messages'       => $row['ssmlMessages'] ?? $row['ssml_messages'] ?? [],
                    'ssml'                => $row['ssml'] ?? [],
                    'engine'              => $row['engine'] ?? 'azure',
                    'language'            => $row['language'] ?? 'en-US',
                    'speaker'             => $row['speaker'] ?? 'en-US-AriaNeural',
                    'speaker_style'       => $row['speakerStyle'] ?? null,
                    'speaker_personality' => $row['speakerPersonality'] ?? null,
                    'prosody_rate'        => $row['prosodyRate'] ?? 'medium',
                    'prosody_pitch'       => $row['prosodyPitch'] ?? 'medium',
                    'prosody_volume'      => $row['prosodyVolume'] ?? 'medium',
                    'audio_paths'         => $row['audioPaths'] ?? $row['audio_paths'] ?? [],
                    'audio_urls'          => $row['audioUrls'] ?? $row['audio_urls'] ?? [],
                    'editable'            => $row['editable'] ?? true,
                ]
            );
        });
        $this->line(' Messages imported (' . count($rows) . ')');
    }

    private function importAudiobooks(string $dir): void
    {
        $file = $dir . '/audiobooks.json';
        if (!file_exists($file)) { $this->warn('audiobooks.json not found, skipping'); return; }

        $rows = $this->loadJson($file);
        $this->withProgressBar($rows, function ($row) {
            $book = TtsAudiobook::firstOrCreate(
                ['mongo_id' => $this->extractId($row)],
                [
                    'book_title'      => $row['bookTitle'] ?? $row['book_title'],
                    'book_author'     => $row['bookAuthor'] ?? $row['book_author'] ?? null,
                    'language'        => $row['language'] ?? 'en-US',
                    'speaker'         => $row['speaker']  ?? 'en-US-AriaNeural',
                    'engine'          => $row['engine']   ?? 'azure',
                    'speaker_style'   => $row['speakerStyle'] ?? null,
                    'expression_style'=> $row['expressionStyle'] ?? null,
                    'prosody_rate'    => $row['prosodyRate'] ?? 'medium',
                    'prosody_pitch'   => $row['prosodyPitch'] ?? 'medium',
                    'prosody_volume'  => $row['prosodyVolume'] ?? 'medium',
                ]
            );

            foreach (($row['chapters'] ?? []) as $i => $ch) {
                TtsAudiobookChapter::firstOrCreate(
                    ['audiobook_id' => $book->id, 'chapter_number' => $ch['chapterNumber'] ?? $ch['chapter_number'] ?? ($i + 1)],
                    [
                        'title'         => $ch['title']         ?? '',
                        'plain_content' => $ch['plainContent']  ?? $ch['plain_content'] ?? '',
                        'ssml_content'  => $ch['ssmlContent']   ?? $ch['ssml_content']  ?? '',
                        'audio_path'    => $ch['audioPath']     ?? $ch['audio_path']    ?? '',
                        'audio_url'     => $ch['audioUrl']      ?? $ch['audio_url']     ?? '',
                        'status'        => $ch['status']        ?? 'pending',
                    ]
                );
            }
        });
        $this->line(' Audiobooks imported (' . count($rows) . ')');
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function loadJson(string $file): array
    {
        $content = file_get_contents($file);
        $rows    = json_decode($content, true);
        if (!is_array($rows)) {
            $this->error("Invalid JSON in {$file}");
            return [];
        }
        return $rows;
    }

    private function extractId(array $row): ?string
    {
        if (isset($row['_id'])) {
            return is_array($row['_id']) ? ($row['_id']['$oid'] ?? null) : $row['_id'];
        }
        return null;
    }
}
