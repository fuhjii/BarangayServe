<?php
/**
 * router.php: ONLY for PHP's built-in test server.
 *
 *     php -S localhost:8000 router.php
 *
 * The built-in server ignores the .htaccess files, so without this file a visitor could
 * download database/barangayserve.sql or read source files. This router applies the same
 * rules as the .htaccess files. Apache (XAMPP) does not need this file.
 */
$path = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));

$blockedFolder = preg_match('#^/(config|includes|database|storage|tests|tools|docs)(/|$)#', $path) === 1;
$blockedFile   = preg_match('#(^|/)\.[^/]#', $path) === 1                       // .git, .htaccess, .env ...
              || preg_match('#\.(sql|md|log|bak|orig|dist|sh|ini|lock|json)$#i', $path) === 1
              || basename($path) === 'router.php';

if ($blockedFolder || $blockedFile) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    return true;
}
return false;   // let the built-in server handle the request normally
