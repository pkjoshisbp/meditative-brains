<?php

/**
 * Builds a single, mobile-friendly combined HTML reader for each
 * "Practicing Happiness" edition.
 *
 * Why this exists:
 *   The Flutter app loads the ebook inside a WebView using loadHtmlString().
 *   The original index.html is a Table of Contents that links out to separate
 *   chapter-XX.html files. Those chapter links cannot be opened inside the
 *   WebView because the asset endpoint requires a Bearer auth token that the
 *   WebView cannot attach to navigation requests, so readers only ever saw the
 *   first (TOC) page.
 *
 *   This script inlines every chapter into ONE self-contained HTML document
 *   with an in-page anchor table of contents, so the whole book is readable
 *   inside the mobile reader without any cross-page navigation.
 */

$root = dirname(__DIR__);
$editions = [
    'US Edition'    => $root . '/ebook/practicing-happiness-us-edition',
    'India Edition' => $root . '/ebook/practicing-happiness-india-edition',
];

foreach ($editions as $label => $dir) {
    if (! is_dir($dir)) {
        fwrite(STDERR, "Missing edition directory: {$dir}\n");
        exit(1);
    }

    $html = buildCombinedReader($dir, $label);
    $output = $dir . '/practicing-happiness-complete-reader.html';
    file_put_contents($output, $html);
    echo "{$label}: {$output}\n";
}

