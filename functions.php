<?php

/**
 * Validates if a string is a valid IP address (IPv4 or IPv6).
 *
 * @param string $string The string to check.
 * @return bool True if it's a valid IP, false otherwise.
 */
function is_ip(string $string): bool
{
    // Use PHP's built-in, highly optimized filter. It's faster and more accurate than regex.
    return filter_var($string, FILTER_VALIDATE_IP) !== false;
}

/**
 * Parses a key-value string (one pair per line, separated by '=') into an associative array.
 *
 * @param string $input The input string.
 * @return array The parsed data.
 */
function parse_key_value_string(string $input): array
{
    $data = [];
    // Use PREG_SPLIT_NO_EMPTY to ignore empty lines.
    $lines = preg_split('/\\R/', $input, -1, PREG_SPLIT_NO_EMPTY);

    foreach ($lines as $line) {
        // Explode with a limit of 2, in case the value contains an '='.
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            // Ensure key and value are not empty after trimming.
            if ($key !== '' && $value !== '') {
                $data[$key] = $value;
            }
        }
    }
    return $data;
}

/**
 * Fetches geolocation information for an IP address or hostname.
 * It uses a fallback mechanism with multiple API endpoints.
 *
 * @param string $ipOrHost The IP address or hostname.
 * @return stdClass|null An object with country info, or null on failure.
 */
function ip_info(string $ipOrHost): ?stdClass
{
    // Check for Cloudflare first (cached).
    if (is_cloudflare_ip($ipOrHost)) {
        $traceUrl = "http://{$ipOrHost}/cdn-cgi/trace";
        // Use a timeout for file_get_contents to prevent long waits.
        $context = stream_context_create(['http' => ['timeout' => 5]]);
        $traceContent = @file_get_contents($traceUrl, false, $context);

        if ($traceContent) {
            $traceData = parse_key_value_string($traceContent);
            return (object) [
                "country" => $traceData['loc'] ?? 'CF',
            ];
        }
    }
    
    $ip = $ipOrHost;
    // Resolve hostname to IP if needed.
    if (!is_ip($ip)) {
        // Use '@' to suppress warnings on invalid domains.
        $ip_records = @dns_get_record($ip, DNS_A);
        if (empty($ip_records)) {
            return null; // Failed to resolve hostname
        }
        $ip = $ip_records[array_rand($ip_records)]["ip"];
    }

    // API endpoint configuration [url_template, country_code_key]
    $endpoints = [
        ['https://ipapi.co/{ip}/json/', 'country_code'],
        ['https://ipwhois.app/json/{ip}', 'country_code'],
        ['http://www.geoplugin.net/json.gp?ip={ip}', 'geoplugin_countryCode'],
        // Note: ipbase.com requires an API key for most uses. This might fail.
        ['https://api.ipbase.com/v1/json/{ip}', 'country_code'],
    ];

    // Create stream context once with a reasonable timeout.
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36\r\n",
            'timeout' => 5, // 5 second timeout
        ],
    ];
    $context = stream_context_create($options);

    foreach ($endpoints as [$url_template, $country_key]) {
        $url = str_replace('{ip}', $ip, $url_template);
        // Use '@' to suppress warnings from file_get_contents on failure.
        $response = @file_get_contents($url, false, $context);

        if ($response !== false) {
            $data = json_decode($response);
            // Check if JSON decoding was successful and the key exists
            if (json_last_error() === JSON_ERROR_NONE && isset($data->{$country_key})) {
                return (object) [
                    "country" => $data->{$country_key} ?? 'XX',
                ];
            }
        }
    }

    // Return default if all endpoints fail.
    return (object) ["country" => "XX"];
}


/**
 * Checks if an IP is a Cloudflare IP, using a local cache.
 *
 * @param string $ip The IP to check.
 * @return bool
 */
function is_cloudflare_ip(string $ip): bool
{
    $cacheFile = sys_get_temp_dir() . '/cloudflare_ips_v4.cache';
    $cacheTime = 3600 * 24; // 24 hours

    // Use cache if it exists and is not expired.
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
        $cloudflare_ranges_str = file_get_contents($cacheFile);
    } else {
        // Fetch fresh list and update cache.
        $cloudflare_ranges_str = @file_get_contents('https://www.cloudflare.com/ips-v4');
        if ($cloudflare_ranges_str === false) {
            // If fetch fails but an old cache exists, use it to prevent total failure.
            return file_exists($cacheFile) ? is_cloudflare_ip($ip) : false;
        }
        file_put_contents($cacheFile, $cloudflare_ranges_str);
    }
    
    $cloudflare_ranges = explode("\n", $cloudflare_ranges_str);

    foreach ($cloudflare_ranges as $range) {
        if (!empty($range) && cidr_match($ip, $range)) {
            return true;
        }
    }
    return false;
}

