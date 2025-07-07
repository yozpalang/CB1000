<?php

declare(strict_types=1);

/**
 * Stage 1: Asset & HTML Fetcher
 * - Fetches channel HTML pages in parallel.
 * - Stores the raw HTML to a temporary cache.
 * - Processes the HTML to update channel assets (title, logo, types).
 */

// --- Setup ---
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/functions.php';

// --- Configuration Constants ---
const INPUT_FILE = __DIR__ . '/channelsAssets.json';
const FINAL_ASSETS_DIR = __DIR__ . '/channelsData';
const TEMP_BUILD_DIR = __DIR__ . '/temp_build'; // A single temp dir for all artifacts
const HTML_CACHE_DIR = TEMP_BUILD_DIR . '/html_cache';
const LOGOS_DIR_NAME = 'logos';
const GITHUB_LOGO_BASE_URL = 'https://raw.githubusercontent.com/yebekhe/TVC/main/channelsData/logos';
const ALL_POSSIBLE_TYPES = ['vmess', 'vless', 'trojan', 'ss', 'tuic', 'hy2', 'hysteria'];

// --- 1. Initial Checks and Setup ---

echo "--- STAGE 1: ASSET & HTML FETCHER ---" . PHP_EOL;
echo "1. Initializing and loading source data..." . PHP_EOL;

if (!file_exists(INPUT_FILE)) {
    die('Error: channelsAssets.json not found.' . PHP_EOL);
}
$sourcesData = json_decode(file_get_contents(INPUT_FILE), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die('Error: Failed to decode channelsAssets.json.' . PHP_EOL);
}
$sourcesToProcess = array_keys($sourcesData);

// Atomic operation setup: build everything in a temp directory first.
if (is_dir(TEMP_BUILD_DIR)) {
    deleteFolder(TEMP_BUILD_DIR);
}
mkdir(HTML_CACHE_DIR, 0775, true);
mkdir(TEMP_BUILD_DIR . '/' . LOGOS_DIR_NAME, 0775, true);

// --- 2. Fetch All Channel HTML Pages in Parallel ---

echo "2. Fetching all channel HTML pages in parallel..." . PHP_EOL;
$urls_to_fetch_html = [];
foreach ($sourcesToProcess as $source) {
    // We only need the first page for this process.
    $urls_to_fetch_html[$source] = "https://t.me/s/" . $source;
}
$fetched_html_data = fetch_multiple_urls_parallel($urls_to_fetch_html);
echo "\nFetched " . count($fetched_html_data) . " pages successfully." . PHP_EOL;

// --- 3. Save HTML to Cache and Process Assets ---

echo "3. Caching HTML and processing assets..." . PHP_EOL;

$channelArray = [];
$logo_urls_to_fetch = [];
$totalSources = count($sourcesToProcess);
$processedCount = 0;

foreach ($sourcesToProcess as $source) {
    print_progress(++$processedCount, $totalSources, 'Processing:');

    if (!isset($fetched_html_data[$source]) || empty($fetched_html_data[$source])) {
        echo "\nWarning: No HTML content for '{$source}'. Carrying over old data." . PHP_EOL;
        $channelArray[$source] = $sourcesData[$source];
        continue;
    }

    $html = $fetched_html_data[$source];
    
    // **CRITICAL: Save the fetched HTML to the cache for the next script.**
    file_put_contents(HTML_CACHE_DIR . '/' . $source . '.html', $html);

    // Dynamic Type Discovery
    $foundTypes = [];
    foreach (ALL_POSSIBLE_TYPES as $type) {
        if (str_contains($html, "{$type}://")) {
            $foundTypes[] = $type;
        }
    }
    $channelArray[$source]['types'] = $foundTypes;

    // Asset Extraction
    preg_match('#<meta property="twitter:title" content="(.*?)">#', $html, $title_match);
    preg_match('#<meta property="twitter:image" content="(.*?)">#', $html, $image_match);
    
    $channelArray[$source]['title'] = $title_match[1] ?? 'Unknown Title';
    
    if (isset($image_match[1]) && !empty($image_match[1])) {
        $logo_urls_to_fetch[$source] = $image_match[1];
        $channelArray[$source]['logo'] = GITHUB_LOGO_BASE_URL . '/' . $source . ".jpg";
    } else {
        $channelArray[$source]['logo'] = '';
    }
}
echo PHP_EOL;

// --- 4. Fetch All Logo Images in Parallel ---

if (!empty($logo_urls_to_fetch)) {
    echo "4. Fetching " . count($logo_urls_to_fetch) . " logo images in parallel..." . PHP_EOL;
    $fetched_logo_data = fetch_multiple_urls_parallel($logo_urls_to_fetch);
    foreach ($fetched_logo_data as $source => $imageData) {
        file_put_contents(TEMP_BUILD_DIR . '/' . LOGOS_DIR_NAME . '/' . $source . '.jpg', $imageData);
    }
    echo "\nLogo downloads complete." . PHP_EOL;
} else {
    echo "4. No new logos to fetch." . PHP_EOL;
}

// --- 5. Finalize, Write JSON, and Perform Atomic Swap ---

echo "5. Writing new assets file and swapping directories..." . PHP_EOL;

$jsonOutput = json_encode($channelArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents(TEMP_BUILD_DIR . '/channelsAssets.json', $jsonOutput);

if (is_dir(FINAL_ASSETS_DIR)) {
    deleteFolder(FINAL_ASSETS_DIR);
}
// Rename the entire temp directory to become the new final directory.
// This also moves the html_cache inside channelsData.
rename(TEMP_BUILD_DIR, FINAL_ASSETS_DIR);

echo "Done! Channel assets and HTML cache have been successfully updated." . PHP_EOL;

// Helper functions (print_progress) should be in functions.php
