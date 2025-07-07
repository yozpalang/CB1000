<?php

declare(strict_types=1);

/**
 * Modern Subscription Page Generator for PSG
 *
 * Scans subscription directories and generates a modern, visually-rich index.html
 * with client icons, country flags, protocol tags, and a responsive UI.
 */

// --- Configuration ---
define('PROJECT_ROOT', __DIR__);
define('GITHUB_REPO_URL', 'https://raw.githubusercontent.com/itsyebekhe/PSG/main');
define('OUTPUT_HTML_FILE', PROJECT_ROOT . '/index.html');
define('SCAN_DIRECTORIES', [
    'Standard' => PROJECT_ROOT . '/subscriptions',
    'Lite' => PROJECT_ROOT . '/lite/subscriptions',
]);

// --- Data Mapping for Visuals ---
const CLIENT_ICONS = [
    'clash' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="icon"><path d="M12.75 3.03v.75a.75.75 0 0 1-1.5 0v-.75a.75.75 0 0 1 1.5 0Zm-1.5 8.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75Zm-1.5 3a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM7.875 6a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM12 8.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75Zm4.125-2.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM15 11.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM12.75 14.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM12 17.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM12.75 20.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM9.75 17.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM8.25 14.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM9 11.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75Z M4.5 9a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75A.75.75 0 0 1 4.5 9Z" clip-rule="evenodd" /></svg>',
    'meta' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="icon"><path d="M12.75 3.03v.75a.75.75 0 0 1-1.5 0v-.75a.75.75 0 0 1 1.5 0Zm-1.5 8.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75Zm-1.5 3a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM7.875 6a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM12 8.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75Zm4.125-2.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM15 11.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM12.75 14.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM12 17.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM12.75 20.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM9.75 17.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM8.25 14.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM9 11.25a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75Z M4.5 9a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75A.75.75 0 0 1 4.5 9Z" clip-rule="evenodd" /></svg>',
    'singbox' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="icon"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm11.378-3.917c-.882 0-1.6.718-1.6 1.6 0 .882.718 1.6 1.6 1.6.882 0 1.6-.718 1.6-1.6 0-.882-.718-1.6-1.6-1.6ZM8.25 12c0-.882.718-1.6 1.6-1.6.882 0 1.6.718 1.6 1.6s-.718 1.6-1.6 1.6c-.882 0-1.6-.718-1.6-1.6Zm3.828 4.083c-1.61 0-2.917-1.306-2.917-2.917 0-1.61 1.307-2.917 2.917-2.917 1.61 0 2.917 1.307 2.917 2.917 0 1.61-1.307 2.917-2.917 2.917Z" clip-rule="evenodd" /></svg>',
    'surfboard' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="icon"><path d="M14.625 2.25a.75.75 0 0 1 .75.75v18a.75.75 0 0 1-1.5 0V3a.75.75 0 0 1 .75-.75Z" /><path d="M5.969 4.22a.75.75 0 0 1 1.06 0l5.25 5.25a.75.75 0 0 1 0 1.06l-5.25 5.25a.75.75 0 1 1-1.06-1.06l4.72-4.72-4.72-4.72a.75.75 0 0 1 0-1.06Z" /></svg>',
    'warp' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="icon"><path fill-rule="evenodd" d="M12.963 2.286a.75.75 0 0 0-1.071 1.052A9.75 9.75 0 0 1 18.635 12a.75.75 0 0 1-1.5 0 8.25 8.25 0 0 0-7.22-8.224.75.75 0 0 0-1.052-1.071Zm-3.182 2.859A.75.75 0 0 1 11.25 6a8.25 8.25 0 0 1 8.25 8.25.75.75 0 0 1-1.5 0A6.75 6.75 0 0 0 11.25 7.5a.75.75 0 0 1-.47-1.355Z" clip-rule="evenodd" /></svg>',
    'xray' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="icon"><path d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm1.823 12.34a.75.75 0 0 1-1.196-.935l.23-.46a.75.75 0 0 1 .936-1.196l-.23.46a.75.75 0 0 1 .266 1.631Zm-3.23.22a.75.75 0 0 1-1.197-.936l.231-.46a.75.75 0 0 1 .936-1.196l-.23.46a.75.75 0 0 1 .26 1.632Zm3.242-3.834a.75.75 0 0 1-1.061-1.06l.472-.472a.75.75 0 1 1 1.06 1.06l-.47.47Zm-3.415-3.415a.75.75 0 0 1-1.06-1.061l.47-.472a.75.75 0 1 1 1.06 1.06l-.47.472Z" /></svg>',
    'location' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="icon"><path fill-rule="evenodd" d="M11.54 22.351l.07.04.028.016a.76.76 0 0 0 .723 0l.028-.015.071-.041a23.525 23.525 0 0 0 4.28-2.28.816.816 0 0 0-.001-1.442l-3.636-2.09A.75.75 0 0 0 12 16.5v-3.75a.75.75 0 0 1 .75-.75h.008a.75.75 0 0 0 .742-.723l.025-1.08a.75.75 0 0 0-.742-.777H12a.75.75 0 0 0-.75.75v5.25a.75.75 0 0 0 .44 1.33l3.182 1.836-2.923 1.68a21.998 21.998 0 0 1-2.613-1.63c-.22-.178-.484-.308-.758-.358V3a.75.75 0 0 1 .75-.75h2.25a.75.75 0 0 1 0 1.5H12v5.128a1.26 1.26 0 0 1 .099-.517l.01-.03a.75.75 0 0 1 .49-.495l1.023-.417a.75.75 0 0 0 .49-.495l.01-.03c.09-.236.19-.487.29-.728a.75.75 0 0 0-.25-1.011l-3.628-2.093a.818.818 0 0 0-1.444.001l-3.636 2.09A.75.75 0 0 0 4.5 5.25v3.75a.75.75 0 0 0 .75.75h.008a.75.75 0 0 1 .742.723l.025 1.08a.75.75 0 0 1-.742.777H6a.75.75 0 0 1-.75-.75V5.128c.09-.235.19-.486.29-.728a.75.75 0 0 1 .49-.495l1.024-.417a.75.75 0 0 0 .49-.495l.01-.03c.036-.089.07-.178.1-.266A.816.816 0 0 1 9.458 1.65L12 3.472l2.542-1.822a.818.818 0 0 1 1.444-.001l3.636 2.09a.75.75 0 0 1 .25 1.01l-.001.002c-.1.242-.2.493-.29.728l-.01.03a.75.75 0 0 0-.49.495l-1.023.417a.75.75 0 0 1-.49.495l-.01.03a1.26 1.26 0 0 1-.099.517V15a.75.75 0 0 1-.75.75h-2.25a.75.75 0 0 1 0-1.5H12V9.872c-.09.235-.19.486-.29.728a.75.75 0 0 1-.49.495l-1.024.417a.75.75 0 0 0-.49.495l-.01.03c-.09.236-.19.487-.29.728a.75.75 0 0 0 .25 1.011l3.628 2.093a.818.818 0 0 0 1.444-.001l3.636-2.09A.75.75 0 0 1 19.5 15V9.75a.75.75 0 0 1 .75-.75h.008a.75.75 0 0 0 .742-.723l.025-1.08a.75.75 0 0 0-.742-.777H20a.75.75 0 0 0-.75.75v5.25a.75.75 0 0 0 .44 1.33l3.182 1.836a2.25 2.25 0 0 1 .002 3.992l-4.281 2.28-4.28-2.28a.815.815 0 0 0-.724 0l-.028.015-.07.04Z" clip-rule="evenodd" /></svg>',
    'default' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="icon"><path fill-rule="evenodd" d="M11.998 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.383 2.25 11.998 2.25Zm.925 8.243a.75.75 0 0 0-1.85 0l-4.25 2.5a.75.75 0 0 0 .425 1.375l2.218-.475a.75.75 0 0 1 .633.262l1.75 2.5a.75.75 0 0 0 1.3-.913l-1.354-3.16a.75.75 0 0 1 .236-.904l3.013-2.125a.75.75 0 0 0-.5-1.312l-2.438.525a.75.75 0 0 1-.737-.55l-1-3.5a.75.75 0 0 0-1.424-.413l-1 3.5a.75.75 0 0 1-.737.55l-2.438-.525a.75.75 0 0 0-.5 1.312l3.013 2.125a.75.75 0 0 1 .236.904l-1.354 3.16a.75.75 0 0 0 1.3.913l1.75-2.5a.75.75 0 0 1 .633-.262l2.218.475a.75.75 0 0 0 .425-1.375l-4.25-2.5Z" clip-rule="evenodd" /></svg>',
];

