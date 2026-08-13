<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$root = dirname(__DIR__);
$editions = [
    'US Edition' => $root . '/ebook/practicing-happiness-us-edition',
    'India Edition' => $root . '/ebook/practicing-happiness-india-edition',
];

foreach ($editions as $label => $dir) {
    if (!is_dir($dir)) {
        fwrite(STDERR, "Missing edition directory: {$dir}\n");
        exit(1);
    }

    $html = buildBookHtml($dir, $label);
    $output = $dir . '/Practicing Happiness - Complete Book.pdf';

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('chroot', $dir);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->setBasePath($dir . DIRECTORY_SEPARATOR);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->render();

    file_put_contents($output, $dompdf->output());
    echo "{$label}: {$output}\n";
}

function buildBookHtml(string $dir, string $label): string
{
    $files = glob($dir . '/chapter-*.html');
    sort($files, SORT_NATURAL);

    $toc = file_exists($dir . '/index.html') ? extractPageContent(file_get_contents($dir . '/index.html')) : '';
    $chapters = [];

    foreach ($files as $file) {
        $content = extractPageContent(file_get_contents($file));
        $content = preg_replace('~<div class="nav-links">.*?</div>~s', '', $content);
        $content = preg_replace('~<p style="text-align:center;font-size:13px;color:#aaa;.*?</p>~s', '', $content);
        $chapters[] = '<section class="chapter-page">' . $content . '</section>';
    }

    $coverContent = file_exists($dir . '/cover.png')
        ? '<img src="cover.png" class="cover-image cover-image-full" alt="Practicing Happiness cover">'
        : '<div class="cover-fallback">
    <h1 class="cover-title">Practicing Happiness</h1>
    <p class="cover-subtitle">Rewiring Your Mind for a Life of Genuine, Lasting Joy</p>
    <p class="cover-meta">' . htmlspecialchars($label, ENT_QUOTES) . '<br>By Pawan Joshi</p>
    </div>';

    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Practicing Happiness - ' . htmlspecialchars($label, ENT_QUOTES) . '</title>
<style>
@page { margin: 54px 48px; }
@page:first { margin: 0; }
body {
  font-family: DejaVu Sans, sans-serif;
  color: #222;
  background: #fff;
  line-height: 1.62;
  font-size: 13px;
}
.cover {
  text-align: center;
  page-break-after: always;
  padding: 0;
}
.cover-fallback {
  padding-top: 60px;
}
.cover-image {
  max-width: 360px;
  max-height: 360px;
  margin: 0 auto 36px;
}
.cover-image-full {
  display: block;
  width: 210mm;
  height: 297mm;
  max-width: none;
  max-height: none;
  margin: 0;
}
.cover-title {
  font-size: 34px;
  color: #1a1a2e;
  margin: 0 0 12px;
}
.cover-subtitle {
  font-size: 18px;
  color: #8a7355;
  margin: 0 0 18px;
}
.cover-meta {
  font-size: 14px;
  color: #555;
}
.page-wrapper {
  max-width: none;
  margin: 0;
  padding: 0;
}
.book-header {
  text-align: center;
  border-bottom: 2px solid #c8a96e;
  padding-bottom: 18px;
  margin-bottom: 28px;
}
.book-title {
  font-size: 10px;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: #8a7355;
}
.chapter-number {
  font-size: 34px;
  color: #c8a96e;
  line-height: 1.1;
}
.chapter-title {
  font-size: 24px;
  font-weight: bold;
  color: #1a1a2e;
}
.chapter-subtitle {
  font-size: 14px;
  color: #666;
  font-style: italic;
}
h1, h2, h3, h4, h5 {
  color: #1a1a2e;
  page-break-after: avoid;
  break-after: avoid-page;
}
h2 {
  font-size: 18px;
  margin-top: 28px;
  margin-bottom: 10px;
  border-left: 4px solid #c8a96e;
  padding-left: 10px;
}
h3 { font-size: 15px; margin-top: 20px; }
h2 + p, h2 + blockquote, h2 + ul, h2 + ol, h2 + div,
h3 + p, h3 + blockquote, h3 + ul, h3 + ol, h3 + div,
h4 + p, h4 + blockquote, h4 + ul, h4 + ol, h4 + div {
  page-break-before: avoid;
  break-before: avoid-page;
}
p { margin: 0 0 11px; }
blockquote {
  border-left: 4px solid #c8a96e;
  background: #fffbf4;
  margin: 22px 0;
  padding: 12px 16px;
  font-style: italic;
}
blockquote cite {
  display: block;
  margin-top: 8px;
  font-size: 11px;
  color: #777;
}
.story-box, .reflection-box, .chapter-summary, .culture-box,
.science-box, .practice-box, .conditioning-section, .objection-box,
.comparison-col, .quote-section {
  border: 1px solid #d7c6a8;
  padding: 14px 16px;
  margin: 20px 0;
  background: #fbf8f1;
  page-break-inside: avoid;
  break-inside: avoid-page;
}
.science-box { background: #edf7ed; border-left: 4px solid #4caf50; }
.practice-box { background: #f3eeff; border-left: 4px solid #7c4dff; }
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
  padding: 14px;
  margin: 22px 0;
}
figure { margin: 22px 0; text-align: center; page-break-inside: avoid; }
img { max-width: 100%; height: auto; }
figcaption { font-size: 10px; color: #777; font-style: italic; margin-top: 6px; }
.chapter-page { page-break-before: always; }
.toc-page { page-break-after: always; }
.chapter-grid, .comparison-grid { display: block; }
.chapter-card {
  display: block;
  color: #222;
  text-decoration: none;
  border: 1px solid #ddd;
  padding: 12px;
  margin: 8px 0;
  page-break-inside: avoid;
  break-inside: avoid-page;
}
.begin-cta, .nav-links { display: none; }
ul, ol { margin-top: 8px; }
li { margin-bottom: 6px; }
</style>
</head>
<body>
  <section class="cover">
    ' . $coverContent . '
  </section>
  <section class="toc-page">' . $toc . '</section>
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
