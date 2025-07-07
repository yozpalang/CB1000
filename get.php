<?php

declare(strict_types=1);

// Enable error reporting for debugging
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// It's crucial that the optimized functions.php from the previous answer is used.
require 'functions.php';

// --- Configuration Constants ---
const API_URL = __DIR__ . '/channelsData/channelsAssets.json';
const OUTPUT_DIR = __DIR__ . '/subscriptions';
const LOCATION_DIR = OUTPUT_DIR . '/location';
const FINAL_CONFIG_FILE = __DIR__ . '/config.txt';
const CONFIGS_TO_PROCESS_PER_SOURCE = 40; // Process the latest 40 configs from each source

// --- Helper Functions ---



// --- Main Script Logic ---

echo "1. Fetching source list from API..." . PHP_EOL;
$sourcesJson = @file_get_contents(API_URL);
if ($sourcesJson === false) {
    die("Error: Could not fetch the source list from " . API_URL . PHP_EOL);
}
$sourcesArray = json_decode($sourcesJson, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error: Invalid JSON received from source API." . PHP_EOL);
}

echo "2. Fetching all channel data in parallel..." . PHP_EOL;
$urls_to_fetch = [];
foreach ($sourcesArray as $source => $data) {
    $urls_to_fetch[$source] = "https://t.me/s/" . $source;
}
$fetched_data = fetch_multiple_urls_parallel($urls_to_fetch);

echo "\n3. Extracting configs from fetched data..." . PHP_EOL;
$configsList = [];
$totalSources = count($sourcesArray);
$tempCounter = 0;
foreach ($sourcesArray as $source => $data) {
    $types = $data['types'];
    print_progress(++$tempCounter, $totalSources, 'Extracting:');
    if (isset($fetched_data[$source])) {
        $typePattern = implode('|', array_map('preg_quote', $types, ['/']));
        $tempExtract = extractLinksByType($fetched_data[$source], $typePattern);
        if (!empty($tempExtract)) {
            $configsList[$source] = $tempExtract;
        }
    }
}
echo PHP_EOL . "Extraction complete. Found configs from " . count($configsList) . " sources." . PHP_EOL;


echo "4. Processing and enriching all configs..." . PHP_EOL;

// This cache will store IP info to avoid repeated API calls for the same IP.
$ipInfoCache = [];
$finalOutput = [];
$locationBased = [];

// Mapping of config types to their respective IP/Host and Name/Hash fields.
$configFields = [
    'vmess' => ['ip' => 'add', 'name' => 'ps'],
    'vless' => ['ip' => 'hostname', 'name' => 'hash'],
    'trojan' => ['ip' => 'hostname', 'name' => 'hash'],
    'tuic' => ['ip' => 'hostname', 'name' => 'hash'],
    'hy2' => ['ip' => 'hostname', 'name' => 'hash'],
    'ss' => ['ip' => 'server_address', 'name' => 'name'],
];

$totalConfigsToProcess = 0;
foreach ($configsList as $configs) {
    $totalConfigsToProcess += min(count($configs), CONFIGS_TO_PROCESS_PER_SOURCE);
}
$processedCount = 0;

foreach ($configsList as $source => $configs) {
    // Process only the latest N configs using array_slice. More efficient than array_reverse.
    $configsToProcess = array_slice($configs, -CONFIGS_TO_PROCESS_PER_SOURCE);
    $key_offset = count($configs) - count($configsToProcess);

    foreach ($configsToProcess as $key => $config) {
        print_progress(++$processedCount, $totalConfigsToProcess, 'Processing:');
        
        $config = explode('<', $config, 2)[0]; // Sanitize config string
        if (!is_valid($config)) {
            continue;
        }

        $type = detect_type($config);
        if ($type === null || !isset($configFields[$type])) {
            continue;
        }

        $decodedConfig = configParse($config);
        if ($decodedConfig === null) {
            continue;
        }
        
        // Skip invalid SS configs (empty method/password)
        if ($type === 'ss' && (empty($decodedConfig['encryption_method']) || empty($decodedConfig['password']))) {
            continue;
        }

        $ipField = $configFields[$type]['ip'];
        $nameField = $configFields[$type]['name'];
        $ipOrHost = $decodedConfig[$ipField] ?? null;

        if ($ipOrHost === null) {
            continue;
        }
        
        // ** CRITICAL OPTIMIZATION: Use cache for IP info **
        if (!isset($ipInfoCache[$ipOrHost])) {
            $info = ip_info($ipOrHost);
            $ipInfoCache[$ipOrHost] = $info ? $info->country : 'XX';
        }
        $countryCode = $ipInfoCache[$ipOrHost];

        $flag = ($countryCode === 'XX') ? '❔' : (($countryCode === 'CF') ? '🚩' : getFlags($countryCode));
        $encryptionStatus = isEncrypted($config) ? '🟢' : '🔴';
        
        $newName = sprintf(
            '%s %s | %s | %s | @%s | %d',
            $flag,
            $countryCode,
            $encryptionStatus,
            $type,
            $source,
            ($key + $key_offset)
        );
        $decodedConfig[$nameField] = $newName;
        
        $encodedConfig = reparseConfig($decodedConfig, $type);
        if ($encodedConfig === null) continue;

        // Clean up potential encoding artifacts
        $cleanConfig = str_replace('amp%3B', '', $encodedConfig);

        $finalOutput[] = $cleanConfig;
        $locationBased[$countryCode][] = $cleanConfig;
    }
}

echo PHP_EOL . "Processing complete." . PHP_EOL;

echo "5. Writing subscription files..." . PHP_EOL;

// Clean up old directories and create new ones
if (is_dir(LOCATION_DIR)) {
    deleteFolder(LOCATION_DIR);
}
mkdir(LOCATION_DIR . '/normal', 0775, true);
mkdir(LOCATION_DIR . '/base64', 0775, true);

foreach ($locationBased as $location => $configs) {
    // If the location key is empty or just whitespace, skip this iteration.
    if (empty(trim($location))) {
        continue;
    }
    $plainText = implode(PHP_EOL, $configs);
    file_put_contents(LOCATION_DIR . '/normal/' . $location, $plainText);
    file_put_contents(LOCATION_DIR . '/base64/' . $location, base64_encode($plainText));
}

// Write the final combined config file
file_put_contents(FINAL_CONFIG_FILE, implode(PHP_EOL, $finalOutput));

echo "Done! All files have been generated successfully." . PHP_EOL;