function buildCombinedReader(string $dir, string $label): string
{
    // Collect every chapter page in natural order.
    $files = glob($dir . '/chapter-*.html');
    sort($files, SORT_NATURAL);

    $tocItems = [];
    $chapterBlocks = [];

    foreach ($files as $file) {
        [$number, $title, $subtitle] = chapterMeta($file);
        $anchor = 'chapter-' . $number;
        $content = extractPageContent(file_get_contents($file));

        // Drop the cross-page navigation footer (we use in-page anchors now).
        $content = preg_replace('~<div class="nav-links">.*?</div>~s', '', $content);
        // Drop the small "back to top" / page meta captions.
        $content = preg_replace('~<p style="text-align:center;font-size:13px;color:#aaa;.*?</p>~s', '', $content);

        $tocItems[] = sprintf(
            '<a href="#%s" class="toc-chapter"><span class="toc-num">%s</span>' .
            '<span class="toc-text"><span class="toc-title">%s</span>' .
            '<span class="toc-subtitle">%s</span></span></a>',
            htmlspecialchars($anchor, ENT_QUOTES),
            htmlspecialchars($number, ENT_QUOTES),
            htmlspecialchars($title, ENT_QUOTES),
            htmlspecialchars($subtitle, ENT_QUOTES)
        );

        $chapterBlocks[] = sprintf(
            '<section id="%s" class="chapter-section">%s</section>',
            htmlspecialchars($anchor, ENT_QUOTES),
            $content
        );
    }

    $coverBlock = file_exists($dir . '/cover.png')
        ? '<img src="cover.png" class="cover-image" alt="Practicing Happiness cover">'
        : '<div class="cover-fallback">
              <h1 class="cover-title">Practicing Happiness</h1>
              <p class="cover-subtitle">Rewiring Your Mind for a Life of Genuine, Lasting Joy</p>
              <p class="cover-meta">' . htmlspecialchars($label, ENT_QUOTES) . '<br>By Pawan Joshi</p>
           </div>';

    $tocHtml = implode("\n", $tocItems);
    $chaptersHtml = implode("\n", $chapterBlocks);

    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=4.0">
<title>Practicing Happiness - ' . htmlspecialchars($label, ENT_QUOTES) . '</title>
<style>
  :root { color-scheme: light dark; }
  * { box-sizing: border-box; }
  html { scroll-behavior: smooth; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
    background: #fdf8f2;
    color: #242424;
    margin: 0;
    padding: 0;
    line-height: 1.75;
    -webkit-text-size-adjust: 100%;
  }
  /* Single mobile-first column: minimal horizontal padding, full width text. */
  .reader-shell { width: 100%; max-width: 720px; margin: 0 auto; padding: 0 16px 64px; }

  /* Cover */
  .cover { text-align: center; padding: 28px 0 18px; border-bottom: 2px solid #c8a96e; margin-bottom: 18px; }
  .cover-fallback { padding-top: 24px; }
  .cover-image { max-width: 100%; height: auto; border-radius: 6px; }
  .cover-title { font-size: 26px; color: #1a1a2e; margin: 14px 0 8px; line-height: 1.2; }
  .cover-subtitle { font-size: 15px; color: #6b6b6b; font-style: italic; margin: 0 0 10px; }
  .cover-meta { font-size: 13px; color: #555; margin: 0; }

  /* Sticky top bar with back-to-contents link */
  .top-bar {
    position: sticky; top: 0; z-index: 10;
    background: rgba(253, 248, 242, 0.94);
    backdrop-filter: saturate(140%) blur(6px);
    -webkit-backdrop-filter: saturate(140%) blur(6px);
    border-bottom: 1px solid #ece0cf;
    padding: 8px 0;
    margin: 0 -16px 14px;
    text-align: center;
  }
  .top-bar a { color: #8a6a2e; text-decoration: none; font-size: 13px; font-weight: 600; }

  /* Table of contents */
  .toc { margin: 6px 0 22px; }
  .toc h2 { font-size: 15px; letter-spacing: 2px; text-transform: uppercase; color: #8a7355; margin: 4px 0 12px; }
  .toc-chapter {
    display: flex; gap: 12px; align-items: flex-start;
    text-decoration: none; color: inherit;
    background: #fff; border: 1px solid #ece0cf; border-radius: 8px;
    padding: 12px 12px; margin-bottom: 8px;
  }
  .toc-num {
    min-width: 34px; height: 34px; background: #1a1a2e; color: #c8a96e;
    border-radius: 6px; display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 14px; flex-shrink: 0;
  }
  .toc-text { display: flex; flex-direction: column; min-width: 0; }
  .toc-title { font-size: 15px; font-weight: 700; color: #1a1a2e; line-height: 1.3; }
  .toc-subtitle { font-size: 13px; color: #777; font-style: italic; line-height: 1.4; margin-top: 2px; }

  /* Chapter content */
  .chapter-section { padding: 6px 0 22px; border-bottom: 1px solid #efe3d0; }
  .chapter-section:last-child { border-bottom: none; }
  .book-header { text-align: center; border-bottom: 2px solid #c8a96e; padding-bottom: 16px; margin-bottom: 22px; }
  .book-title { font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #8a7355; }
  .chapter-number { font-size: 30px; font-weight: 300; color: #c8a96e; line-height: 1.1; }
  .chapter-title { font-size: 22px; font-weight: bold; color: #1a1a2e; margin: 4px 0; }
  .chapter-subtitle { font-size: 15px; color: #6b6b6b; font-style: italic; }

  p { font-size: 16.5px; margin: 0 0 14px; }
  h2 { font-size: 19px; color: #1a1a2e; margin: 24px 0 10px; border-left: 4px solid #c8a96e; padding-left: 10px; }
  h3 { font-size: 16px; color: #3a3a5c; margin: 20px 0 8px; }
  h4 { font-size: 14px; margin: 16px 0 8px; }

  blockquote { border-left: 4px solid #c8a96e; margin: 18px 0; padding: 12px 14px; background: #fffbf4; font-size: 15.5px; font-style: italic; color: #3a3a3a; }
  blockquote cite { display: block; margin-top: 6px; font-size: 12px; font-style: normal; color: #888; }

  .story-box, .science-box, .practice-box, .reflection-box, .chapter-summary, .culture-box, .conditioning-section, .comparison-col, .quote-section {
    border-radius: 8px; padding: 14px 14px; margin: 18px 0; page-break-inside: avoid;
  }
  .story-box { background: #f5ede0; border: 1px solid #d4a96a; }
  .science-box { background: #edf7ed; border-left: 4px solid #4caf50; }
  .practice-box { background: #f3eeff; border-left: 4px solid #7c4dff; }
  .reflection-box { background: #fffde7; border: 1px solid #f9d84a; }
  .chapter-summary { background: #f0f4ff; border: 1px solid #b0c0e8; }
  .culture-box { background: #e8eeff; }
  .conditioning-section { background: #1a1a2e; color: #f5e6c8; }
  .conditioning-section h2, .conditioning-section p, .conditioning-section li { color: #f5e6c8; }

  .insight-pull { text-align: center; font-size: 16px; font-style: italic; color: #5a3e1b; border-top: 1px solid #c8a96e; border-bottom: 1px solid #c8a96e; padding: 12px; margin: 18px 0; }

  img { max-width: 100%; height: auto; }
  figure { margin: 18px 0; text-align: center; }
  figcaption { font-size: 12px; color: #777; font-style: italic; margin-top: 4px; }
  ul, ol { margin: 8px 0 14px; padding-left: 22px; }
  li { margin-bottom: 6px; font-size: 16px; }
  a { color: #006b5c; }
  .nav-links { display: none; }
  .begin-cta { display: none; }

  .chapter-grid, .comparison-grid { display: block; }
  .chapter-card { display: block; }

  /* Dark mode for the reader */
  @media (prefers-color-scheme: dark) {
    body { background: #0b1610; color: #edf4ef; }
    .top-bar { background: rgba(11, 22, 16, 0.94); border-bottom-color: #1f3328; }
    .top-bar a { color: #d4b87e; }
    .cover-title { color: #ffffff; }
    .cover-subtitle { color: #b9c8c0; }
    .cover-meta { color: #a9bdb2; }
    .toc-chapter { background: #11201a; border-color: #1f3328; }
    .toc-title { color: #ffffff; }
    .toc-subtitle { color: #9fb6a8; }
    .chapter-section { border-bottom-color: #1f3328; }
    .chapter-title { color: #ffffff; }
    h2, h3 { color: #ffffff; }
    blockquote { background: #11201a; color: #dbe7e0; }
    .story-box { background: #11201a; }
    a { color: #73dfcf; }
  }

  /* Very small phones: tighten everything further. */
  @media (max-width: 360px) {
    .reader-shell { padding: 0 12px 56px; }
    p { font-size: 16px; }
    .chapter-title { font-size: 20px; }
  }
</style>
</head>
<body>
  <div class="reader-shell">
    <div class="top-bar"><a href="#contents">↑ Table of Contents</a></div>

    <header class="cover">' . $coverBlock . '</header>

    <nav class="toc" id="contents">
      <h2>Table of Contents</h2>
      ' . $tocHtml . '
    </nav>

    ' . $chaptersHtml . '

    <p style="text-align:center;font-size:12px;color:#999;margin-top:28px;">© 2026 Pawan Joshi · ' . htmlspecialchars($label, ENT_QUOTES) . '</p>
  </div>
</body>
</html>';
}

/**
 * Pull the inner content of a chapter/contents HTML page.
 * Mirrors the extractor used by the PDF builder.
 */
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

/**
 * Derive chapter number, title and subtitle from a chapter file.
 */
function chapterMeta(string $file): array
{
    $html = file_get_contents($file);

    $number = '';
    if (preg_match('~<div class="chapter-number">\s*(.*?)\s*</div>~s', $html, $m)) {
        $number = trim(strip_tags($m[1]));
    } elseif (preg_match('~Chapter\s*(\d+)~i', $html, $m)) {
        $number = $m[1];
    } elseif (preg_match('~/chapter-(\d+)-~i', $file, $m)) {
        $number = $m[1];
    }

    $title = '';
    if (preg_match('~<div class="chapter-title">\s*(.*?)\s*</div>~s', $html, $m)) {
        $title = trim(strip_tags($m[1]));
    } elseif (preg_match('~<title>(.*?)</title>~s', $html, $m)) {
        $title = trim($m[1]);
    }

    $subtitle = '';
    if (preg_match('~<div class="chapter-subtitle">\s*(.*?)\s*</div>~s', $html, $m)) {
        $subtitle = trim(strip_tags($m[1]));
    }

    return [$number, $title, $subtitle];
}