/**
 * Matches an IP address to a CIDR range.
 *
 * @param string $ip The IP address.
 * @param string $range The CIDR range (e.g., "192.168.1.0/24").
 * @return bool
 */
function cidr_match(string $ip, string $range): bool
{
    // Robustly handle ranges with or without a bitmask.
    if (!str_contains($range, '/')) {
        $range .= '/32';
    }

    list($subnet, $bits) = explode('/', $range, 2);

    // Validate inputs to prevent errors with ip2long.
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false ||
        filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        return false;
    }

    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask = -1 << (32 - (int)$bits);
    
    return ($ip_long & $mask) === ($subnet_long & $mask);
}


/**
 * Checks if the input string contains invalid characters.
 *
 * @param string $input
 * @return bool True if valid, false otherwise.
 */
function is_valid(string $input): bool
{
    // Combined check is slightly more efficient.
    return !(str_contains($input, '…') || str_contains($input, '...'));
}


/**
 * Determines if a proxy configuration is encrypted.
 *
 * @param string $input The configuration link.
 * @return bool
 */
function isEncrypted(string $input): bool
{
    $configType = detect_type($input);

    switch ($configType) {
        case 'vmess':
            $decodedConfig = configParse($input);
            // Ensure keys exist before accessing.
            return ($decodedConfig['tls'] ?? '') !== '' && ($decodedConfig['scy'] ?? 'none') !== 'none';

        case 'vless':
        case 'trojan':
            // Fast check without full parsing.
            return str_contains($input, 'security=tls') || str_contains($input, 'security=reality');
        
        case 'ss':
        case 'tuic':
        case 'hy2':
            // These protocols are inherently encrypted.
            return true;

        default:
            return false;
    }
}


/**
 * Converts a 2-letter country code to a regional flag emoji.
 *
 * @param string $country_code
 * @return string
 */
function getFlags(string $country_code): string
{
    $country_code = strtoupper(trim($country_code));
    if (strlen($country_code) !== 2 || !ctype_alpha($country_code)) {
        return '🏳️'; // Return a default flag for invalid codes.
    }

    $regional_offset = 127397;
    $char1 = mb_convert_encoding('&#' . ($regional_offset + ord($country_code[0])) . ';', 'UTF-8', 'HTML-ENTITIES');
    $char2 = mb_convert_encoding('&#' . ($regional_offset + ord($country_code[1])) . ';', 'UTF-8', 'HTML-ENTITIES');
    
    return $char1 . $char2;
}

/**
 * Detects the proxy protocol type from a configuration link.
 * Uses modern str_starts_with() for readability and performance.
 *
 * @param string $input
 * @return string|null
 */
function detect_type(string $input): ?string
{
    if (str_starts_with($input, 'vmess://')) return 'vmess';
    if (str_starts_with($input, 'vless://')) return 'vless';
    if (str_starts_with($input, 'trojan://')) return 'trojan';
    if (str_starts_with($input, 'ss://')) return 'ss';
    if (str_starts_with($input, 'tuic://')) return 'tuic';
    if (str_starts_with($input, 'hy2://') || str_starts_with($input, 'hysteria2://')) return 'hy2';
    if (str_starts_with($input, 'hysteria://')) return 'hysteria';
    
    return null;
}

/**
 * Extracts all links of a specific protocol type from a larger string.
 *
 * @param string $inputString The text to search within.
 * @param string $configType The protocol type (e.g., "vmess").
 * @return array An array of found links.
 */
function extractLinksByType(string $inputString, string $configType): array
{
    // Use preg_quote to safely handle the configType in regex.
    $pattern = '/' . preg_quote($configType, '/') . ':\/\/[^"\'\s]+/';
    preg_match_all($pattern, $inputString, $matches);
    
    return $matches[0] ?? [];
}

/**
 * Parses a configuration link into an associative array.
 *
 * @param string $input The configuration link.
 * @return array|null The parsed configuration or null on failure.
 */
