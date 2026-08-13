<?php

namespace App\Console\Commands;

use App\Http\Controllers\SitemapController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate {--path= : Output path. Defaults to public/sitemap.xml}';

    protected $description = 'Generate a static sitemap.xml file from the application sitemap controller';

    public function handle(SitemapController $sitemapController): int
    {
        $outputPath = $this->option('path') ?: public_path('sitemap.xml');
        $response = $sitemapController();
        $content = $response->getContent();

        if (!is_string($content) || trim($content) === '') {
            $this->error('Sitemap content was empty.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($outputPath));
        File::put($outputPath, $content);

        $this->info('Sitemap generated: ' . $outputPath);

        return self::SUCCESS;
    }
}
