<?php
declare(strict_types=1);

// Copie este arquivo para config/config.local.php e ajuste os valores.
// O config.local.php não vai para o GitHub (está no .gitignore).
return [
    'app' => [
        // Só é necessário se o projeto estiver em uma subpasta do servidor (ex.: XAMPP).
        // Com `php -S localhost:8000 -t public`, deixe vazio.
        'base_path' => '',
        // 'base_path' => '/ecommerce-php/public',
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'ecommerce',
        'user' => 'root',
        'pass' => 'sua_senha_aqui',
    ],
];
