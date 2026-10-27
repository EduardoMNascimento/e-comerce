<?php
declare(strict_types=1);

/**
 * Inicialização comum a todas as páginas: configuração, sessão e funções.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0'); // erros vão para o log do PHP (console do `php -S`)
date_default_timezone_set('America/Sao_Paulo');

$GLOBALS['config'] = (static function (): array {
    $config = require __DIR__ . '/../config/config.php';
    $local  = __DIR__ . '/../config/config.local.php';
    if (is_file($local)) {
        $config = array_replace_recursive($config, require $local);
    }
    return $config;
})();

set_exception_handler(static function (Throwable $e): void {
    error_log((string) $e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<!doctype html><meta charset="utf-8"><title>Erro</title>'
       . '<p style="font-family:sans-serif;padding:2rem">Ocorreu um erro inesperado. '
       . 'Verifique se o banco de dados está criado e configurado e tente novamente.</p>';
});

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/shop.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();
