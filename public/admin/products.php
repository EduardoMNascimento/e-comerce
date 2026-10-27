<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';
require_admin();

// Ativar/desativar produto (produtos com histórico de vendas não são apagados)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $id = input_int($_POST, 'product_id');
    if ($id !== null && input_str($_POST, 'action') === 'toggle') {
        $st = db()->prepare('UPDATE products SET active = 1 - active WHERE id = ?');
        $st->execute([$id]);
        flash('success', 'Visibilidade do produto atualizada.');
    }
    redirect('/admin/products.php');
}

$products = db()->query(
    'SELECT id, name, price, stock, active FROM products ORDER BY active DESC, name'
)->fetchAll();

render_header('Produtos e estoque');
?>
<h1>Produtos e estoque</h1>
<?php render_admin_nav('products'); ?>

<p><a class="btn" href="<?= e(url('/admin/product_form.php')) ?>">+ Novo produto</a></p>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Produto</th>
                <th class="num">Preço</th>
                <th class="num">Estoque</th>
                <th>Situação</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $p):
            $stock = (int) $p['stock'];
            ?>
            <tr>
                <td><?= e($p['name']) ?></td>
                <td class="num"><?= e(money($p['price'])) ?></td>
                <td class="num">
                    <span class="badge <?= $stock === 0 ? 'badge--out' : ($stock <= 5 ? 'badge--low' : 'badge--ok') ?>"><?= $stock ?></span>
                </td>
                <td>
                    <?= (int) $p['active']
                        ? '<span class="badge badge--ok">Visível</span>'
                        : '<span class="badge badge--muted">Oculto</span>' ?>
                </td>
                <td class="num">
                    <div class="actions">
                        <a href="<?= e(url('/admin/product_form.php?id=' . (int) $p['id'])) ?>">Editar</a>
                        <a href="<?= e(url('/admin/stock.php?id=' . (int) $p['id'])) ?>">Estoque</a>
                        <form method="post" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="link-button"><?= (int) $p['active'] ? 'Ocultar' : 'Exibir' ?></button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php render_footer(); ?>