const PROTOCOL_COLORS = [
    'vless' => 'bg-sky-100 text-sky-800', 'reality' => 'bg-emerald-100 text-emerald-800',
    'vmess' => 'bg-blue-100 text-blue-800', 'trojan' => 'bg-red-100 text-red-800',
    'ss' => 'bg-purple-100 text-purple-800', 'hy2' => 'bg-pink-100 text-pink-800',
    'tuic' => 'bg-yellow-100 text-yellow-800', 'mix' => 'bg-slate-200 text-slate-800',
];

// --- Helper Functions ---
// `scan_directory` remains the same as before.
function scan_directory(string $dir): array {
    if (!is_dir($dir)) return [];
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    $ignoreExtensions = ['php', 'md', 'json', 'yml', 'yaml', 'ini'];
    foreach ($iterator as $file) {
        if ($file->isFile() && !in_array($file->getExtension(), $ignoreExtensions)) {
            $relativePath = str_replace(PROJECT_ROOT . '/', '', $file->getRealPath());
            $files[] = $relativePath;
        }
    }
    return $files;
}
// `getFlags` from your main functions.php is needed here.
function getFlags(string $country_code): string {
    if (strlen($country_code) !== 2 || !ctype_alpha($country_code)) return '🏳️';
    $country_code = strtoupper($country_code);
    $regional_offset = 127397;
    $char1 = mb_convert_encoding('&#' . ($regional_offset + ord($country_code[0])) . ';', 'UTF-8', 'HTML-ENTITIES');
    $char2 = mb_convert_encoding('&#' . ($regional_offset + ord($country_code[1])) . ';', 'UTF-8', 'HTML-ENTITIES');
    return $char1 . $char2;
}
function process_files_to_structure(array $files): array {
    $structure = [];
    foreach ($files as $category => $paths) {
        foreach ($paths as $path) {
            $parts = explode('/', $path);
            if (count($parts) < 2) continue;
            $type = $parts[count($parts) - 2];
            $name = pathinfo($path, PATHINFO_FILENAME);
            $url = GITHUB_REPO_URL . '/' . $path;
            $structure[$category][$type][$name] = $url;
        }
    }
    foreach ($structure as &$categories) { ksort($categories); }
    return $structure;
}

