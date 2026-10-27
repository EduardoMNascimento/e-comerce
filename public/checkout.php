<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/cart.php');
}

csrf_check();
$user = require_login('/cart.php');

try {
    $orderId = place_order((int) $user['id']);
    flash('success', 'Pedido #' . $orderId . ' realizado com sucesso! O estoque já foi atualizado.');
    redirect('/orders.php');
} catch (ShopException $ex) {
    flash('danger', $ex->getMessage());
    redirect('/cart.php');
}
