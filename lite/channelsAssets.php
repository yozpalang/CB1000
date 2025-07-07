<?php

declare(strict_types=1);

/**
 * This script updates channel assets (title, logo) and dynamically discovers
 * the types of proxy configurations (vmess, vless, etc.) present in each channel.
 * It performs all network requests in parallel for maximum speed and uses an atomic
 * write method to ensure data integrity.
 */

// --- Setup ---
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/functions.php';

// --- Configuration Constants ---
const INPUT_FILE = __DIR__ . '/channelsData/channelsAssets.json';
const FINAL_DIR = __DIR__ . '/channelsData';
const TEMP_DIR = __DIR__ . '/channelsData_temp';
const LOGOS_DIR_NAME = 'logos';
const GITHUB_LOGO_BASE_URL = 'https://raw.githubusercontent.com/yebekhe/TVC/main/channelsData/logos';

// Define all possible protocol types that the script should look for.
const ALL_POSSIBLE_TYPES = [
    'vmess', 'vless', 'trojan', 'ss', 'tuic', 'hy2', 'hysteria'
];


// #############################################################################
// Helper Functions (assuming fetch_multiple_urls_parallel and deleteFolder are in functions.php)
// #############################################################################

// --- 1. Initial Checks and Setup ---

echo "1. Initializing and loading source data..." . PHP_EOL;

if (!file_exists(INPUT_FILE)) {
    die('Error: channelsAssets.json not found.' . PHP_EOL);
}

$sourcesData = json_decode(file_get_contents(INPUT_FILE), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die('Error: Failed to decode channelsAssets.json. Invalid JSON.' . PHP_EOL);
}
// Get just the source names (the keys) from the input file
$sourcesToProcess = array_keys($sourcesData);

if (is_dir(TEMP_DIR)) {
    deleteFolder(TEMP_DIR);
}
mkdir(TEMP_DIR . '/' . LOGOS_DIR_NAME, 0775, true);


// --- 2. Fetch All Channel HTML Pages in Parallel ---

echo "2. Fetching all channel HTML pages in parallel..." . PHP_EOL;

$urls_to_fetch_html = [];
foreach ($sourcesToProcess as $source) {
    $urls_to_fetch_html[$source] = "https://t.me/s/" . $source;
}

$fetched_html_data = fetch_multiple_urls_parallel($urls_to_fetch_html);


// --- 3. Parse HTML, Discover Types, and Prepare Logo URLs ---

echo "\n3. Parsing HTML, discovering config types, and preparing logo download list..." . PHP_EOL;

$channelArray = [];
$logo_urls_to_fetch = [];
$totalSources = count($sourcesToProcess);
$processedCount = 0;

foreach ($sourcesToProcess as $source) {
    print_progress(++$processedCount, $totalSources, 'Processing:');

    // Check if HTML for this source was successfully fetched
    if (!isset($fetched_html_data[$source])) {
        // If fetch failed, carry over old data as a fallback
        echo "\nWarning: Failed to fetch HTML for '{$source}'. Carrying over old data." . PHP_EOL;
        $channelArray[$source] = $sourcesData[$source];
        continue;
    }

    $html = $fetched_html_data[$source];
    
    // ** DYNAMIC TYPE DISCOVERY LOGIC **
    $foundTypes = [];
    foreach (ALL_POSSIBLE_TYPES as $type) {
        // A simple string search is very fast and effective for this purpose.
        if (str_contains($html, "{$type}://")) {
            $foundTypes[] = $type;
        }
    }
    // If no types are found, we can assign an empty array or handle as needed.
    $channelArray[$source]['types'] = $foundTypes;

    // --- Asset (Title & Logo) Extraction Logic (remains the same) ---
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
        file_put_contents(TEMP_DIR . '/' . LOGOS_DIR_NAME . '/' . $source . '.jpg', $imageData);
    }
    echo "\nLogo downloads complete." . PHP_EOL;
} else {
    echo "4. No new logos to fetch." . PHP_EOL;
}


// --- 5. Finalize, Write JSON, and Perform Atomic Swap ---

echo "5. Finalizing data and writing output files..." . PHP_EOL;

$jsonOutput = json_encode($channelArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents(TEMP_DIR . '/channelsAssets.json', $jsonOutput);

if (is_dir(FINAL_DIR)) {
    deleteFolder(FINAL_DIR);
}
rename(TEMP_DIR, FINAL_DIR);

echo "Done! Channel assets have been successfully updated with dynamically discovered types." . PHP_EOL;
