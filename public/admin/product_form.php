<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';
$admin = require_admin();

$id      = input_int($_GET, 'id');
$product = null;

if ($id !== null) {
    $st = db()->prepare('SELECT * FROM products WHERE id = ?');
    $st->execute([$id]);
    $product = $st->fetch();
    if (!$product) {
        http_response_code(404);
        exit('Produto não encontrado.');
    }
}

$errors = [];
$values = [
    'name'        => $product['name'] ?? '',
    'description' => $product['description'] ?? '',
    'price'       => $product ? number_format((float) $product['price'], 2, ',', '') : '',
    'stock'       => '0',
    'image_url'   => $product['image_url'] ?? '',
    'active'      => $product ? (bool) $product['active'] : true,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $values['name']        = input_str($_POST, 'name');
    $values['description'] = input_str($_POST, 'description');
    $values['price']       = input_str($_POST, 'price');
    $values['stock']       = input_str($_POST, 'stock');
    $values['image_url']   = input_str($_POST, 'image_url');
    $values['active']      = isset($_POST['active']);

    $nameLength = mb_strlen($values['name'], 'UTF-8');
    if ($nameLength < 2 || $nameLength > 150) {
        $errors[] = 'O nome deve ter de 2 a 150 caracteres.';
    }
    if (mb_strlen($values['description'], 'UTF-8') > 2000) {
        $errors[] = 'A descrição pode ter no máximo 2000 caracteres.';
    }

    $price = parse_price($values['price']);
    if ($price === null) {
        $errors[] = 'Informe um preço válido (ex.: 49,90).';
    }

    $stock = 0;
    if ($product === null) { // o estoque de produtos existentes é alterado na tela "Estoque"
        $stockInput = filter_var($values['stock'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
        if ($stockInput === false) {
            $errors[] = 'O estoque inicial deve ser um número inteiro entre 0 e 1.000.000.';
        } else {
            $stock = $stockInput;
        }
    }

    if ($values['image_url'] !== '') {
        $scheme = strtolower((string) parse_url($values['image_url'], PHP_URL_SCHEME));
        if (
            strlen($values['image_url']) > 255
            || !filter_var($values['image_url'], FILTER_VALIDATE_URL)
            || !in_array($scheme, ['http', 'https'], true)
        ) {
            $errors[] = 'A URL da imagem deve começar com http:// ou https:// (máx. 255 caracteres).';
        }
    }

    if (!$errors) {
        $pdo = db();
        $imageUrl = $values['image_url'] !== '' ? $values['image_url'] : null;
        $active   = $values['active'] ? 1 : 0;

        if ($product === null) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    'INSERT INTO products (name, description, price, stock, image_url, active) VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([$values['name'], $values['description'], $price, $stock, $imageUrl, $active]);
                $newId = (int) $pdo->lastInsertId();

                if ($stock > 0) {
                    $pdo->prepare(
                        'INSERT INTO stock_movements (product_id, user_id, delta, reason, note) VALUES (?, ?, ?, ?, ?)'
                    )->execute([$newId, (int) $admin['id'], $stock, 'initial', 'Estoque inicial']);
                }
                $pdo->commit();
            } catch (Throwable $ex) {
                $pdo->rollBack();
                throw $ex;
            }
            flash('success', 'Produto criado.');
        } else {
            $pdo->prepare(
                'UPDATE products SET name = ?, description = ?, price = ?, image_url = ?, active = ? WHERE id = ?'
            )->execute([$values['name'], $values['description'], $price, $imageUrl, $active, $product['id']]);
            flash('success', 'Produto atualizado.');
        }

        redirect('/admin/products.php');
    }
}

$title = $product ? 'Editar produto' : 'Novo produto';
render_header($title);
?>
<h1><?= e($title) ?></h1>
<?php render_admin_nav('products'); ?>

<form method="post" class="card form form--wide" novalidate>
    <?= csrf_field() ?>
    <?php if ($errors): ?>
        <ul class="errors">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="field">
        <label for="name">Nome</label>
        <input type="text" id="name" name="name" value="<?= e($values['name']) ?>" required maxlength="150">
    </div>
    <div class="field">
        <label for="description">Descrição</label>
        <textarea id="description" name="description" rows="3" maxlength="2000"><?= e($values['description']) ?></textarea>
    </div>
    <div class="field">
        <label for="price">Preço (R$)</label>
        <input type="text" id="price" name="price" value="<?= e($values['price']) ?>" required inputmode="decimal" placeholder="49,90">
    </div>
    <?php if ($product === null): ?>
        <div class="field">
            <label for="stock">Estoque inicial</label>
            <input type="number" id="stock" name="stock" value="<?= e($values['stock']) ?>" min="0" max="1000000" required>
        </div>
    <?php else: ?>
        <p class="muted">
            Estoque atual: <strong><?= (int) $product['stock'] ?></strong> —
            <a href="<?= e(url('/admin/stock.php?id=' . (int) $product['id'])) ?>">ajustar estoque</a>
        </p>
    <?php endif; ?>
    <div class="field">
        <label for="image_url">URL da imagem (opcional)</label>
        <input type="url" id="image_url" name="image_url" value="<?= e($values['image_url']) ?>" maxlength="255" placeholder="https://…">
    </div>
    <div class="field checkbox">
        <input type="checkbox" id="active" name="active" value="1" <?= $values['active'] ? 'checked' : '' ?>>
        <label for="active">Produto visível na loja</label>
    </div>

    <div class="actions">
        <button type="submit" class="btn">Salvar</button>
        <a href="<?= e(url('/admin/products.php')) ?>">Cancelar</a>
    </div>
</form>
<?php render_footer(); ?>
