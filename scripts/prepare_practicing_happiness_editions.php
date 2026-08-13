<?php

$root = dirname(__DIR__);
$editions = [
    'us' => $root . '/ebook/practicing-happiness-us-edition',
    'india' => $root . '/ebook/practicing-happiness-india-edition',
];

$imageMap = [
    'morning-smile-calm' => 'morning-smile-calm.jpg',
    'inner-peace-stillness' => 'inner-peace-stillness.jpg',
    'forest-path-light' => 'forest-path-light.jpg',
    'meditation-morning-light' => 'meditation-morning-light.jpg',
    'pottery-clay-hands' => 'pottery-clay-hands.jpg',
    'breathing-calm-window' => 'breathing-calm-window.jpg',
    'piano-practice-focus' => 'piano-practice-focus.jpg',
    'open-horizon-journey' => 'open-horizon-journey.jpg',
    'mountain-strength-resilience' => 'mountain-strength-resilience.jpg',
    'kintsugi-golden-ceramic' => 'kintsugi-golden-ceramic.jpg',
    'morning-tea-journal' => 'morning-tea-journal.jpg',
    'athlete-practice-mastery' => 'athlete-practice-mastery.jpg',
    'work-focus-calm' => 'work-focus-calm.jpg',
    'dawn-horizon-beginning' => 'dawn-horizon-beginning.jpg',
];

$indiaReplacements = [
    'US Edition' => 'India Edition',
    'Ling' => 'Ananya',
    "Ling's" => "Ananya's",
    'Marcus Aurelius' => 'Marcus Aurelius',
    'Marcus had done' => 'Rohan had done',
    'Marcus asked' => 'Rohan asked',
    'Marcus sat' => 'Rohan sat',
    'Marcus was experiencing' => 'Rohan was experiencing',
    'Marcus wasn\'t' => 'Rohan wasn\'t',
    'Marcus' => 'Rohan',
    'Dr. Priya Chen' => 'Dr. Priya Menon',
    'Dr. Priya was' => 'Dr. Priya was',
    'Priya Chen' => 'Priya Menon',
    'Sarah' => 'Meera',
    "Sarah's" => "Meera's",
    'Clara' => 'Kavita',
    "Clara's" => "Kavita's",
    'Omar' => 'Arjun',
    "Omar's" => "Arjun's",
    'accounting firm' => 'finance office',
    'technology company' => 'technology services company',
    'owned two homes' => 'owned a flat and a family home',
    'owned two properties' => 'owned a flat and a family home',
    'drove a car he had dreamed about at twenty' => 'drove the car he had once promised himself he would buy',
    'called a mechanic, and took a bus' => 'called a mechanic, and took an auto to the bus stop',
    "took an auto to the bus stop she hadn't ridden in years" => "took a bus she hadn't ridden in years",
    'At a stop near a small park' => 'At a stop near a small neighborhood park',
    'small neighborhood park' => 'small neighbourhood park',
    'in the cold' => 'in the morning rush',
    'kettle boiled' => 'tea boiled',
    'morning coffee' => 'morning tea',
    'professional dinner' => 'professional conference dinner',
    'community health programme' => 'community wellness programme',
    'neighborhood' => 'neighbourhood',
    'program' => 'programme',
    'programs' => 'programmes',
    'recognize' => 'recognise',
    'normalizing' => 'normalising',
    'normalizes' => 'normalises',
    'skeptical' => 'sceptical',
];

foreach ($editions as $edition => $dir) {
    if (!is_dir($dir)) {
        fwrite(STDERR, "Missing edition directory: {$dir}\n");
        exit(1);
    }

    localizeImages($dir, $imageMap);
    normalizeRateTags($dir);
}

localizeIndiaEdition($editions['india'], $indiaReplacements);

foreach ($editions as $edition => $dir) {
    addEditionLabels($dir, $edition === 'india' ? 'India Edition' : 'US Edition');
    rebuildTtsAggregateFiles($dir);
}

