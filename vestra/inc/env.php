<?php
/**
 * Minimal .env loader.
 * Reads KEY=value lines from the .env file stored one level above
 * the document root (not web-accessible). Call is idempotent.
 */
if (!function_exists('vestra_load_env')) {
    function vestra_load_env(string $path): void {
        if (!is_readable($path)) return;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k); $v = trim($v, " \t\"'");
            if ($k !== '' && !array_key_exists($k, $_ENV)) {
                $_ENV[$k] = $v;
                putenv("$k=$v");
            }
        }
    }
    // Two levels up from inc/ = one level above public_html/ (home dir on server)
    vestra_load_env(dirname(__DIR__, 2) . '/.env');
}
