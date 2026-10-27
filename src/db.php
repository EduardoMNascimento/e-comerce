<?php
declare(strict_types=1);

/**
 * Retorna a conexão PDO (criada uma única vez por requisição).
 * Todas as consultas do projeto usam prepared statements.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $c   = config('db');
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $c['host'],
            $c['port'],
            $c['name'],
            $c['charset']
        );

        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}