function generate_html_section(string $type, array $links): string
{
    $icon = CLIENT_ICONS[$type] ?? CLIENT_ICONS['default'];
    $title = ucwords(str_replace(['-', '_'], ' ', $type));
    $html = "<section class='category-section'>\n";
    $html .= "  <h2 class='category-title'>" . $icon . " " . htmlspecialchars($title) . "</h2>\n";
    $html .= "  <div class='grid'>\n";

    foreach ($links as $name => $url) {
        $visual = '';
        $displayName = ucwords(str_replace(['-', '_'], ' ', $name));
        $card_class = 'card';
        
        if ($type === 'location') {
            $flag = getFlags($name);
            $visual = "<span class='flag'>{$flag}</span>";
            $displayName = strtoupper($name);
        } elseif ($type === 'xray') {
            $color_class = PROTOCOL_COLORS[$name] ?? 'bg-slate-200 text-slate-800';
            $visual = "<span class='tag {$color_class}'>" . strtoupper($name) . "</span>";
            $displayName = "Base64 Encoded";
        }
        
        $html .= "    <div class='{$card_class}'>\n";
        $html .= "      <div class='card-header'>{$visual}" . htmlspecialchars($displayName) . "</div>\n";
        $html .= "      <div class='input-group'>\n";
        $html .= "          <input type='text' readonly value='" . htmlspecialchars($url) . "'>\n";
        $html .= "          <button class='copy-btn' data-url='" . htmlspecialchars($url) . "' title='Copy URL'>\n";
        $html .= "              <svg class='copy-icon' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='currentColor'><path d='M7.5 3.375c0-1.036.84-1.875 1.875-1.875h.375a3.75 3.75 0 0 1 3.75 3.75v1.875C13.5 8.16 12.66 9 11.625 9h-.375a3.75 3.75 0 0 1-3.75-3.75V3.375Zm6.188 1.875a.75.75 0 0 0-1.5 0v1.875a.75.75 0 0 0 .75.75h.375a.75.75 0 0 0 .75-.75V5.25ZM9 3.375a2.25 2.25 0 0 1 2.25-2.25h.375a2.25 2.25 0 0 1 2.25 2.25v1.875a2.25 2.25 0 0 1-2.25 2.25h-.375A2.25 2.25 0 0 1 9 5.25V3.375Z' /><path d='M12.983 9.917a.75.75 0 0 0-1.166-.825l-5.334 3.078a.75.75 0 0 0-.417.825V21a.75.75 0 0 0 .75.75h10.5a.75.75 0 0 0 .75-.75V13a.75.75 0 0 0-.417-.825l-5.333-3.078Z' /></svg>";
        $html .= "              <svg class='check-icon' style='display:none;' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='currentColor'><path fill-rule='evenodd' d='M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z' clip-rule='evenodd' /></svg>";
        $html .= "          </button>\n";
        $html .= "      </div>\n";
        $html .= "    </div>\n";
    }

    $html .= "  </div>\n</section>\n";
    return $html;
}


