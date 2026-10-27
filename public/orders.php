<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$user   = require_login();
$orders = user_orders((int) $user['id']);

render_header('Meus pedidos');
?>
<h1>Meus pedidos</h1>

<?php if (!$orders): ?>
    <div class="empty">
        <p>Você ainda não fez nenhum pedido.</p>
        <a class="btn" href="<?= e(url('/')) ?>">Ver produtos</a>
    </div>
<?php else: ?>
    <?php foreach ($orders as $order): ?>
        <section class="card order">
            <div class="order__head">
                <strong>Pedido #<?= (int) $order['id'] ?></strong>
                <span class="muted"><?= e(fmt_date($order['created_at'])) ?></span>
                <span class="badge <?= $order['status'] === 'cancelled' ? 'badge--out' : 'badge--ok' ?>">
                    <?= e(order_status_label($order['status'])) ?>
                </span>
            </div>
            <ul>
                <?php foreach ($order['items'] as $item): ?>
                    <li>
                        <?= (int) $item['quantity'] ?> × <?= e($item['product_name']) ?>
                        — <?= e(money($item['unit_price'])) ?> cada
                    </li>
                <?php endforeach; ?>
            </ul>
            <p><strong>Total: <?= e(money($order['total'])) ?></strong></p>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
<?php render_footer(); ?>
