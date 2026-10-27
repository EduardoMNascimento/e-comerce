<?php
/** @var string $pageTitle */
$user      = current_user();
$cartCount = $user ? cart_count((int) $user['id']) : 0;
$fullTitle = $pageTitle !== '' ? $pageTitle . ' · ' . app_name() : app_name();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($fullTitle) ?></title>
    <link rel="stylesheet" href="<?= e(url('/assets/style.css')) ?>">
</head>
<body>
<header class="topbar">
    <div class="container topbar__inner">
        <a class="brand" href="<?= e(url('/')) ?>"><?= e(app_name()) ?></a>
        <nav class="nav" aria-label="Principal">
            <a href="<?= e(url('/')) ?>">Produtos</a>
            <?php if ($user): ?>
                <a href="<?= e(url('/cart.php')) ?>">Carrinho<?php if ($cartCount > 0): ?> <span class="pill"><?= $cartCount ?></span><?php endif; ?></a>
                <a href="<?= e(url('/orders.php')) ?>">Meus pedidos</a>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="<?= e(url('/admin/')) ?>">Admin</a>
                <?php endif; ?>
                <span class="nav__user">Olá, <?= e(explode(' ', $user['name'])[0]) ?></span>
                <form method="post" action="<?= e(url('/logout.php')) ?>" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="link-button">Sair</button>
                </form>
            <?php else: ?>
                <a href="<?= e(url('/login.php')) ?>">Entrar</a>
                <a class="btn btn--small" href="<?= e(url('/register.php')) ?>">Criar conta</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="container">
    <?php foreach (pull_flash() as $f): ?>
        <div class="alert alert--<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
