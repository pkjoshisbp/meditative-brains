<?php

/**
 * Generates a mobile-optimized PDF for each "Practicing Happiness" edition.
 *
 * The standard PDF (A4) wastes horizontal space on a phone and forces a lot of
 * pinch-to-zoom. This version uses a tall, narrow page that closely matches a
 * phone screen, with tight margins, so the reader can read it at full screen
 * width with minimal horizontal scrolling.
 *
 * Output: "<edition dir>/Practicing Happiness - Mobile.pdf"
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$root = dirname(__DIR__);
$editions = [
    'US Edition'    => $root . '/ebook/practicing-happiness-us-edition',
    'India Edition' => $root . '/ebook/practicing-happiness-india-edition',
];

// PDFs are stored in private (non-web) storage so they can only be served
// through the authenticated signed-URL download flow.
$privatePdfDir = $root . '/storage/app/private/pdfs';
if (! is_dir($privatePdfDir)) {
    mkdir($privatePdfDir, 0775, true);
}

// Tall, narrow page roughly matching a phone screen at a comfortable print size.
// 78mm wide x 148mm tall keeps the text column narrow on a mobile display.
define('MOBILE_PAGE_WIDTH_MM', 78);
define('MOBILE_PAGE_HEIGHT_MM', 148);

foreach ($editions as $label => $dir) {
    if (! is_dir($dir)) {
        fwrite(STDERR, "Missing edition directory: {$dir}\n");
        exit(1);
    }

    $html = buildMobileBookHtml($dir, $label);

    $editionOutDir = $privatePdfDir . '/' . basename($dir);
    if (! is_dir($editionOutDir)) {
        mkdir($editionOutDir, 0775, true);
    }
    $output = $editionOutDir . '/Practicing Happiness - Mobile.pdf';

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('chroot', $dir);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->setPaper(
        [0, 0, mmToPt(MOBILE_PAGE_WIDTH_MM), mmToPt(MOBILE_PAGE_HEIGHT_MM)],
        'portrait'
    );
    $dompdf->setBasePath($dir . DIRECTORY_SEPARATOR);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->render();

    file_put_contents($output, $dompdf->output());
    echo "{$label}: {$output}\n";
}

function mmToPt(float $mm): float
{
    // 1 inch = 25.4 mm = 72 pt
    return $mm * 72.0 / 25.4;
}

function buildMobileBookHtml(string $dir, string $label): string
{
    $files = glob($dir . '/chapter-*.html');
    sort($files, SORT_NATURAL);

    $chapters = [];
    foreach ($files as $file) {
        $content = extractPageContent(file_get_contents($file));
        $content = preg_replace('~<div class="nav-links">.*?</div>~s', '', $content);
        $content = preg_replace('~<p style="text-align:center;font-size:13px;color:#aaa;.*?</p>~s', '', $content);
        $chapters[] = '<section class="chapter-page">' . $content . '</section>';
    }

    $coverContent = file_exists($dir . '/cover.png')
        ? '<img src="cover.png" class="cover-image" alt="Practicing Happiness cover">'
        : '<div class="cover-fallback">
    <h1 class="cover-title">Practicing Happiness</h1>
    <p class="cover-subtitle">Rewiring Your Mind for a Life of Genuine, Lasting Joy</p>
    <p class="cover-meta">' . htmlspecialchars($label, ENT_QUOTES) . '<br>By Pawan Joshi</p>
    </div>';

    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Practicing Happiness - ' . htmlspecialchars($label, ENT_QUOTES) . ' (Mobile)</title>
<style>
/* Tight margins so the text column nearly fills a phone screen. */
@page { margin: 6mm 5mm; }
@page:first { margin: 0; }
body {
  font-family: DejaVu Sans, sans-serif;
  color: #222;
  background: #fff;
  line-height: 1.5;
  font-size: 11px;
  margin: 0;
}
.cover {
  text-align: center;
  page-break-after: always;
  padding: 18mm 8mm 0;
}
.cover-fallback { padding-top: 30mm; }
.cover-image {
  width: 100%;
  max-width: 64mm;
  margin: 0 auto 8mm;
}
.cover-title { font-size: 19px; color: #1a1a2e; margin: 0 0 5px; line-height: 1.15; }
.cover-subtitle { font-size: 12px; color: #8a7355; margin: 0 0 8px; font-style: italic; }
.cover-meta { font-size: 10px; color: #555; }
.book-header {
  text-align: center;
  border-bottom: 1.5px solid #c8a96e;
  padding-bottom: 5px;
  margin-bottom: 8px;
}
.book-title { font-size: 7px; letter-spacing: 1.2px; text-transform: uppercase; color: #8a7355; }
.chapter-number { font-size: 20px; color: #c8a96e; line-height: 1.05; }
.chapter-title { font-size: 14px; font-weight: bold; color: #1a1a2e; margin: 2px 0; }
.chapter-subtitle { font-size: 9.5px; color: #666; font-style: italic; }
h1, h2, h3, h4, h5 { color: #1a1a2e; page-break-after: avoid; break-after: avoid-page; }
h2 { font-size: 12px; margin-top: 10px; margin-bottom: 5px; border-left: 3px solid #c8a96e; padding-left: 5px; }
h3 { font-size: 11px; margin-top: 8px; margin-bottom: 4px; }
h4 { font-size: 10px; margin-top: 7px; margin-bottom: 3px; text-transform: uppercase; letter-spacing: 0.8px; }
h2 + p, h2 + blockquote, h2 + ul, h2 + ol, h2 + div,
h3 + p, h3 + blockquote, h3 + ul, h3 + ol, h3 + div,
h4 + p, h4 + blockquote, h4 + ul, h4 + ol, h4 + div {
  page-break-before: avoid;
  break-before: avoid-page;
}
p { margin: 0 0 6px; }
blockquote {
  border-left: 3px solid #c8a96e;
  background: #fffbf4;
  margin: 8px 0;
  padding: 5px 7px;
  font-style: italic;
}
blockquote cite { display: block; margin-top: 4px; font-size: 8px; color: #777; }
.story-box, .reflection-box, .chapter-summary, .culture-box,
.science-box, .practice-box, .conditioning-section, .objection-box,
.comparison-col, .quote-section {
  border: 1px solid #d7c6a8;
  padding: 6px 7px;
  margin: 8px 0;
  page-break-inside: avoid;
  break-inside: avoid-page;
}
.science-box { background: #edf7ed; border-left: 3px solid #4caf50; }
.practice-box { background: #f3eeff; border-left: 3px solid #7c4dff; }
.conditioning-section { background: #1a1a2e; color: #f5e6c8; }
.conditioning-section h2, .conditioning-section p, .conditioning-section li { color: #f5e6c8; }
.chapter-summary { background: #f0f4ff; }
.reflection-box { background: #fffde7; }
.culture-box { background: #e8eeff; }
.insight-pull {
  text-align: center;
  font-style: italic;
  color: #5a3e1b;
  border-top: 1px solid #c8a96e;
  border-bottom: 1px solid #c8a96e;
  padding: 6px;
  margin: 8px 0;
}
figure { margin: 8px 0; text-align: center; page-break-inside: avoid; }
img { max-width: 100%; height: auto; }
figcaption { font-size: 8px; color: #777; font-style: italic; margin-top: 3px; }
.chapter-page { page-break-before: always; }
.chapter-grid, .comparison-grid { display: block; }
.chapter-card {
  display: block;
  color: #222;
  text-decoration: none;
  border: 1px solid #ddd;
  padding: 5px;
  margin: 4px 0;
  page-break-inside: avoid;
  break-inside: avoid-page;
}
.begin-cta, .nav-links { display: none; }
ul, ol { margin-top: 4px; margin-bottom: 6px; padding-left: 14px; }
li { margin-bottom: 3px; }
</style>
</head>
<body>
  <section class="cover">
    ' . $coverContent . '
  </section>
  ' . implode("\n", $chapters) . '
</body>
</html>';
}

function extractPageContent(string $html): string
{
    if (preg_match('~<div class="page-wrapper">(.*)</div>\s*</body>~s', $html, $matches)) {
        return $matches[1];
    }
    if (preg_match('~<body[^>]*>(.*)</body>~s', $html, $matches)) {
        return $matches[1];
    }
    return $html;
}
