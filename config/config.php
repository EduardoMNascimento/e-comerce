<?php
declare(strict_types=1);

/**
 * Configuração padrão. Pode ser sobrescrita de duas formas:
 *   1) variáveis de ambiente (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS);
 *   2) arquivo config/config.local.php (ignorado pelo Git), que retorna um array
 *      com as chaves que você quiser trocar — veja config.local.example.php.
 */
return [
    'app' => [
        'name'      => 'Loja PHP',
        // Use se o projeto roda em uma subpasta (ex.: XAMPP → "/ecommerce-php/public").
        // Deixe vazio ao usar `php -S localhost:8000 -t public`.
        'base_path' => rtrim((string) (getenv('APP_BASE_PATH') ?: ''), '/'),
    ],
    'db' => [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'port'    => getenv('DB_PORT') ?: '3306',
        'name'    => getenv('DB_NAME') ?: 'ecommerce',
        'user'    => getenv('DB_USER') ?: 'root',
        'pass'    => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
];
