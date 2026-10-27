<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$q      = input_str($_GET, 'q');
$sql    = 'SELECT id, name, description, price, stock, image_url FROM products WHERE active = 1';
$params = [];

if ($q !== '') {
    $like    = '%' . addcslashes($q, '%_\\') . '%';
    $sql    .= ' AND (name LIKE ? OR description LIKE ?)';
    $params  = [$like, $like];
}
$sql .= ' ORDER BY name';

$st = db()->prepare($sql);
$st->execute($params);
$products = $st->fetchAll();

render_header('Produtos');
?>
<h1>Produtos</h1>

<form method="get" class="search" role="search">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar produtos…" aria-label="Buscar produtos">
    <button type="submit" class="btn">Buscar</button>
</form>

<?php if (!$products): ?>
    <div class="empty">Nenhum produto encontrado.</div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($products as $p):
            $stock = (int) $p['stock'];
            $img   = (string) $p['image_url'];
            ?>
            <article class="product">
                <div class="product__image" aria-hidden="true">
                    <?php if ($img !== ''): ?>
                        <img src="<?= e($img) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <?= e(mb_strtoupper(mb_substr($p['name'], 0, 1, 'UTF-8'), 'UTF-8')) ?>
                    <?php endif; ?>
                </div>
                <div class="product__body">
                    <h2 class="product__name"><?= e($p['name']) ?></h2>
                    <p class="product__desc"><?= e($p['description']) ?></p>
                    <div class="product__price"><?= e(money($p['price'])) ?></div>

                    <?php if ($stock === 0): ?>
                        <span class="badge badge--out">Esgotado</span>
                    <?php elseif ($stock <= 5): ?>
                        <span class="badge badge--low">Restam apenas <?= $stock ?></span>
                    <?php else: ?>
                        <span class="badge badge--ok">Em estoque</span>
                    <?php endif; ?>

                    <?php if ($stock > 0): ?>
                        <form method="post" action="<?= e(url('/cart.php')) ?>" class="product__buy">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                            <input type="hidden" name="next" value="<?= e(current_path()) ?>">
                            <input type="number" name="quantity" value="1" min="1" max="<?= $stock ?>" aria-label="Quantidade">
                            <button type="submit" class="btn">Adicionar</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php render_footer(); ?>
