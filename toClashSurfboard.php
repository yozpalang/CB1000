<?php

declare(strict_types=1);

// --- Setup ---
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/functions.php'; // Includes ConfigWrapper
// Re-use the ConfigWrapper from the sing-box optimization. If not present, add it here or in functions.php
if (!class_exists('ConfigWrapper')) { die('Error: ConfigWrapper class not found. Please include it from previous optimizations.'); }

// --- Configuration Constants ---
const INPUT_DIR = __DIR__ . '/subscriptions/xray/base64';
const OUTPUT_DIR_BASE = __DIR__ . '/subscriptions';
const TEMPLATES_DIR = __DIR__ . '/templates';
const GITHUB_BASE_URL = 'https://raw.githubusercontent.com/itsyebekhe/PSG/main';

const ALLOWED_SS_METHODS = ["chacha20-ietf-poly1305", "aes-256-gcm"];
// Define which input files can be converted to which output formats
const OUTPUT_MAPPING = [
    'clash' => ['mix', 'vmess', 'trojan', 'ss'],
    'meta' => ['mix', 'vmess', 'vless', 'reality', 'trojan', 'ss'],
    'surfboard' => ['mix', 'vmess', 'trojan', 'ss'],
];

// #############################################################################
// Universal Data Converters (Input -> Generic PHP Array)
// #############################################################################

function vmessToProxyData(ConfigWrapper $c): ?array {
    if (!is_valid_uuid($c->getUuid())) return null;
    $proxy = [
        "name" => $c->getTag(), "type" => "vmess", "server" => $c->getServer(), "port" => $c->getPort(),
        "cipher" => $c->get('scy', 'auto'), "uuid" => $c->getUuid(), "alterId" => $c->get('aid', 0),
        "tls" => $c->get('tls') === 'tls', "skip-cert-verify" => true, "network" => $c->get('net', 'tcp'),
    ];
    if ($proxy['network'] === "ws") {
        $proxy["ws-opts"] = ["path" => $c->getPath(), "headers" => ["Host" => $c->get('host', $c->getServer())]];
    } elseif ($proxy['network'] === "grpc") {
        $proxy["grpc-opts"] = ["grpc-service-name" => $c->getServiceName(), "grpc-mode" => $c->get('type')];
        $proxy["tls"] = true;
    }
    return $proxy;
}

function vlessToProxyData(ConfigWrapper $c): ?array {
    if (!is_valid_uuid($c->getUuid())) return null;
    $proxy = [
        "name" => $c->getTag(), "type" => "vless", "server" => $c->getServer(), "port" => $c->getPort(),
        "uuid" => $c->getUuid(), "tls" => $c->getParam('security') === 'tls', "network" => $c->getParam('type', 'tcp'),
        "client-fingerprint" => "chrome", "udp" => true,
    ];
    if ($c->getParam('sni')) $proxy["servername"] = $c->getParam('sni');
    if ($c->getParam('flow')) $proxy["flow"] = 'xtls-rprx-vision';
    if ($proxy['network'] === "ws") {
        $proxy["ws-opts"] = ["path" => $c->getPath(), "headers" => ["Host" => $c->getParam('host', $c->getServer())]];
    } elseif ($proxy['network'] === "grpc" && $c->getParam('serviceName')) {
        $proxy["grpc-opts"] = ["grpc-service-name" => $c->getParam('serviceName')];
        $proxy["tls"] = true;
    }
    if ($c->getParam('security') === 'reality') {
        if (in_array(strtolower($c->getParam('fp', '')), ["android", "ios", "random"])) return null;
        $proxy["tls"] = true;
        $proxy["client-fingerprint"] = $c->getParam('fp');
        $proxy["reality-opts"] = ["public-key" => $c->getParam('pbk')];
        if ($c->getParam('sid')) $proxy["reality-opts"]["short-id"] = $c->getParam('sid');
    }
    return $proxy;
}

function trojanToProxyData(ConfigWrapper $c): ?array {
    return [
        "name" => $c->getTag(), "type" => "trojan", "server" => $c->getServer(), "port" => $c->getPort(),
        "password" => $c->getPassword(), "skip-cert-verify" => (bool)$c->getParam("allowInsecure", false),
    ];
}

function ssToProxyData(ConfigWrapper $c): ?array {
    $method = $c->get('encryption_method');
    if (!in_array($method, ALLOWED_SS_METHODS)) return null;
    return [
        "name" => $c->getTag(), "type" => "ss", "server" => $c->getServer(), "port" => $c->getPort(),
        "password" => $c->getPassword(), "cipher" => $method,
    ];
}


// #############################################################################
// Profile Generator Classes
// #############################################################################

abstract class ProfileGenerator {
    protected array $proxies = [];
    protected array $proxyNames = [];
    public function addProxy(array $proxyData): void {
        $formattedProxy = $this->formatProxy($proxyData);
        if ($formattedProxy) {
            $this->proxies[] = $formattedProxy;
            $this->proxyNames[] = $proxyData['name'];
        }
    }
    abstract protected function formatProxy(array $proxyData): ?string;
    abstract public function generate(): string;
}

