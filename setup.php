<?php

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

define('SETUP_TOKEN', '29cc6e81284676d3d5807e6d6d7c8798af4e7f8a');
define('HT_BEGIN', '# BEGIN ScrapingVisitor');
define('HT_END', '# END ScrapingVisitor');

function setup_json($ok, $message, $extra = array(), $code = 200)
{
    $GLOBALS['setup_done'] = true;
    http_response_code($code);
    echo json_encode(array_merge(array(
        'ok' => (bool) $ok,
        'message' => (string) $message,
    ), $extra));
    exit;
}

function setup_ends_with($haystack, $needle)
{
    $needle = (string) $needle;
    $haystack = (string) $haystack;
    $len = strlen($needle);

    return $len === 0 || substr($haystack, -$len) === $needle;
}

function setup_contains($haystack, $needle)
{
    return strpos((string) $haystack, (string) $needle) !== false;
}

function setup_starts_with($haystack, $needle)
{
    $needle = (string) $needle;

    return $needle === '' || substr((string) $haystack, 0, strlen($needle)) === $needle;
}

set_error_handler(function ($severity, $message) {
    if (! ($severity & (E_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR))) {
        return false;
    }
    setup_json(false, 'PHP error: '.$message, array(), 500);
});

register_shutdown_function(function () {
    if (! empty($GLOBALS['setup_done'])) {
        return;
    }
    $err = error_get_last();
    if ($err && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        if (! headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        http_response_code(500);
        echo json_encode(array(
            'ok' => false,
            'message' => 'Fatal: '.$err['message'],
        ));
    }
});

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    setup_json(false, 'POST only', array('ready' => true), 405);
}

$token = (string) (isset($_POST['token']) ? $_POST['token'] : (isset($_SERVER['HTTP_X_SETUP_TOKEN']) ? $_SERVER['HTTP_X_SETUP_TOKEN'] : ''));
if ($token === '' || ! hash_equals(SETUP_TOKEN, $token)) {
    setup_json(false, 'Token setup salah.', array(), 403);
}

$file = isset($_FILES['zip']) ? $_FILES['zip'] : null;
$uploadError = is_array($file) ? (int) (isset($file['error']) ? $file['error'] : UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
if (! is_array($file) || $uploadError !== UPLOAD_ERR_OK) {
    $uploadHints = array(
        UPLOAD_ERR_INI_SIZE => 'ZIP terlalu besar (upload_max_filesize).',
        UPLOAD_ERR_FORM_SIZE => 'ZIP terlalu besar (MAX_FILE_SIZE).',
        UPLOAD_ERR_PARTIAL => 'Upload ZIP terputus.',
        UPLOAD_ERR_NO_FILE => 'File ZIP tidak diterima.',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder tmp PHP tidak ada.',
        UPLOAD_ERR_CANT_WRITE => 'PHP gagal menulis file upload.',
        UPLOAD_ERR_EXTENSION => 'Ekstensi PHP memblokir upload.',
    );
    setup_json(false, isset($uploadHints[$uploadError]) ? $uploadHints[$uploadError] : 'File ZIP tidak diterima.', array(), 400);
}

$tmp = (string) (isset($file['tmp_name']) ? $file['tmp_name'] : '');
$name = strtolower((string) (isset($file['name']) ? $file['name'] : ''));
if ($tmp === '' || ! is_file($tmp) || ! setup_ends_with($name, '.zip')) {
    setup_json(false, 'Upload harus berupa .zip', array(), 400);
}

if (! class_exists('ZipArchive')) {
    setup_json(false, 'ZipArchive tidak aktif di PHP.', array(), 500);
}

$root = __DIR__;
$zip = new ZipArchive;
if ($zip->open($tmp) !== true) {
    setup_json(false, 'ZIP tidak bisa dibuka.', array(), 400);
}

$extracted = 0;
$htaccessSnippet = '';

for ($i = 0; $i < $zip->numFiles; $i++) {
    $entry = (string) $zip->getNameIndex($i);
    $norm = str_replace('\\', '/', $entry);
    if ($norm === '' || setup_ends_with($norm, '/')) {
        continue;
    }
    if (setup_contains($norm, '..')) {
        continue;
    }

    $base = strtolower(basename($norm));
    if ($base === 'setup.php' || $base === '.htaccess') {
        continue;
    }

    $target = $root.'/'.ltrim($norm, '/');
    $rootReal = str_replace('\\', '/', (string) realpath($root));
    $targetNorm = str_replace('\\', '/', $target);
    if ($rootReal === '' || ! setup_starts_with($targetNorm, $rootReal.'/')) {
        continue;
    }

    $dir = dirname($target);
    if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
        $zip->close();
        setup_json(false, 'Gagal membuat folder.', array(), 500);
    }

    $contents = $zip->getFromIndex($i);
    if ($contents === false) {
        continue;
    }

    if ($base === 'htaccess.txt' && ! setup_contains($norm, '/')) {
        $htaccessSnippet = (string) $contents;
        continue;
    }

    if (file_put_contents($target, $contents) === false) {
        $zip->close();
        setup_json(false, 'Gagal menulis '.$norm, array(), 500);
    }

    $extracted++;
}

$zip->close();

$htaccessMerged = false;
$htaccessCreated = false;
if ($htaccessSnippet !== '') {
    $htPath = $root.'/.htaccess';
    $htaccessCreated = ! is_file($htPath);
    $existing = $htaccessCreated ? '' : (string) file_get_contents($htPath);
    $merged = merge_scraping_htaccess($existing, $htaccessSnippet);
    if (file_put_contents($htPath, $merged) === false) {
        setup_json(false, 'Gagal menulis .htaccess', array(), 500);
    }
    $htaccessMerged = true;
}

$done = array(
    'ZIP diterima',
    $extracted.' file diextract ke root domain',
);
if ($htaccessCreated) {
    $done[] = '.htaccess belum ada, dibuat dari htaccess.txt';
} elseif ($htaccessMerged) {
    $done[] = 'htaccess.txt disisipkan ke .htaccess yang sudah ada (rule lama tetap)';
} else {
    $done[] = 'htaccess.txt tidak ada, .htaccess tidak diubah';
}

setup_json(true, $htaccessCreated
    ? 'ZIP diextract ('.$extracted.' file) dan .htaccess dibuat dari htaccess.txt.'
    : ($htaccessMerged
        ? 'ZIP diextract ('.$extracted.' file) dan aturan htaccess.txt disisipkan ke .htaccess.'
        : 'ZIP diextract ('.$extracted.' file). htaccess.txt tidak ada, .htaccess tidak diubah.'), array(
    'files' => $extracted,
    'htaccess' => $htaccessMerged,
    'htaccess_created' => $htaccessCreated,
    'done' => $done,
));

function merge_scraping_htaccess($existing, $snippet)
{
    $snippet = trim((string) $snippet);
    if ($snippet === '') {
        return $existing;
    }

    $cleaned = (string) $existing;
    if (setup_contains($cleaned, HT_BEGIN) && setup_contains($cleaned, HT_END)) {
        $cleaned = (string) preg_replace(
            '/'.preg_quote(HT_BEGIN, '/').'.*?'.preg_quote(HT_END, '/').'\s*/s',
            '',
            $cleaned
        );
    }

    return HT_BEGIN."\n".$snippet."\n".HT_END."\n\n".ltrim($cleaned);
}
