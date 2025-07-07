<?php

declare(strict_types=1);

/**
 * Stage 1: Asset & HTML Fetcher (Throttled Version)
 * - Fetches channel HTML pages in throttled, parallel batches to avoid rate-limiting.
 * - Sleeps between batches to be a "polite" scraper.
 * - Caches raw HTML and processes assets as before.
 */

// --- Setup ---
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/functions.php';

// --- Configuration Constants ---
const INPUT_FILE = __DIR__ . '/channelsData/channelsAssets.json';
const FINAL_ASSETS_DIR = __DIR__ . '/channelsData';
const TEMP_BUILD_DIR = __DIR__ . '/temp_build';
const HTML_CACHE_DIR = TEMP_BUILD_DIR . '/html_cache';
const LOGOS_DIR_NAME = 'logos';
const GITHUB_LOGO_BASE_URL = 'https://raw.githubusercontent.com/yebekhe/TVC/main/channelsData/logos';
const ALL_POSSIBLE_TYPES = ['vmess', 'vless', 'trojan', 'ss', 'tuic', 'hy2', 'hysteria'];

// ** NEW THROTTLING CONFIGURATION **
const BATCH_SIZE = 50; // Number of channels to fetch in one parallel batch.
const SLEEP_BETWEEN_BATCHES = 5; // Seconds to wait between batches.

// --- 1. Initial Checks and Setup ---

echo "--- STAGE 1: ASSET & HTML FETCHER (THROTTLED) ---" . PHP_EOL;
echo "1. Initializing and loading source data..." . PHP_EOL;

if (!file_exists(INPUT_FILE)) {
    die('Error: channelsAssets.json not found.' . PHP_EOL);
}
$sourcesData = json_decode(file_get_contents(INPUT_FILE), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die('Error: Failed to decode channelsAssets.json.' . PHP_EOL);
}
$sourcesToProcess = array_keys($sourcesData);

if (is_dir(TEMP_BUILD_DIR)) {
    deleteFolder(TEMP_BUILD_DIR);
}
mkdir(HTML_CACHE_DIR, 0775, true);
mkdir(TEMP_BUILD_DIR . '/' . LOGOS_DIR_NAME, 0775, true);

// --- 2. Fetch All Channel HTML Pages in Throttled Batches ---

echo "2. Fetching all channel HTML pages in batches of " . BATCH_SIZE . "..." . PHP_EOL;

// Split the list of sources into smaller chunks (batches)
$sourceBatches = array_chunk($sourcesToProcess, BATCH_SIZE);
$fetched_html_data = [];
$totalBatches = count($sourceBatches);

foreach ($sourceBatches as $batchIndex => $batch) {
    echo "--> Fetching HTML batch " . ($batchIndex + 1) . " of {$totalBatches}..." . PHP_EOL;
    
    $urls_in_batch = [];
    foreach ($batch as $source) {
        $urls_in_batch[$source] = "https://t.me/s/" . $source;
    }
    
    $batch_results = fetch_multiple_urls_parallel($urls_in_batch);
    // Use the + operator to merge associative arrays, which is efficient and preserves keys.
    $fetched_html_data += $batch_results; 

    // Sleep after fetching a batch, but not after the very last one.
    if (($batchIndex + 1) < $totalBatches) {
        echo "--> Batch complete. Sleeping for " . SLEEP_BETWEEN_BATCHES . " seconds to avoid rate-limiting..." . PHP_EOL;
        sleep(SLEEP_BETWEEN_BATCHES);
    }
}
echo "\nFinished fetching all HTML pages. Total successful fetches: " . count($fetched_html_data) . PHP_EOL;

// --- 3. Save HTML to Cache and Process Assets ---
// This part of the logic remains the same, it just operates on the fully populated $fetched_html_data array.
echo "\n3. Caching HTML and processing assets..." . PHP_EOL;

$channelArray = [];
$logo_urls_to_fetch = [];
$processedCount = 0;
$totalSources = count($sourcesToProcess);
foreach ($sourcesToProcess as $source) {
    print_progress(++$processedCount, $totalSources, 'Processing:');
    if (!isset($fetched_html_data[$source]) || empty($fetched_html_data[$source])) {
        $channelArray[$source] = $sourcesData[$source]; // Carry over old data on failure
        continue;
    }
    $html = $fetched_html_data[$source];
    file_put_contents(HTML_CACHE_DIR . '/' . $source . '.html', $html);
    $foundTypes = [];
    foreach (ALL_POSSIBLE_TYPES as $type) {
        if (str_contains($html, "{$type}://")) {
            $foundTypes[] = $type;
        }
    }
    $channelArray[$source]['types'] = $foundTypes;
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

// --- 4. Fetch All Logo Images in Throttled Batches ---
// The same batching logic is applied here for fetching logos.

if (!empty($logo_urls_to_fetch)) {
    echo "\n4. Fetching " . count($logo_urls_to_fetch) . " logo images in batches..." . PHP_EOL;
    
    // Chunk the associative array, preserving keys.
    $logoBatches = array_chunk($logo_urls_to_fetch, BATCH_SIZE, true);
    $fetched_logo_data = [];
    $totalLogoBatches = count($logoBatches);

    foreach ($logoBatches as $batchIndex => $batch) {
        echo "--> Fetching logo batch " . ($batchIndex + 1) . " of {$totalLogoBatches}..." . PHP_EOL;
        $batch_results = fetch_multiple_urls_parallel($batch);
        $fetched_logo_data += $batch_results;

        if (($batchIndex + 1) < $totalLogoBatches) {
            echo "--> Batch complete. Sleeping for " . SLEEP_BETWEEN_BATCHES . " seconds..." . PHP_EOL;
            sleep(SLEEP_BETWEEN_BATCHES);
        }
    }

    foreach ($fetched_logo_data as $source => $imageData) {
        file_put_contents(TEMP_BUILD_DIR . '/' . LOGOS_DIR_NAME . '/' . $source . '.jpg', $imageData);
    }
    echo "\nLogo downloads complete." . PHP_EOL;
} else {
    echo "\n4. No new logos to fetch." . PHP_EOL;
}

// --- 5. Finalize, Write JSON, and Perform Atomic Swap ---
// This final part is unchanged.
echo "\n5. Writing new assets file and swapping directories..." . PHP_EOL;
$jsonOutput = json_encode($channelArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents(TEMP_BUILD_DIR . '/channelsAssets.json', $jsonOutput);
if (is_dir(FINAL_ASSETS_DIR)) {
    deleteFolder(FINAL_ASSETS_DIR);
}
rename(TEMP_BUILD_DIR, FINAL_ASSETS_DIR);
echo "Done! Channel assets and HTML cache have been successfully updated." . PHP_EOL;
