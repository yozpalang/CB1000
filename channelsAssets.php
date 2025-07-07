<?php

declare(strict_types=1);

/**
 * This script updates channel assets (title, logo) by scraping public Telegram pages.
 * It performs all network requests in parallel for maximum speed and uses an atomic
 * write method to ensure data integrity.
 */

// --- Setup ---
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// This script needs the parallel fetching function from our previous optimizations.
require_once __DIR__ . '/functions.php';

// --- Configuration Constants ---
const INPUT_FILE = __DIR__ . '/channelsAssets.json';
const FINAL_DIR = __DIR__ . '/channelsData';
const TEMP_DIR = __DIR__ . '/channelsData_temp'; // Temporary directory for atomic writes
const LOGOS_DIR_NAME = 'logos'; // The name of the subdirectory for logos
const GITHUB_LOGO_BASE_URL = 'https://raw.githubusercontent.com/itsyebekhe/PSG/main/channelsData/logos';

// --- 1. Initial Checks and Setup ---

echo "1. Initializing and loading source data..." . PHP_EOL;

if (!file_exists(INPUT_FILE)) {
    die('Error: channelsAssets.json not found.' . PHP_EOL);
}

$sourcesArray = json_decode(file_get_contents(INPUT_FILE), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die('Error: Failed to decode channelsAssets.json. Invalid JSON.' . PHP_EOL);
}

// Clean up any old temp directory in case of a previously failed run.
if (is_dir(TEMP_DIR)) {
    deleteFolder(TEMP_DIR);
}
// Create the temporary directory structure.
mkdir(TEMP_DIR . '/' . LOGOS_DIR_NAME, 0775, true);


// --- 2. Fetch All Channel HTML Pages in Parallel ---

echo "2. Fetching all channel HTML pages in parallel..." . PHP_EOL;

$urls_to_fetch_html = [];
foreach ($sourcesArray as $source => $data) {
    $urls_to_fetch_html[$source] = "https://t.me/s/" . $source;
}

$fetched_html_data = fetch_multiple_urls_parallel($urls_to_fetch_html);


// --- 3. Parse HTML, Prepare Logo URLs, and Build Channel Data ---

echo "\n3. Parsing HTML and preparing logo download list..." . PHP_EOL;

$channelArray = [];
$logo_urls_to_fetch = [];
$totalSources = count($sourcesArray);
$processedCount = 0;

foreach ($sourcesArray as $source => $data) {
    print_progress(++$processedCount, $totalSources, 'Parsing:');

    // Use original types from input file
    $channelArray[$source]['types'] = $data['types'];

    // Check if HTML for this source was successfully fetched
    if (!isset($fetched_html_data[$source])) {
        // Set default values if fetch failed
        $channelArray[$source]['title'] = $data['title'] ?? 'Unknown (Fetch Failed)';
        $channelArray[$source]['logo'] = $data['logo'] ?? '';
        continue;
    }

    $html = $fetched_html_data[$source];
    
    // Extract title and image URL using regular expressions
    preg_match('#<meta property="twitter:title" content="(.*?)">#', $html, $title_match);
    preg_match('#<meta property="twitter:image" content="(.*?)">#', $html, $image_match);

    // Set title with a fallback
    $channelArray[$source]['title'] = $title_match[1] ?? 'Unknown Title';
    
    // Set the final logo URL and add the real URL to our download queue
    if (isset($image_match[1]) && !empty($image_match[1])) {
        $logo_urls_to_fetch[$source] = $image_match[1];
        $channelArray[$source]['logo'] = GITHUB_LOGO_BASE_URL . '/' . $source . ".jpg";
    } else {
        $channelArray[$source]['logo'] = ''; // No logo found
    }
}
echo PHP_EOL;

// --- 4. Fetch All Logo Images in Parallel ---

if (!empty($logo_urls_to_fetch)) {
    echo "4. Fetching " . count($logo_urls_to_fetch) . " logo images in parallel..." . PHP_EOL;
    $fetched_logo_data = fetch_multiple_urls_parallel($logo_urls_to_fetch);

    // Save the downloaded logos to the temporary directory
    foreach ($fetched_logo_data as $source => $imageData) {
        file_put_contents(TEMP_DIR . '/' . LOGOS_DIR_NAME . '/' . $source . '.jpg', $imageData);
    }
    echo "\nLogo downloads complete." . PHP_EOL;
} else {
    echo "4. No new logos to fetch." . PHP_EOL;
}


// --- 5. Finalize, Write JSON, and Perform Atomic Swap ---

echo "5. Finalizing data and writing output files..." . PHP_EOL;

// Save the new channel data array to the temporary directory
$jsonOutput = json_encode($channelArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents(TEMP_DIR . '/channelsAssets.json', $jsonOutput);

// **Atomic Operation**: Replace the old directory with the new one.
// This ensures that we don't end up with a corrupt state if the script fails.
if (is_dir(FINAL_DIR)) {
    deleteFolder(FINAL_DIR);
}
rename(TEMP_DIR, FINAL_DIR);

echo "Done! Channel assets have been successfully updated." . PHP_EOL;

/**
 * A helper function to print a clean, overwriting progress bar.
 * (This could also live in your functions.php)
 */
function print_progress(int $current, int $total, string $message = ''): void
{
    if ($total == 0) return;
    $percentage = ($current / $total) * 100;
    $bar_length = 50;
    $filled_length = (int)($bar_length * $current / $total);
    $bar = str_repeat('=', $filled_length) . str_repeat(' ', $bar_length - $filled_length);
    printf("\r%s [%s] %d%% (%d/%d)", $message, $bar, $percentage, $current, $total);
}
