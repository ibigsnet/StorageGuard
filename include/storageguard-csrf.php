<?php
/**
 * CSRF check for Storage Guard POST endpoints. Fails closed.
 * Unraid's local_prepend.php checks csrf_token on every POST, then removes it
 * from $_POST, so the submitted value is also read from the raw form body.
 */
function storageguard_csrf_expected(): string {
    if (!is_readable('/var/local/emhttp/var.ini')) {
        return '';
    }
    $vi = @parse_ini_file('/var/local/emhttp/var.ini');
    return is_array($vi) ? (string)($vi['csrf_token'] ?? '') : '';
}

function storageguard_csrf_submitted(): string {
    if (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
        return $_POST['csrf_token'];
    }
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    if (function_exists('getallheaders')) {
        foreach ((array)getallheaders() as $k => $v) {
            if (strtolower((string)$k) === 'x-csrf-token') {
                return (string)$v;
            }
        }
    }
    $type = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strpos($type, 'application/x-www-form-urlencoded') === 0) {
        $raw = (string)@file_get_contents('php://input', false, null, 0, 1048576);
        $body = [];
        parse_str($raw, $body);
        if (isset($body['csrf_token']) && is_string($body['csrf_token'])) {
            return $body['csrf_token'];
        }
    }
    return '';
}

function storageguard_csrf_ok(): bool {
    $expected = storageguard_csrf_expected();
    $got = storageguard_csrf_submitted();
    return $expected !== '' && $got !== '' && hash_equals($expected, $got);
}