function configParse(string $input): ?array
{
    $configType = detect_type($input);

    switch ($configType) {
        case 'vmess':
            $base64_data = substr($input, 8);
            return json_decode(base64_decode($base64_data), true);

        case 'vless':
        case 'trojan':
        case 'tuic':
        case 'hy2':
            $parsedUrl = parse_url($input);
            if ($parsedUrl === false) return null;
            
            $params = [];
            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $params);
            }
            
            $output = [
                'protocol' => $configType,
                'username' => $parsedUrl['user'] ?? '',
                'hostname' => $parsedUrl['host'] ?? '',
                'port' => $parsedUrl['port'] ?? '',
                'params' => $params,
                'hash' => isset($parsedUrl['fragment']) ? rawurldecode($parsedUrl['fragment']) : 'PSG' . getRandomName(),
            ];

            if ($configType === 'tuic') {
                $output['pass'] = $parsedUrl['pass'] ?? '';
            }
            return $output;

        case 'ss':
            $parsedUrl = parse_url($input);
            if ($parsedUrl === false) return null;

            $userInfo = rawurldecode($parsedUrl['user'] ?? '');
            
            // Handle Base64 encoded user info part
            if (isBase64($userInfo)) {
                $userInfo = base64_decode($userInfo);
            }

            if (!str_contains($userInfo, ':')) return null; // Invalid format
            
            list($method, $password) = explode(':', $userInfo, 2);

            return [
                'encryption_method' => $method,
                'password' => $password,
                'server_address' => $parsedUrl['host'] ?? '',
                'server_port' => $parsedUrl['port'] ?? '',
                'name' => isset($parsedUrl['fragment']) ? rawurldecode($parsedUrl['fragment']) : 'PSG' . getRandomName(),
            ];
            
        default:
            return null;
    }
}

/**
 * Rebuilds a configuration link from a parsed array.
 *
 * @param array $configArray
 * @param string $configType
 * @return string|null
 */
function reparseConfig(array $configArray, string $configType): ?string
{
    switch ($configType) {
        case 'vmess':
            $encoded_data = rtrim(strtr(base64_encode(json_encode($configArray)), '+/', '-_'), '=');
            return "vmess://" . $encoded_data;
        
        case 'vless':
        case 'trojan':
        case 'tuic':
        case 'hy2':
            $url = $configType . "://";
            // User and optional password
            if (!empty($configArray['username'])) {
                $url .= $configArray['username'];
                if (!empty($configArray['pass'])) {
                    $url .= ':' . $configArray['pass'];
                }
                $url .= '@';
            }
            $url .= $configArray['hostname'];
            // Port
            if (!empty($configArray['port'])) {
                $url .= ':' . $configArray['port'];
            }
            // Query parameters
            if (!empty($configArray['params'])) {
                $url .= '?' . http_build_query($configArray['params']);
            }
            // Fragment/hash
            if (!empty($configArray['hash'])) {
                // rawurlencode is the correct function for fragments.
                $url .= '#' . rawurlencode($configArray['hash']);
            }
            return $url;

        case 'ss':
            $user_info = base64_encode($configArray['encryption_method'] . ':' . $configArray['password']);
            $url = "ss://{$user_info}@{$configArray['server_address']}:{$configArray['server_port']}";
            if (!empty($configArray['name'])) {
                $url .= '#' . rawurlencode($configArray['name']);
            }
            return $url;

        default:
            return null;
    }
}

/**
 * Checks if a VLESS config uses the 'reality' security protocol.
 *
 * @param string $input
 * @return bool
 */
function is_reality(string $input): bool
{
    // A fast string check is sufficient and avoids parsing.
    return str_starts_with($input, 'vless://') && str_contains($input, 'security=reality');
}

/**
 * Checks if a string is Base64 encoded.
 *
 * @param string $input
 * @return bool
 */
function isBase64(string $input): bool
{
    // The strict parameter ensures the input contains only valid Base64 characters.
    return base64_decode($input, true) !== false;
}

/**
 * Generates a cryptographically secure random name.
 *
 * @param int $length
 * @return string
 */
function getRandomName(int $length = 10): string
{
    // Using random_int is more secure than rand().
    $alphabet = 'abcdefghijklmnopqrstuvwxyz';
    $max = strlen($alphabet) - 1;
    $name = '';
    for ($i = 0; $i < $length; $i++) {
        $name .= $alphabet[random_int(0, $max)];
    }
    return $name;
}

/**
 * Recursively deletes a folder and its contents.
 *
 * @param string $folder The path to the folder.
 * @return bool True on success, false on failure.
 */
