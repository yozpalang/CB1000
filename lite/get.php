<?php

declare(strict_types=1);

/**
 * Stage 2: Config Extractor
 * - Reads channel data and cached HTML from Stage 1.
 * - Extracts proxy configs from the cached HTML files.
 * - Processes, enriches, and saves the final subscription files.
 */

// --- Setup ---
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require 'functions.php';

// --- Configuration Constants ---
const ASSETS_FILE = __DIR__ . '/channelsData/channelsAssets.json';
const HTML_CACHE_DIR = __DIR__ . '/channelsData/html_cache';
const OUTPUT_DIR = __DIR__ . '/subscriptions';
const LOCATION_DIR = OUTPUT_DIR . '/location';
const FINAL_CONFIG_FILE = __DIR__ . '/config.txt';
const CONFIGS_TO_PROCESS_PER_SOURCE = 2; // Process the latest 2 configs from each source.

// --- 1. Load Source Data and Sanity Check ---

echo "--- STAGE 2: CONFIG EXTRACTOR ---" . PHP_EOL;
echo "1. Loading source list from assets file..." . PHP_EOL;

if (!file_exists(ASSETS_FILE)) {
    die("Error: channelsAssets.json not found. Please run the assets script first." . PHP_EOL);
}
if (!is_dir(HTML_CACHE_DIR)) {
    die("Error: HTML cache directory not found. Please run the assets script first." . PHP_EOL);
}

$sourcesArray = json_decode(file_get_contents(ASSETS_FILE), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error: Invalid JSON in assets file." . PHP_EOL);
}

// --- 2. Extract Configs from Cached HTML Files ---

echo "2. Extracting configs from local HTML cache..." . PHP_EOL;
$configsList = [];
$totalSources = count($sourcesArray);
$sourceCounter = 0;

foreach ($sourcesArray as $source => $sourceData) {
    print_progress(++$sourceCounter, $totalSources, 'Extracting:');
    
    $htmlFile = HTML_CACHE_DIR . '/' . $source . '.html';
    if (!file_exists($htmlFile)) {
        continue; // Skip if no cached HTML exists for this source
    }

    $htmlContent = file_get_contents($htmlFile);
    if (empty($htmlContent)) {
        continue;
    }

    $types = $sourceData['types'] ?? [];
    if (empty($types)) {
        continue;
    }

    $typePattern = implode('|', array_map('preg_quote', $types, ['/']));
    $extractedLinks = extractLinksByType($htmlContent, $typePattern);

    if (!empty($extractedLinks)) {
        $configsList[$source] = array_values(array_unique($extractedLinks));
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
