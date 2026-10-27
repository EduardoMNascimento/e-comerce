<?php
declare(strict_types=1);

/* ------------------------------------------------------------------
 * CSRF
 * ----------------------------------------------------------------*/

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Deve ser chamada no início de todo tratamento de formulário (POST). */
function csrf_check(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Sessão expirada ou token inválido. Volte à página anterior, recarregue e tente novamente.');
    }
}

/* ------------------------------------------------------------------
 * Sessão / usuário autenticado
 * ----------------------------------------------------------------*/

/** Usuário logado (id, name, email, role) ou null. Consulta o banco uma vez por requisição. */
function current_user(): ?array
{
    static $user = false;

    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $st = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
            $st->execute([(int) $_SESSION['user_id']]);
            $user = $st->fetch() ?: null;
            if ($user === null) {
                unset($_SESSION['user_id']);
            }
        }
    }

    return $user;
}

/** Exige login. Guarda para onde voltar depois de autenticar. */
function require_login(?string $returnTo = null): array
{
    $user = current_user();
    if ($user === null) {
        $_SESSION['intended'] = safe_path($returnTo ?? current_path(), '/');
        flash('warning', 'Faça login para continuar.');
        redirect('/login.php');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('Acesso negado: esta área é restrita a administradores.');
    }
    return $user;
}

function login_user(int $userId): void
{
    session_regenerate_id(true); // evita fixação de sessão
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void
{
    unset($_SESSION['user_id'], $_SESSION['intended']);
    session_regenerate_id(true);
}