function deleteFolder(string $folder): bool
{
    if (!is_dir($folder)) {
        return false;
    }

    // Use modern iterators for better performance and clarity.
    $iterator = new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }

    return rmdir($folder);
}

/**
 * Gets the current time in the Asia/Tehran timezone.
 *
 * @param string $format The desired date/time format.
 * @return string The formatted time string.
 */
function tehran_time(string $format = 'Y-m-d H:i:s'): string
{
    // This is safer than date_default_timezone_set() as it doesn't affect global state.
    try {
        $date = new DateTime('now', new DateTimeZone('Asia/Tehran'));
        return $date->format($format);
    } catch (Exception $e) {
        // Fallback in case of an error.
        return date($format);
    }
}

/**
 * Generates a Hiddify-compatible subscription header.
 * Uses NOWDOC syntax for clean, multi-line string.
 *
 * @param string $subscriptionName
 * @return string
 */
function hiddifyHeader(string $subscriptionName): string
{
    $base64Name = base64_encode($subscriptionName);
    return <<<HEADER
#profile-title: base64:{$base64Name}
#profile-update-interval: 1
#subscription-userinfo: upload=0; download=0; total=10737418240000000; expire=2546249531
#support-url: https://t.me/yebekhe
#profile-web-page-url: https://github.com/itsyebekhe/PSG

HEADER;
}

/**
 * Fetches multiple URLs in parallel using cURL for maximum efficiency.
 * @param array $urls An associative array of [key => url]
 * @return array An associative array of [key => content] for successful requests.
 */
function fetch_multiple_urls_parallel(array $urls): array
{
    $multi_handle = curl_multi_init();
    $handles = [];
    $results = [];

    foreach ($urls as $key => $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15, // 15-second timeout per request
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false, // Less secure, but helps with t.me SSL issues
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $handles[$key] = $ch;
        curl_multi_add_handle($multi_handle, $ch);
    }

    $running = null;
    do {
        curl_multi_exec($multi_handle, $running);
        curl_multi_select($multi_handle); // Wait for activity
    } while ($running > 0);

    foreach ($handles as $key => $ch) {
        $content = curl_multi_getcontent($ch);
        if (curl_errno($ch) === 0 && !empty($content)) {
            $results[$key] = $content;
        } else {
            echo "\nWarning: Failed to fetch URL for source '{$key}': " . curl_error($ch) . "\n";
        }
        curl_multi_remove_handle($multi_handle, $ch);
        curl_close($ch);
    }

    curl_multi_close($multi_handle);
    return $results;
}

/**
 * Prints a clean, overwriting progress bar to the console.
 * @param int $current
 * @param int $total
 * @param string $message
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

/**
 * Validates if a string is a valid Version 4 UUID.
 *
 * @param string|null $uuid The string to check.
 * @return bool True if valid, false otherwise.
 */
function is_valid_uuid(?string $uuid): bool
{
    if ($uuid === null) {
        return false;
    }
    
    // This regex is a standard and reliable pattern for V4 UUIDs.
    $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
    
    return (bool) preg_match($pattern, $uuid);
}

/**
 * Fetches multiple pages of a Telegram channel until the page limit is reached or no more pages are available.
 *
 * @param string $channelName The username of the channel.
 * @param int $maxPages The maximum number of pages to fetch.
 * @return string The combined HTML content of all fetched pages.
 */
function fetch_channel_data_paginated(string $channelName, int $maxPages): string
{
    $combinedHtml = '';
    $nextUrl = "https://t.me/s/{$channelName}";
    $fetchedPages = 0;

    while ($fetchedPages < $maxPages && $nextUrl) {
        echo "\rFetching page " . ($fetchedPages + 1) . "/{$maxPages} for channel '{$channelName}'... ";
        
        $response = @file_get_contents($nextUrl, false, stream_context_create([
            'http' => [
                'timeout' => 15,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36',
            ]
        ]));

        if ($response === false || empty($response)) {
            // Stop paginating for this channel if a request fails
            $nextUrl = null;
            continue;
        }

        $combinedHtml .= $response;

        // Find the oldest message ID on the page to build the next page URL
        preg_match_all('/data-post="[^"]+\/(\d+)"/', $response, $matches);
        
        if (!empty($matches[1])) {
            $oldestMessageId = min($matches[1]);
            $nextUrl = "https://t.me/s/{$channelName}?before={$oldestMessageId}";
        } else {
            // No more message IDs found, so it's the last page
            $nextUrl = null;
        }
        $fetchedPages++;
    }

    return $combinedHtml;
}