class ClashProfile extends ProfileGenerator {
    private string $type; // 'clash' or 'meta'
    public function __construct(string $type) { $this->type = $type; }
    protected function formatProxy(array $proxyData): ?string {
        if ($this->type === 'clash' && $proxyData['type'] === 'vless') return null;
        return '  - ' . json_encode($proxyData, JSON_UNESCAPED_UNICODE);
    }
    public function generate(): string {
        $template = file_get_contents(TEMPLATES_DIR . '/clash.yaml');
        $proxies_yaml = implode("\n", $this->proxies);
        $proxy_names_yaml = "    - " . implode("\n    - ", array_map(fn($n) => "'$n'", $this->proxyNames));
        $final_yaml = str_replace('##PROXIES##', $proxies_yaml, $template);
        $final_yaml = str_replace('##PROXY_NAMES##', $proxy_names_yaml, $final_yaml);

        if ($this->type === 'meta') {
            $meta_additions = file_get_contents(TEMPLATES_DIR . '/meta_additions.yaml');
            $meta_parts = explode("meta_rules:", $meta_additions, 2);
            $final_yaml = str_replace("rules:", $meta_parts[0] . "\nrules:", $final_yaml);
            $final_yaml = str_replace("  - MATCH,PSG-MANUAL", trim($meta_parts[1]) . "\n  - MATCH,PSG-MANUAL", $final_yaml);
        }
        return $final_yaml;
    }
}

class SurfboardProfile extends ProfileGenerator {
    private string $configUrl;
    public function __construct(string $configUrl) { $this->configUrl = $configUrl; }
    protected function formatProxy(array $proxyData): ?string {
        $type = $proxyData['type'];
        if ($type === 'vless' || ($type === 'ss' && $proxyData['cipher'] === '2022-blake3-aes-256-gcm')) return null;

        $parts = [$proxyData['name'] . " = " . $type, $proxyData['server'], $proxyData['port']];
        if ($type === 'vmess') {
            $aead = ($proxyData['alterId'] ?? 0) == 0;
            $parts[] = "username = " . $proxyData['uuid'];
            $parts[] = "ws = " . ($proxyData['network'] === 'ws' ? 'true' : 'false');
            $parts[] = "tls = " . ($proxyData['tls'] ? 'true' : 'false');
            $parts[] = "vmess-aead = " . ($aead ? 'true' : 'false');
            if ($proxyData['network'] === 'ws') {
                $parts[] = "ws-path = " . $proxyData['ws-opts']['path'];
                $parts[] = 'ws-headers = Host:"' . $proxyData['ws-opts']['headers']['Host'] . '"';
            }
        } elseif ($type === 'trojan') {
            $parts[] = "password = " . $proxyData['password'];
            $parts[] = "skip-cert-verify = " . ($proxyData['skip-cert-verify'] ? 'true' : 'false');
            if (isset($proxyData['servername'])) $parts[] = "sni = " . $proxyData['servername'];
        } elseif ($type === 'ss') {
            $parts[] = "encrypt-method = " . $proxyData['cipher'];
            $parts[] = "password = " . $proxyData['password'];
        }
        return implode(", ", $parts);
    }
    public function generate(): string {
        $template = file_get_contents(TEMPLATES_DIR . '/surfboard.ini');
        $proxies_ini = implode("\n", $this->proxies);
        $proxy_names_ini = implode(", ", $this->proxyNames);
        $final_ini = str_replace('##CONFIG_URL##', $this->configUrl, $template);
        $final_ini = str_replace('##PROXIES##', $proxies_ini, $final_ini);
        return str_replace('##PROXY_NAMES##', $proxy_names_ini, $final_ini);
    }
}


// --- Main Script Execution ---

echo "Starting conversion to Clash, Meta, and Surfboard formats..." . PHP_EOL;

$files_to_process = glob(INPUT_DIR . '/*');

foreach ($files_to_process as $filepath) {
    $inputType = pathinfo($filepath, PATHINFO_FILENAME);
    echo "Processing input file: {$inputType}..." . PHP_EOL;
    
    $base64_data = file_get_contents($filepath);
    $configs = file(sprintf('data:text/plain;base64,%s', $base64_data), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    // Convert all configs to a neutral format once
    $proxyDataList = [];
    foreach ($configs as $config_str) {
        $wrapper = new ConfigWrapper($config_str);
        if (!$wrapper->isValid()) continue;
        
        $proxyData = match($wrapper->getType()) {
            'vmess' => vmessToProxyData($wrapper),
            'vless' => vlessToProxyData($wrapper),
            'trojan' => trojanToProxyData($wrapper),
            'ss' => ssToProxyData($wrapper),
            default => null
        };
        if ($proxyData) $proxyDataList[] = $proxyData;
    }

    // Now, generate all required output files from the neutral data
    foreach (OUTPUT_MAPPING as $outputType => $allowedInputs) {
        if (in_array($inputType, $allowedInputs)) {
            echo "  -> Generating {$outputType} profile..." . PHP_EOL;
            
            $outputDir = OUTPUT_DIR_BASE . '/' . $outputType;
            if (!is_dir($outputDir)) mkdir($outputDir, 0775, true);

            if ($outputType === 'clash' || $outputType === 'meta') {
                $generator = new ClashProfile($outputType);
            } elseif ($outputType === 'surfboard') {
                $url = GITHUB_BASE_URL . '/subscriptions/surfboard/' . $inputType;
                $generator = new SurfboardProfile($url);
            } else {
                continue;
            }

            foreach($proxyDataList as $proxyData) {
                $generator->addProxy($proxyData);
            }
            
            file_put_contents($outputDir . '/' . $inputType, $generator->generate());
        }
    }
}

echo "All conversions are complete!" . PHP_EOL;
