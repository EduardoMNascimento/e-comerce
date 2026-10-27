<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $orderId = input_int($_POST, 'order_id');
    $status  = input_str($_POST, 'status');

    try {
        if ($orderId === null) {
            throw new ShopException('Pedido inválido.');
        }
        change_order_status($orderId, $status, (int) $admin['id']);
        flash('success', 'Pedido #' . $orderId . ' atualizado.');
    } catch (ShopException $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect('/admin/orders.php');
}

$orders = attach_order_items(db()->query(
    'SELECT o.id, o.total, o.status, o.created_at, u.name AS customer, u.email
       FROM orders o
       JOIN users u ON u.id = o.user_id
      ORDER BY o.id DESC
      LIMIT 100'
)->fetchAll());

$statuses = ['pending', 'paid', 'shipped', 'cancelled'];

render_header('Pedidos');
?>
<h1>Pedidos</h1>
<?php render_admin_nav('orders'); ?>

<?php if (!$orders): ?>
    <div class="empty">Nenhum pedido ainda.</div>
<?php else: ?>
    <?php foreach ($orders as $order): ?>
        <section class="card order">
            <div class="order__head">
                <strong>Pedido #<?= (int) $order['id'] ?></strong>
                <span><?= e($order['customer']) ?> <span class="muted">(<?= e($order['email']) ?>)</span></span>
                <span class="muted"><?= e(fmt_date($order['created_at'])) ?></span>
            </div>
            <ul>
                <?php foreach ($order['items'] as $item): ?>
                    <li><?= (int) $item['quantity'] ?> × <?= e($item['product_name']) ?> — <?= e(money($item['unit_price'])) ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="summary">
                <strong>Total: <?= e(money($order['total'])) ?></strong>

                <?php if ($order['status'] === 'cancelled'): ?>
                    <span class="badge badge--out">Cancelado</span>
                <?php else: ?>
                    <form method="post" class="qty-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                        <select name="status" aria-label="Status do pedido #<?= (int) $order['id'] ?>">
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?= e($s) ?>" <?= $s === $order['status'] ? 'selected' : '' ?>><?= e(order_status_label($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn--secondary btn--small">Salvar</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if ($order['status'] !== 'cancelled'): ?>
                <small class="muted">Ao cancelar, os itens voltam automaticamente ao estoque e o pedido não poderá ser reaberto.</small>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
<?php render_footer(); ?>
