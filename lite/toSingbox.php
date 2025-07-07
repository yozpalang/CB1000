<?php

declare(strict_types=1);

/**
 * This script converts various proxy subscription formats (VLESS, VMess, etc.)
 * into the sing-box JSON configuration format. It processes multiple input
 * files from a directory and generates corresponding sing-box profiles.
 */

// --- Setup ---
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/functions.php';

// --- Configuration Constants ---
const INPUT_DIR = __DIR__ . '/subscriptions/xray/base64';
const OUTPUT_DIR = __DIR__ . '/subscriptions/singbox';
const STRUCTURE_FILE = __DIR__ . '/templates/structure.json';
const ALLOWED_SS_METHODS = [
    "chacha20-ietf-poly1305",
    "aes-256-gcm",
    "2022-blake3-aes-256-gcm"
];

// #############################################################################
// The Core Abstraction: A Wrapper Class for Different Config Types
// #############################################################################

class ConfigWrapper
{
    private ?array $decoded;
    private string $type;

    public function __construct(string $config_string)
    {
        $this->type = detect_type($config_string) ?? 'unknown';
        $this->decoded = configParse($config_string);
    }

    public function isValid(): bool
    {
        return $this->decoded !== null;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTag(): string
    {
        $field = match($this->type) {
            'vmess' => 'ps',
            'ss' => 'name',
            default => 'hash',
        };
        return urldecode($this->decoded[$field] ?? 'Unknown Tag');
    }

    public function getServer(): string
    {
        return match($this->type) {
            'vmess' => $this->decoded['add'],
            'ss' => $this->decoded['server_address'],
            default => $this->decoded['hostname'],
        };
    }

    public function getPort(): int
    {
        $port = match($this->type) {
            'ss' => $this->decoded['server_port'],
            default => $this->decoded['port'],
        };
        return (int)$port;
    }

    public function getUuid(): string
    {
        return match($this->type) {
            'vmess' => $this->decoded['id'],
            'vless', 'trojan' => $this->decoded['username'],
            'tuic' => $this->decoded['username'],
            default => '',
        };
    }

    public function getPassword(): string
    {
        return match($this->type) {
            'trojan' => $this->decoded['username'],
            'ss' => $this->decoded['password'],
            'tuic' => $this->decoded['pass'],
            'hy2' => $this->decoded['username'],
            default => '',
        };
    }

    public function getSni(): string
    {
        return match($this->type) {
            'vmess' => $this->decoded['sni'] ?? $this->getServer(),
            default => $this->decoded['params']['sni'] ?? $this->getServer(),
        };
    }

    public function getTransportType(): ?string
    {
        return match($this->type) {
            'vmess' => $this->decoded['net'],
            default => $this->decoded['params']['type'] ?? null,
        };
    }
    
    public function getPath(): string
    {
        $path = match($this->type) {
            'vmess' => $this->decoded['path'] ?? '/',
            default => $this->decoded['params']['path'] ?? '/',
        };
        return '/' . ltrim($path, '/');
    }

    public function getServiceName(): string
    {
        return match($this->type) {
            'vmess' => $this->decoded['path'] ?? '',
            default => $this->decoded['params']['serviceName'] ?? '',
        };
    }

    // Pass through direct access to the decoded array for complex cases
    public function get(string $key, $default = null)
    {
        return $this->decoded[$key] ?? $default;
    }
    
    public function getParam(string $key, $default = null)
    {
        return $this->decoded['params'][$key] ?? $default;
    }
}


// #############################################################################
// Refactored Conversion Functions
// #############################################################################

function vmessToSingbox(ConfigWrapper $c): ?array
{
    $config = [
        "tag" => $c->getTag(), "type" => "vmess", "server" => $c->getServer(),
        "server_port" => $c->getPort(), "uuid" => $c->getUuid(), "security" => "auto",
        "alter_id" => (int)$c->get('aid'),
    ];
    if ($c->getPort() === 443 || $c->get('tls') === 'tls') {
        $config["tls"] = createTlsSettings($c);
    }
    if (in_array($c->getTransportType(), ["ws", "grpc", "http"])) {
        $config["transport"] = createTransportSettings($c);
        if ($config["transport"] === null) return null; // Invalid transport
    }
    return $config;
}

function vlessToSingbox(ConfigWrapper $c): ?array
{
    $config = [
        "tag" => $c->getTag(), "type" => "vless", "server" => $c->getServer(),
        "server_port" => $c->getPort(), "uuid" => $c->getUuid(),
        "flow" => $c->getParam('flow') ? "xtls-rprx-vision" : "", "packet_encoding" => "xudp",
    ];
    if ($c->getPort() === 443 || in_array($c->getParam('security'), ['tls', 'reality'])) {
        $config["tls"] = createTlsSettings($c);
        if ($c->getParam('security') === 'reality' || $c->getParam('pbk')) {
            $config['flow'] = "xtls-rprx-vision";
            $config["tls"]["reality"] = ['enabled' => true, 'public_key' => $c->getParam('pbk', ''), 'short_id' => $c->getParam('sid', '')];
            $config["tls"]["utls"]['fingerprint'] = $c->getParam('fp');
            if (empty($config["tls"]["reality"]['public_key'])) return null;
        }
    }
    if (in_array($c->getTransportType(), ["ws", "grpc", "http"])) {
        $config["transport"] = createTransportSettings($c);
        if ($config["transport"] === null) return null;
    }
    return $config;
}

function trojanToSingbox(ConfigWrapper $c): ?array
{
    $config = [
        "tag" => $c->getTag(), "type" => "trojan", "server" => $c->getServer(),
        "server_port" => $c->getPort(), "password" => $c->getPassword(),
    ];
    if ($c->getPort() === 443 || $c->getParam('security') === 'tls') {
        $config["tls"] = createTlsSettings($c);
    }
    if (in_array($c->getTransportType(), ["ws", "grpc", "http"])) {
        $config["transport"] = createTransportSettings($c);
        if ($config["transport"] === null) return null;
    }
    return $config;
}

function ssToSingbox(ConfigWrapper $c): ?array
{
    $method = $c->get('encryption_method');
    if (!in_array($method, ALLOWED_SS_METHODS)) {
        return null;
    }
    return [
        "tag" => $c->getTag(), "type" => "shadowsocks", "server" => $c->getServer(),
        "server_port" => $c->getPort(), "method" => $method, "password" => $c->getPassword(),
    ];
}

function tuicToSingbox(ConfigWrapper $c): ?array
{
    return [
        "tag" => $c->getTag(), "type" => "tuic", "server" => $c->getServer(),
        "server_port" => $c->getPort(), "uuid" => $c->getUuid(), "password" => $c->getPassword(),
        "congestion_control" => $c->getParam("congestion_control", "bbr"),
        "udp_relay_mode" => $c->getParam("udp_relay_mode", "native"),
        "tls" => [
            "enabled" => true,
            "server_name" => $c->getSni(),
            "insecure" => (bool)$c->getParam("allow_insecure", 0),
            "alpn" => empty($c->getParam('alpn')) ? null : explode(',', $c->getParam('alpn')),
        ]
    ];
}

function hy2ToSingbox(ConfigWrapper $c): ?array
{
    $obfsPass = $c->getParam('obfs-password');
    if (empty($obfsPass)) return null;

    return [
        "tag" => $c->getTag(), "type" => "hysteria2", "server" => $c->getServer(),
        "server_port" => $c->getPort(), "password" => $c->getPassword(),
        "obfs" => ["type" => $c->getParam('obfs'), "password" => $obfsPass],
        "tls" => [
            "enabled" => true,
            "server_name" => $c->getSni(),
            "insecure" => (bool)$c->getParam("insecure", 0),
            "alpn" => ["h3"],
        ],
    ];
}

// #############################################################################
// Unified Helper Functions
// #############################################################################

function createTlsSettings(ConfigWrapper $c): array
{
    return [
        "enabled" => true, "server_name" => $c->getSni(), "insecure" => true,
        "utls" => ["enabled" => true, "fingerprint" => "chrome"],
    ];
}

function createTransportSettings(ConfigWrapper $c): ?array
{
    $transportType = $c->getTransportType();
    $transport = match($transportType) {
        'ws' => ["type" => "ws", "path" => $c->getPath(), "headers" => ["Host" => $c->getSni()]],
        'grpc' => ["type" => "grpc", "service_name" => $c->getServiceName()],
        'http' => ["type" => "http", "host" => [$c->getSni()], "path" => $c->getPath()],
        default => null
    };
    // Centralized validation
    if ($transportType === 'grpc' && empty($transport['service_name'])) {
        return null;
    }
    return $transport;
}

// #############################################################################
// Main Processing Logic
// #############################################################################

/**
 * Main router function to convert any config string to a sing-box array.
 */
function convert_to_singbox_array(string $config_string): ?array
{
    $wrapper = new ConfigWrapper($config_string);
    if (!$wrapper->isValid()) {
        return null;
    }
    return match($wrapper->getType()) {
        "vmess" => vmessToSingbox($wrapper),
        "vless" => vlessToSingbox($wrapper),
        "trojan" => trojanToSingbox($wrapper),
        "ss" => ssToSingbox($wrapper),
        "tuic" => tuicToSingbox($wrapper),
        "hy2" => hy2ToSingbox($wrapper),
        default => null,
    };
}

/**
 * Generates the full sing-box JSON profile from a list of configs.
 */
function generate_singbox_profile(string $base64_configs, array $base_structure, string $profile_name): string
{
    $configs = file(sprintf('data:text/plain;base64,%s', $base64_configs), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($configs as $config) {
        $singboxConfig = convert_to_singbox_array($config);
        if ($singboxConfig !== null) {
            $base_structure['outbounds'][] = $singboxConfig;
            $tag = $singboxConfig['tag'];
            // Add tag to "All" and "Auto" groups
            $base_structure['outbounds'][0]['outbounds'][] = $tag;
            $base_structure['outbounds'][1]['outbounds'][] = $tag;
        }
    }

    $base64Name = base64_encode($profile_name);
    $header = <<<HEADER
//profile-title: base64:{$base64Name}
//profile-update-interval: 1
//subscription-userinfo: upload=0; download=0; total=10737418240000000; expire=2546249531
//support-url: https://t.me/yebekhe
//profile-web-page-url: ithub.com/itsyebekhe/PSG

HEADER;

    return $header . json_encode($base_structure, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}


// --- Script Execution ---

echo "Starting conversion to sing-box format..." . PHP_EOL;

if (!file_exists(STRUCTURE_FILE)) {
    die("Error: structure.json not found." . PHP_EOL);
}
// **PERFORMANCE**: Read the structure file ONCE.
$base_structure = json_decode(file_get_contents(STRUCTURE_FILE), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error: Invalid JSON in structure.json" . PHP_EOL);
}

// **ROBUSTNESS**: Use glob to find files dynamically.
$files_to_process = glob(INPUT_DIR . '/*');
if (empty($files_to_process)) {
    echo "No files found in " . INPUT_DIR . " to process." . PHP_EOL;
    exit;
}

if (!is_dir(OUTPUT_DIR)) {
    mkdir(OUTPUT_DIR, 0775, true);
}

foreach ($files_to_process as $filepath) {
    // **ROBUSTNESS**: Use pathinfo to get filename reliably.
    $filename = pathinfo($filepath, PATHINFO_FILENAME);
    $profile_name = "PSG | " . strtoupper($filename);
    
    echo "Processing {$filename}..." . PHP_EOL;

    $base64_data = file_get_contents($filepath);
    $converted_profile = generate_singbox_profile($base64_data, $base_structure, $profile_name);
    
    file_put_contents(OUTPUT_DIR . '/' . $filename . ".json", $converted_profile);
}

echo "Conversion to sing-box complete!" . PHP_EOL;