function generate_full_html(array $structured_data): string
{
    $universal_link_plain = GITHUB_REPO_URL . '/config.txt';
    $universal_link_b64 = GITHUB_REPO_URL . '/subscriptions/xray/base64/mix';
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PSG - Subscription Links</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg-color: #f8fafc; --card-bg: #ffffff; --text-color: #0f172a; --text-light: #64748b; --accent-color: #4f46e5; --accent-hover: #4338ca; --border-color: #e2e8f0; --shadow-color: rgba(149, 157, 165, 0.1); }
        body { margin: 0; font-family: 'Inter', sans-serif; background-color: var(--bg-color); color: var(--text-color); line-height: 1.6; -webkit-font-smoothing: antialiased; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1rem; }
        header { text-align: center; margin-bottom: 3rem; }
        h1 { font-size: 2.25rem; font-weight: 700; letter-spacing: -0.025em; margin: 0; }
        header p { font-size: 1.125rem; color: var(--text-light); margin-top: 0.5rem; }
        .main-title { font-size: 2rem; font-weight: 600; margin-top: 2rem; margin-bottom: 2rem; padding-left: 0.5rem; border-left: 4px solid var(--accent-color); }
        .category-title { font-size: 1.5rem; font-weight: 600; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .category-section { margin-bottom: 3rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }
        .card { background-color: var(--card-bg); border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 4px 12px var(--shadow-color); border: 1px solid var(--border-color); }
        .card-header { display: flex; align-items: center; gap: 0.75rem; font-weight: 500; margin-bottom: 1rem; }
        .input-group { display: flex; align-items: center; }
        .input-group input { flex-grow: 1; font-family: monospace; font-size: 0.8rem; padding: 0.6rem 0.75rem; background-color: #f1f5f9; border: 1px solid var(--border-color); border-right: none; border-radius: 0.5rem 0 0 0.5rem; outline: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .copy-btn { flex-shrink: 0; display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; background-color: #eef2ff; color: var(--accent-color); border: 1px solid var(--accent-color); border-radius: 0 0.5rem 0.5rem 0; cursor: pointer; transition: background-color 0.2s ease; }
        .copy-btn:hover { background-color: #e0e7ff; }
        .copy-icon, .check-icon { width: 20px; height: 20px; }
        .icon { width: 24px; height: 24px; color: var(--accent-color); }
        .flag { font-size: 1.5rem; line-height: 1; }
        .tag { font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.5rem; border-radius: 0.375rem; }
        .bg-sky-100 { background-color: #e0f2fe; } .text-sky-800 { color: #075985; }
        .bg-emerald-100 { background-color: #d1fae5; } .text-emerald-800 { color: #065f46; }
        .bg-blue-100 { background-color: #dbeafe; } .text-blue-800 { color: #1e40af; }
        .bg-red-100 { background-color: #fee2e2; } .text-red-800 { color: #991b1b; }
        .bg-purple-100 { background-color: #f3e8ff; } .text-purple-800 { color: #6b21a8; }
        .bg-pink-100 { background-color: #fce7f3; } .text-pink-800 { color: #9d174d; }
        .bg-yellow-100 { background-color: #fef9c3; } .text-yellow-800 { color: #854d0e; }
        .bg-slate-200 { background-color: #e2e8f0; } .text-slate-800 { color: #1e293b; }
        footer { text-align: center; margin-top: 4rem; padding: 2rem 0; border-top: 1px solid var(--border-color); color: var(--text-light); }
        footer a { color: var(--accent-color); text-decoration: none; font-weight: 500; }
        footer a:hover { text-decoration: underline; }
        @media (max-width: 640px) { h1 { font-size: 1.75rem; } .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Proxy Subscription Generator</h1>
            <p>A collection of automatically generated subscription links.</p>
        </header>
        <main>
            <section class='category-section'>
                <h2 class='category-title'><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="icon"><path d="M11.998 2.25a.75.75 0 0 1 .53 1.28l-3.25 3.25a.75.75 0 0 1-1.06 0l-1.5-1.5a.75.75 0 1 1 1.06-1.06l.97.97L11.998 2.25ZM11.25 9.75A2.25 2.25 0 1 0 13.5 12a2.25 2.25 0 0 0-2.25-2.25ZM12 7.5a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9Zm5.44 1.93a.75.75 0 1 0-1.06-1.06l-1.5 1.5a.75.75 0 1 0 1.06 1.06l1.5-1.5Zm-11.94-1.06a.75.75 0 0 0-1.06 1.06l1.5 1.5a.75.75 0 0 0 1.06-1.06l-1.5-1.5ZM12 21.75a.75.75 0 0 1 .75-.75h.008a.75.75 0 0 1 .75.75v.008a.75.75 0 0 1-.75.75h-.008a.75.75 0 0 1-.75-.75v-.008ZM7.06 17.44a.75.75 0 1 0-1.06 1.06l1.5 1.5a.75.75 0 1 0 1.06-1.06l-1.5-1.5Zm9.88 0a.75.75 0 1 0-1.06-1.06l-1.5 1.5a.75.75 0 1 0 1.06 1.06l1.5-1.5Z" /></svg> Universal Subscriptions</h2>
                <div class='grid'>
                    <div class='card'>
                      <div class='card-header'><span class='tag bg-slate-200 text-slate-800'>MIX</span>Plain Text</div>
                      <div class='input-group'>
                          <input type='text' readonly value='{$universal_link_plain}'>
                          <button class='copy-btn' data-url='{$universal_link_plain}' title='Copy URL'>
                            <svg class='copy-icon' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='currentColor'><path d='M7.5 3.375c0-1.036.84-1.875 1.875-1.875h.375a3.75 3.75 0 0 1 3.75 3.75v1.875C13.5 8.16 12.66 9 11.625 9h-.375a3.75 3.75 0 0 1-3.75-3.75V3.375Zm6.188 1.875a.75.75 0 0 0-1.5 0v1.875a.75.75 0 0 0 .75.75h.375a.75.75 0 0 0 .75-.75V5.25ZM9 3.375a2.25 2.25 0 0 1 2.25-2.25h.375a2.25 2.25 0 0 1 2.25 2.25v1.875a2.25 2.25 0 0 1-2.25 2.25h-.375A2.25 2.25 0 0 1 9 5.25V3.375Z' /><path d='M12.983 9.917a.75.75 0 0 0-1.166-.825l-5.334 3.078a.75.75 0 0 0-.417.825V21a.75.75 0 0 0 .75.75h10.5a.75.75 0 0 0 .75-.75V13a.75.75 0 0 0-.417-.825l-5.333-3.078Z' /></svg>
                            <svg class='check-icon' style='display:none;' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='currentColor'><path fill-rule='evenodd' d='M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z' clip-rule='evenodd' /></svg>
                          </button>
                      </div>
                    </div>
                    <div class='card'>
                      <div class='card-header'><span class='tag bg-slate-200 text-slate-800'>MIX</span>Base64</div>
                      <div class='input-group'>
                          <input type='text' readonly value='{$universal_link_b64}'>
                          <button class='copy-btn' data-url='{$universal_link_b64}' title='Copy URL'>
                            <svg class='copy-icon' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='currentColor'><path d='M7.5 3.375c0-1.036.84-1.875 1.875-1.875h.375a3.75 3.75 0 0 1 3.75 3.75v1.875C13.5 8.16 12.66 9 11.625 9h-.375a3.75 3.75 0 0 1-3.75-3.75V3.375Zm6.188 1.875a.75.75 0 0 0-1.5 0v1.875a.75.75 0 0 0 .75.75h.375a.75.75 0 0 0 .75-.75V5.25ZM9 3.375a2.25 2.25 0 0 1 2.25-2.25h.375a2.25 2.25 0 0 1 2.25 2.25v1.875a2.25 2.25 0 0 1-2.25 2.25h-.375A2.25 2.25 0 0 1 9 5.25V3.375Z' /><path d='M12.983 9.917a.75.75 0 0 0-1.166-.825l-5.334 3.078a.75.75 0 0 0-.417.825V21a.75.75 0 0 0 .75.75h10.5a.75.75 0 0 0 .75-.75V13a.75.75 0 0 0-.417-.825l-5.333-3.078Z' /></svg>
                            <svg class='check-icon' style='display:none;' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='currentColor'><path fill-rule='evenodd' d='M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z' clip-rule='evenodd' /></svg>
                          </button>
                      </div>
                    </div>
                </div>
            </section>
HTML;

    // --- Generate sections from structured data ---
    foreach ($structured_data as $prefix => $categories) {
        if(empty($categories)) continue;
        $html .= "<h1 class='main-title'>" . htmlspecialchars($prefix) . " Subscriptions</h1>\n";
        foreach ($categories as $type => $links) {
            $html .= generate_html_section($type, $links);
        }
    }

    $html .= <<<HTML
        </main>
        <footer>
            <p>Generated by <a href="https://github.com/itsyebekhe/PSG" target="_blank" rel="noopener noreferrer">PSG (Proxy Subscription Generator)</a>. Use at your own risk.</p>
        </footer>
    </div>
    <script>
        document.addEventListener('click', function(e) {
            const button = e.target.closest('.copy-btn');
            if (button) {
                const url = button.dataset.url;
                navigator.clipboard.writeText(url).then(() => {
                    const copyIcon = button.querySelector('.copy-icon');
                    const checkIcon = button.querySelector('.check-icon');
                    copyIcon.style.display = 'none';
                    checkIcon.style.display = 'inline-block';
                    setTimeout(() => {
                        copyIcon.style.display = 'inline-block';
                        checkIcon.style.display = 'none';
                    }, 2000);
                }).catch(err => {
                    console.error('Failed to copy URL: ', err);
                    alert('Failed to copy URL.');
                });
            }
        });
    </script>
</body>
</html>
HTML;

    return $html;
}

// --- Main Execution ---
echo "Starting modern subscription page generator..." . PHP_EOL;

$all_files = [];
foreach (SCAN_DIRECTORIES as $category => $dir) {
    echo "Scanning directory: {$dir}\n";
    $all_files[$category] = scan_directory($dir);
}

$structured_data = process_files_to_structure($all_files);
if (empty($structured_data)) {
    die("No subscription files found to generate the page. Exiting.\n");
}
$file_count = 0;
foreach($all_files as $cat_files) { $file_count += count($cat_files); }
echo "Found and categorized {$file_count} subscription files.\n";

$final_html = generate_full_html($structured_data);
file_put_contents(OUTPUT_HTML_FILE, $final_html);
echo "Successfully generated modern page at: " . OUTPUT_HTML_FILE . "\n";
