<?php

declare(strict_types=1);

/**
 * This script deduplicates proxy configurations from a file.
 * It works by stripping the unique name/ID from each config, using the "bare" config as a key
 * to find duplicates, and then rebuilding the final list with the name of the *first* occurrence
 * of each unique config.
 * Finally, it generates various subscription and API files.
 */

// --- Setup ---
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Ensure the optimized functions.php is used
require_once __DIR__ . '/functions.php';

// --- Configuration Constants ---
const CONFIG_FILE = __DIR__ . '/config.txt';
const CHANNELS_ASSETS_FILE = __DIR__ . '/channelsData/channelsAssets.json';
const API_DIR = __DIR__ . '/api';
const API_OUTPUT_FILE = API_DIR . '/allConfigs.json';
const SUBS_DIR = __DIR__ . '/subscriptions/xray';

// Mapping of config types to their respective name/hash fields.
const CONFIG_NAME_FIELDS = [
    'vmess' => 'ps',
    'vless' => 'hash',
    'trojan' => 'hash',
    'tuic' => 'hash',
    'hy2' => 'hash',
    'ss' => 'name',
];


// --- 1. Load Input Files ---

echo "1. Loading input files..." . PHP_EOL;

if (!file_exists(CONFIG_FILE)) {
    die('Error: config.txt not found. Please run the fetch script first.' . PHP_EOL);
}
if (!file_exists(CHANNELS_ASSETS_FILE)) {
    die('Error: channelsAssets.json not found.' . PHP_EOL);
}

// Read the config file and filter out any empty lines.
$configsArray = file(CONFIG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$channelsAssets = json_decode(file_get_contents(CHANNELS_ASSETS_FILE), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die('Error: Failed to decode channelsAssets.json. Invalid JSON.' . PHP_EOL);
}

$initialConfigCount = count($configsArray);
echo "Loaded {$initialConfigCount} configs to process." . PHP_EOL;


// --- 2. Deduplicate Configs using a Hash Map (O(n) Performance) ---

echo "2. Deduplicating configs..." . PHP_EOL;

// This is the core optimization. We use an associative array where:
// Key: The "bare" config string (without its unique name).
// Value: The original name of the *first* time we saw this config.
$uniqueConfigs = [];

foreach ($configsArray as $config) {
    $configType = detect_type($config);

    // Skip if the config type is unknown or not in our map
    if ($configType === null || !isset(CONFIG_NAME_FIELDS[$configType])) {
        continue;
    }

    $decodedConfig = configParse($config);
    if ($decodedConfig === null) {
        continue;
    }

    $nameField = CONFIG_NAME_FIELDS[$configType];
    $originalName = $decodedConfig[$nameField] ?? '';

    // Create the "bare" config by removing its unique name
    unset($decodedConfig[$nameField]);
    $bareConfig = reparseConfig($decodedConfig, $configType);
    if ($bareConfig === null) {
        continue;
    }

    // If we haven't seen this bare config before, store it with its original name.
    // This is an O(1) operation, much faster than in_array().
    if (!isset($uniqueConfigs[$bareConfig])) {
        $uniqueConfigs[$bareConfig] = $originalName;
    }
}

$uniqueConfigCount = count($uniqueConfigs);
echo "Deduplication complete. Found {$uniqueConfigCount} unique configs." . PHP_EOL;


// --- 3. Rebuild Final Configs and API Data ---

echo "3. Rebuilding final config list and API data..." . PHP_EOL;

$finalOutput = [];
$configsFullData = [];

foreach ($uniqueConfigs as $bareConfig => $originalName) {
    $configType = detect_type($bareConfig);
    if ($configType === null) continue;

    $decodedConfig = configParse($bareConfig);
    if ($decodedConfig === null) continue;

    // Add the original name back to the config
    $nameField = CONFIG_NAME_FIELDS[$configType];
    $decodedConfig[$nameField] = $originalName;

    $finalConfig = reparseConfig($decodedConfig, $configType);
    if ($finalConfig === null) continue;

    $finalOutput[] = $finalConfig;

    // Split the name string by the pipe character
    $nameParts = explode('|', $originalName);
    
    // Safely extract channel username. The format is:
    // [0] Flag + Location
    // [1] Status Icon
    // [2] Type
    // [3] @Source
    // [4] Key
    // We trim each part to remove leading/trailing spaces.
    $sourceUsername = 'unknown'; // Default value
    if (isset($nameParts[3])) {
        // Get the part containing the username (e.g., " @abiidar_server ")
        $usernamePart = trim($nameParts[3]);
        // Remove the leading '@' symbol
        $sourceUsername = ltrim($usernamePart, '@');
    }
    
    // Retrieve channel info using the corrected username, with a fallback.
    $channelInfo = $channelsAssets[$sourceUsername] ?? ['title' => 'Unknown Channel', 'logo' => ''];

    // Determine if the config is of type 'reality'
    $effectiveType = ($configType === 'vless' && is_reality($finalConfig)) ? 'reality' : $configType;
    
    $configsFullData[] = [
        'channel' => [
            'username' => $sourceUsername,
            'title' => $channelInfo['title'],
            'logo' => $channelInfo['logo'],
        ],
        'type' => $effectiveType,
        'config' => $finalConfig,
    ];
}


// --- 4. Write Output Files ---

echo "4. Writing all output files..." . PHP_EOL;

// Write back to the main config file
file_put_contents(CONFIG_FILE, implode(PHP_EOL, $finalOutput));

// Prepare Hiddify subscription files
$hiddifyContent = hiddifyHeader("PSG | MIX") . implode(PHP_EOL, $finalOutput);
$hiddifyBase64Content = base64_encode($hiddifyContent);

// Ensure subscription directories exist
if (!is_dir(SUBS_DIR . '/normal')) {
    mkdir(SUBS_DIR . '/normal', 0775, true);
}
if (!is_dir(SUBS_DIR . '/base64')) {
    mkdir(SUBS_DIR . '/base64', 0775, true);
}

file_put_contents(SUBS_DIR . '/normal/mix', $hiddifyContent);
file_put_contents(SUBS_DIR . '/base64/mix', $hiddifyBase64Content);

// Prepare and write the JSON API file
if (!is_dir(API_DIR)) {
    mkdir(API_DIR, 0775, true);
}
$jsonOutput = json_encode($configsFullData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents(API_OUTPUT_FILE, $jsonOutput);

echo "Done! Processed {$initialConfigCount} configs and saved {$uniqueConfigCount} unique configs." . PHP_EOL;
