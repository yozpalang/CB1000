<?php

declare(strict_types=1);

/**
 * Subscription Page Generator for PSG
 *
 * This script scans the 'subscriptions' and 'lite/subscriptions' directories,
 * finds all generated subscription files, and creates a clean, user-friendly
 * index.html page with categorized links and copy-to-clipboard functionality.
 */

// --- Configuration ---
define('PROJECT_ROOT', __DIR__);
define('GITHUB_REPO_URL', 'https://raw.githubusercontent.com/itsyebekhe/PSG/main');
define('OUTPUT_HTML_FILE', PROJECT_ROOT . '/index.html');
define('SCAN_DIRECTORIES', [
    'Standard' => PROJECT_ROOT . '/subscriptions',
    'Lite' => PROJECT_ROOT . '/lite/subscriptions',
]);

// --- Helper Functions ---

/**
 * Recursively scans a directory for files and returns their relative paths.
 */
function scan_directory(string $dir, string $prefix = ''): array
{
    if (!is_dir($dir)) return [];

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $ignoreExtensions = ['php', 'md', 'json', 'yml', 'yaml', 'ini'];

    foreach ($iterator as $file) {
        if ($file->isFile() && !in_array($file->getExtension(), $ignoreExtensions)) {
            $relativePath = str_replace(PROJECT_ROOT . '/', '', $file->getRealPath());
            $files[] = $relativePath;
        }
    }
    return $files;
}

/**
 * Processes a list of file paths into a structured, categorized array.
 */
function process_files_to_structure(array $files): array
{
    $structure = [];
    foreach ($files as $category => $paths) {
        foreach ($paths as $path) {
            $parts = explode('/', $path);
            if (count($parts) < 2) continue; // Skip root files like config.txt

            $type = $parts[count($parts) - 2]; // e.g., 'clash', 'singbox', 'location'
            $name = pathinfo($path, PATHINFO_FILENAME);
            $url = GITHUB_REPO_URL . '/' . $path;

            $structure[$category][$type][$name] = $url;
        }
    }
    // Sort categories alphabetically
    foreach ($structure as &$categories) {
        ksort($categories);
    }
    return $structure;
}

/**
 * Generates the HTML for a single section of links.
 */
function generate_html_section(string $title, array $links): string
{
    $html = "<section class='category-section'>\n";
    $html .= "  <h2>" . htmlspecialchars(ucfirst($title)) . " Subscriptions</h2>\n";
    $html .= "  <div class='grid'>\n";

    foreach ($links as $name => $url) {
        $displayName = ucwords(str_replace(['-', '_'], ' ', $name));
        $html .= "    <div class='card'>\n";
        $html .= "      <span class='card-title'>" . htmlspecialchars($displayName) . "</span>\n";
        $html .= "      <button class='copy-btn' data-url='" . htmlspecialchars($url) . "'>Copy URL</button>\n";
        $html .= "    </div>\n";
    }

    $html .= "  </div>\n</section>\n";
    return $html;
}

/**
 * Generates the full HTML page content.
 */
function generate_full_html(array $structured_data): string
{
    $universal_link = GITHUB_REPO_URL . '/config.txt';

    // --- HTML Header, CSS, and JavaScript ---
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PSG - Subscription Links</title>
    <style>
        :root { --bg-color: #f8fafc; --card-bg: #ffffff; --text-color: #0f172a; --text-light: #64748b; --accent-color: #4f46e5; --accent-hover: #4338ca; --border-color: #e2e8f0; --shadow-color: rgba(149, 157, 165, 0.1); }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: var(--bg-color); color: var(--text-color); line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem; }
        header { text-align: center; margin-bottom: 3rem; }
        h1 { font-size: 2.5rem; font-weight: 700; margin: 0; }
        header p { font-size: 1.125rem; color: var(--text-light); margin-top: 0.5rem; }
        h2 { font-size: 1.75rem; font-weight: 600; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem; margin-top: 3rem; margin-bottom: 1.5rem; }
        .category-section { margin-bottom: 2rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
        .card { background-color: var(--card-bg); border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 4px 12px var(--shadow-color); border: 1px solid var(--border-color); display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px var(--shadow-color); }
        .card-title { font-weight: 500; margin-bottom: 1rem; }
        .copy-btn { font-size: 0.875rem; font-weight: 600; color: var(--accent-color); background-color: transparent; border: 2px solid var(--accent-color); border-radius: 0.5rem; padding: 0.6rem 1rem; cursor: pointer; text-align: center; transition: background-color 0.2s ease, color 0.2s ease; }
        .copy-btn:hover { background-color: var(--accent-color); color: white; }
        .copy-btn.copied { background-color: #16a34a; color: white; border-color: #16a34a; }
        footer { text-align: center; margin-top: 4rem; padding-top: 2rem; border-top: 1px solid var(--border-color); color: var(--text-light); }
        a { color: var(--accent-color); text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Proxy Subscription Generator (PSG)</h1>
            <p>A collection of automatically generated and processed subscription links.</p>
        </header>

        <main>
            <section class='category-section'>
                <h2>Universal Subscription</h2>
                <div class='grid'>
                    <div class='card'>
                      <span class='card-title'>All Configs (Plain Text)</span>
                      <button class='copy-btn' data-url='{$universal_link}'>Copy URL</button>
                    </div>
                </div>
            </section>
HTML;

    // --- Generate sections from structured data ---
    foreach ($structured_data as $prefix => $categories) {
        $html .= "<h1>" . htmlspecialchars($prefix) . " Subscriptions</h1>\n";
        foreach ($categories as $type => $links) {
            $html .= generate_html_section($type, $links);
        }
    }

    // --- HTML Footer and Closing Tags ---
    $html .= <<<HTML
        </main>
        <footer>
            <p>Generated by <a href="https://github.com/itsyebekhe/PSG" target="_blank" rel="noopener noreferrer">PSG</a>. Use at your own risk.</p>
        </footer>
    </div>
    <script>
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('copy-btn')) {
                const button = e.target;
                const url = button.dataset.url;
                navigator.clipboard.writeText(url).then(() => {
                    const originalText = button.textContent;
                    button.textContent = 'Copied!';
                    button.classList.add('copied');
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.classList.remove('copied');
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

echo "Starting subscription page generator..." . PHP_EOL;

// 1. Scan all relevant directories
$all_files = [];
foreach (SCAN_DIRECTORIES as $category => $dir) {
    echo "Scanning directory: {$dir}\n";
    $all_files[$category] = scan_directory($dir);
}

// 2. Process file paths into a structured array
$structured_data = process_files_to_structure($all_files);
if (empty($structured_data)) {
    die("No subscription files found to generate the page. Exiting.\n");
}
echo "Found and categorized " . (count($all_files['Standard'], COUNT_RECURSIVE) -1) . " subscription files.\n";

// 3. Generate the full HTML content
$final_html = generate_full_html($structured_data);

// 4. Write the HTML to the output file
file_put_contents(OUTPUT_HTML_FILE, $final_html);

echo "Successfully generated main page at: " . OUTPUT_HTML_FILE . "\n";
