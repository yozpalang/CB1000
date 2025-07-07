<?php

declare(strict_types=1);

/**
 * This script reads a list of proxy configurations, sorts them by protocol type,
 * and generates separate subscription files for each type, including a special
 * category for "reality" configs.
 */

// --- Setup ---
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Ensure the optimized functions.php is available
require_once __DIR__ . '/functions.php';

// --- Configuration Constants ---
const CONFIG_FILE = __DIR__ . '/config.txt';
const SUBS_DIR_NORMAL = __DIR__ . '/subscriptions/xray/normal';
const SUBS_DIR_BASE64 = __DIR__ . '/subscriptions/xray/base64';


// --- 1. Load Input File ---

echo "1. Loading configurations from " . basename(CONFIG_FILE) . "..." . PHP_EOL;

if (!file_exists(CONFIG_FILE)) {
    die('Error: config.txt not found. Please run the previous scripts first.' . PHP_EOL);
}

// Use file() to read into an array directly. It's clean and efficient.
// The flags automatically skip empty lines and trim newlines from the end of each line.
$configsArray = file(CONFIG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

if (empty($configsArray)) {
    die('Warning: config.txt is empty. No files will be generated.' . PHP_EOL);
}

echo "Loaded " . count($configsArray) . " configs." . PHP_EOL;


// --- 2. Sort Configurations into Groups ---

echo "2. Sorting configs by type..." . PHP_EOL;

$sortedConfigs = [];

foreach ($configsArray as $config) {
    $configType = detect_type($config);

    // Skip any malformed or unknown lines
    if ($configType === null) {
        continue;
    }

    // Add the config to its primary type group
    // The urldecode is kept as it was in the original script's intent
    $sortedConfigs[$configType][] = urldecode($config);

    // **Optimization**: Only check for 'reality' if the type is 'vless'.
    // This avoids running the is_reality() function on every single config.
    if ($configType === 'vless' && is_reality($config)) {
        $sortedConfigs['reality'][] = urldecode($config);
    }
}

echo "Sorting complete. Found " . count($sortedConfigs) . " unique types." . PHP_EOL;


// --- 3. Write Subscription Files ---

echo "3. Writing subscription files..." . PHP_EOL;

// **Robustness**: Ensure the output directories exist, create them if they don't.
if (!is_dir(SUBS_DIR_NORMAL)) {
    mkdir(SUBS_DIR_NORMAL, 0775, true);
}
if (!is_dir(SUBS_DIR_BASE64)) {
    mkdir(SUBS_DIR_BASE64, 0775, true);
}

$filesWritten = 0;
foreach ($sortedConfigs as $type => $configs) {
    // Combine the configs with the appropriate Hiddify header
    $header = hiddifyHeader("TVC | " . strtoupper($type));
    $plainTextContent = $header . implode(PHP_EOL, $configs);
    $base64Content = base64_encode($plainTextContent);

    // Define file paths
    $normalFilePath = SUBS_DIR_NORMAL . '/' . $type;
    $base64FilePath = SUBS_DIR_BASE64 . '/' . $type;

    // Write both the plain text and Base64 encoded files
    file_put_contents($normalFilePath, $plainTextContent);
    file_put_contents($base64FilePath, $base64Content);
    
    $filesWritten++;
}

echo "Done! Wrote subscription files for {$filesWritten} types." . PHP_EOL;