function localizeImages(string $dir, array $imageMap): void
{
    $imageDir = $dir . '/assets/images';
    if (!is_dir($imageDir)) {
        mkdir($imageDir, 0775, true);
    }

    foreach ($imageMap as $seed => $filename) {
        $target = $imageDir . '/' . $filename;
        if (!is_file($target) || filesize($target) === 0) {
            $url = "https://picsum.photos/seed/{$seed}/800/450";
            $data = @file_get_contents($url);
            if ($data === false) {
                fwrite(STDERR, "Could not download {$url}\n");
                continue;
            }
            file_put_contents($target, $data);
        }
    }

    foreach (glob($dir . '/*.html') as $file) {
        $html = file_get_contents($file);
        $html = preg_replace_callback(
            '~src="https://picsum\.photos/seed/([^/]+)/800/450"~',
            function (array $matches) use ($imageMap): string {
                $filename = $imageMap[$matches[1]] ?? ($matches[1] . '.jpg');
                return 'src="assets/images/' . $filename . '"';
            },
            $html
        );
        file_put_contents($file, $html);
    }
}

function normalizeRateTags(string $dir): void
{
    foreach (array_merge(glob($dir . '/tts/*.txt'), glob($dir . '/*.txt')) as $file) {
        $text = file_get_contents($file);
        $text = str_replace(['[slow]', '[/slow]', '[rate:"slow"]'], ['[rate:"-5%"]', '[/rate]', '[rate:"-5%"]'], $text);
        $text = preg_replace('~<prosody\s+rate=["\']slow["\']>(.*?)</prosody>~is', '[rate:"-5%"]$1[/rate]', $text);
        file_put_contents($file, $text);
    }
}

function localizeIndiaEdition(string $dir, array $replacements): void
{
    $files = array_merge(
        glob($dir . '/*.html'),
        glob($dir . '/*.md'),
        glob($dir . '/*.txt'),
        glob($dir . '/tts/*.txt')
    );

    foreach ($files as $file) {
        $text = file_get_contents($file);
        $text = strtr($text, $replacements);
        file_put_contents($file, $text);
    }
}

function addEditionLabels(string $dir, string $label): void
{
    foreach (glob($dir . '/*.html') as $file) {
        $html = file_get_contents($file);
        $html = str_replace('Practicing Happiness</div>', "Practicing Happiness | {$label}</div>", $html);
        $html = str_replace('<title>Practicing Happiness', "<title>Practicing Happiness - {$label}", $html);
        $html = str_replace('— Practicing Happiness</title>', "- {$label}</title>", $html);
        file_put_contents($file, $html);
    }
}

function rebuildTtsAggregateFiles(string $dir): void
{
    $ttsFiles = glob($dir . '/tts/*.txt');
    sort($ttsFiles, SORT_NATURAL);

    $ssml = [];
    foreach ($ttsFiles as $file) {
        $ssml[] = trim(file_get_contents($file));
    }
    $ssmlText = implode("\n\n", $ssml) . "\n";
    file_put_contents($dir . '/Practicing Happiness - Audiobook Admin SSML Import.txt', $ssmlText);

    $plain = stripNarrationTags($ssmlText);
    file_put_contents($dir . '/Practicing Happiness - Complete Book Plain Text.txt', $plain);
}

function stripNarrationTags(string $text): string
{
    $text = preg_replace('~\[(?:pause|silence):[^\]]+\]~', '', $text);
    $text = preg_replace('~\[/?(?:personality|rate)(?::[^\]]+)?\]~', '', $text);
    $text = preg_replace('~<[^>]+>~', '', $text);
    $text = str_replace(['**', '*'], '', $text);
    $text = preg_replace("~[ \t]+\n~", "\n", $text);
    $text = preg_replace("~[ \t]{2,}~", " ", $text);
    $text = preg_replace("~\n{3,}~", "\n\n", $text);

    return trim($text) . "\n";
}
