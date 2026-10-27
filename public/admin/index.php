<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';
require_admin();

$pdo = db();

$totalProducts = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE active = 1')->fetchColumn();
$outOfStock    = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE active = 1 AND stock = 0')->fetchColumn();
$pendingOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$revenue       = (string) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status <> 'cancelled'")->fetchColumn();

$lowStock = $pdo->query(
    'SELECT id, name, stock FROM products WHERE active = 1 AND stock <= 5 ORDER BY stock, name LIMIT 20'
)->fetchAll();

render_header('Administração');
?>
<h1>Administração</h1>
<?php render_admin_nav('dashboard'); ?>

<div class="stats">
    <div class="stat"><strong><?= $totalProducts ?></strong><span>Produtos ativos</span></div>
    <div class="stat"><strong><?= $outOfStock ?></strong><span>Produtos esgotados</span></div>
    <div class="stat"><strong><?= $pendingOrders ?></strong><span>Pedidos aguardando pagamento</span></div>
    <div class="stat"><strong><?= e(money($revenue)) ?></strong><span>Faturamento (exceto cancelados)</span></div>
</div>

<h2>Estoque baixo (5 unidades ou menos)</h2>
<?php if (!$lowStock): ?>
    <p class="muted">Nenhum produto com estoque baixo.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Produto</th><th class="num">Estoque</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($lowStock as $p): ?>
                <tr>
                    <td><?= e($p['name']) ?></td>
                    <td class="num">
                        <span class="badge <?= (int) $p['stock'] === 0 ? 'badge--out' : 'badge--low' ?>"><?= (int) $p['stock'] ?></span>
                    </td>
                    <td class="num"><a href="<?= e(url('/admin/stock.php?id=' . (int) $p['id'])) ?>">Repor estoque</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php render_footer(); ?>
