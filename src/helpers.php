<?php
declare(strict_types=1);

/* ------------------------------------------------------------------
 * Configuração e URLs
 * ----------------------------------------------------------------*/

/** Lê a configuração com notação de pontos: config('db.host'). */
function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function app_name(): string
{
    return (string) config('app.name', 'Loja');
}

function base_path(): string
{
    return rtrim((string) config('app.base_path', ''), '/');
}

/** Monta uma URL interna respeitando a subpasta configurada. */
function url(string $path = '/'): string
{
    return base_path() . $path;
}

/** Caminho atual da requisição, sem o prefixo da subpasta. */
function current_path(): string
{
    $uri  = $_SERVER['REQUEST_URI'] ?? '/';
    $base = base_path();
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
    return $uri === '' ? '/' : $uri;
}

/** Aceita apenas caminhos internos (evita open redirect). */
function safe_path(?string $path, string $fallback = '/'): string
{
    if (
        is_string($path)
        && $path !== ''
        && $path[0] === '/'
        && !str_starts_with($path, '//')
        && !str_contains($path, '\\')
        && !preg_match('/[\r\n]/', $path)
    ) {
        return $path;
    }
    return $fallback;
}

function redirect(string $to): never
{
    if ($to !== '' && $to[0] === '/') {
        $to = url($to);
    }
    header('Location: ' . $to);
    exit;
}

/* ------------------------------------------------------------------
 * Saída segura e mensagens
 * ----------------------------------------------------------------*/

function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Valor anterior de um campo de formulário (já escapado). */
function old(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return e(is_string($v) ? $v : $default);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ------------------------------------------------------------------
 * Entrada, valores monetários e formatação
 * ----------------------------------------------------------------*/

/** Lê um inteiro de $_POST/$_GET; retorna null se ausente ou inválido. */
function input_int(array $source, string $key): ?int
{
    $v = $source[$key] ?? null;
    if (!is_string($v) && !is_int($v)) {
        return null;
    }
    $r = filter_var($v, FILTER_VALIDATE_INT);
    return $r === false ? null : $r;
}

function input_str(array $source, string $key): string
{
    $v = $source[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

/**
 * Converte "1.234,56", "1234,56" ou "1234.56" em "1234.56".
 * Retorna null se o valor for inválido.
 */
function parse_price(string $raw): ?string
{
    $raw = trim(str_replace(['R$', ' '], '', $raw));

    if (preg_match('/^\d{1,3}(\.\d{3})+,\d{1,2}$/', $raw) || preg_match('/^\d+,\d{1,2}$/', $raw)) {
        $raw = str_replace(',', '.', str_replace('.', '', $raw));
    } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $raw)) {
        return null;
    }

    $value = (float) $raw;
    if ($value > 99999999.99) {
        return null;
    }
    return number_format($value, 2, '.', '');
}

/** Converte um DECIMAL em string ("49.90") para centavos inteiros (4990). */
function to_cents(string|float $decimal): int
{
    return (int) round(((float) $decimal) * 100);
}

function cents_to_decimal(int $cents): string
{
    return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
}

function money(string|int|float $value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function fmt_date(string $timestamp): string
{
    $t = strtotime($timestamp);
    return $t === false ? $timestamp : date('d/m/Y H:i', $t);
}

function order_status_label(string $status): string
{
    return match ($status) {
        'pending'   => 'Aguardando pagamento',
        'paid'      => 'Pago',
        'shipped'   => 'Enviado',
        'cancelled' => 'Cancelado',
        default     => $status,
    };
}

/* ------------------------------------------------------------------
 * Layout
 * ----------------------------------------------------------------*/

function render_header(string $pageTitle = ''): void
{
    require __DIR__ . '/views/header.php';
}

function render_footer(): void
{
    require __DIR__ . '/views/footer.php';
}

function render_admin_nav(string $active): void
{
    $links = [
        'dashboard' => ['/admin/', 'Resumo'],
        'products'  => ['/admin/products.php', 'Produtos e estoque'],
        'orders'    => ['/admin/orders.php', 'Pedidos'],
    ];
    echo '<nav class="subnav" aria-label="Administração">';
    foreach ($links as $key => [$href, $label]) {
        $class = $key === $active ? ' class="active"' : '';
        echo '<a' . $class . ' href="' . e(url($href)) . '">' . e($label) . '</a>';
    }
    echo '</nav>';
}
