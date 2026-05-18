<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\TtsAudioProduct;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $staticUrls = collect([
            $this->makeEntry(route('home'), now(), 'daily', '1.0'),
            $this->makeEntry(route('products'), now(), 'daily', '0.9'),
            $this->makeEntry(route('subscription'), now(), 'weekly', '0.8'),
            $this->makeEntry(route('about'), now(), 'monthly', '0.7'),
            $this->makeEntry(route('blog'), now(), 'weekly', '0.8'),
            $this->makeEntry(route('contact'), now(), 'monthly', '0.7'),
            $this->makeEntry(route('audio.catalog'), now(), 'weekly', '0.8'),
            $this->makeEntry(route('legal.terms'), now(), 'yearly', '0.3'),
            $this->makeEntry(route('legal.privacy'), now(), 'yearly', '0.3'),
            $this->makeEntry(route('legal.refund'), now(), 'yearly', '0.3'),
        ]);

        $blogUrls = collect(require resource_path('views/pages/blog/posts.php'))
            ->map(function (array $post): array {
                return $this->makeEntry(
                    route('blog.show', ['slug' => $post['slug']]),
                    $this->resolveBlogLastModified($post['date'] ?? null),
                    'monthly',
                    '0.7'
                );
            });

        $productUrls = Product::query()
            ->active()
            ->whereNotNull('slug')
            ->get(['slug', 'updated_at'])
            ->map(fn (Product $product): array => $this->makeEntry(
                route('products.show', ['slug' => $product->slug]),
                $product->updated_at,
                'weekly',
                '0.7'
            ));

        $audioExperienceUrls = TtsAudioProduct::query()
            ->active()
            ->whereNotNull('slug')
            ->get(['slug', 'updated_at'])
            ->map(fn (TtsAudioProduct $product): array => $this->makeEntry(
                route('audio.detail', ['slug' => $product->slug]),
                $product->updated_at,
                'weekly',
                '0.7'
            ));

        $items = $staticUrls
            ->concat($blogUrls)
            ->concat($productUrls)
            ->concat($audioExperienceUrls)
            ->unique('loc')
            ->values();

        return response()
            ->view('sitemap', ['items' => $items], 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function makeEntry(string $loc, CarbonInterface|string|null $lastmod, string $changefreq, string $priority): array
    {
        $timestamp = $lastmod instanceof CarbonInterface
            ? $lastmod
            : now()->parse($lastmod ?? 'now');

        return [
            'loc' => $loc,
            'lastmod' => $timestamp->toAtomString(),
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    }

    private function resolveBlogLastModified(?string $date): CarbonInterface
    {
        if (!$date) {
            return now();
        }

        try {
            return now()->parse($date);
        } catch (\Throwable) {
            return now();
        }
    }
}