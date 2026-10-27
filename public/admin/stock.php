<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';
$admin = require_admin();

$id = input_int($_GET, 'id');
if ($id === null) {
    redirect('/admin/products.php');
}

$st = db()->prepare('SELECT id, name, stock FROM products WHERE id = ?');
$st->execute([$id]);
$product = $st->fetch();
if (!$product) {
    http_response_code(404);
    exit('Produto não encontrado.');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $delta  = input_int($_POST, 'delta');
    $reason = input_str($_POST, 'reason');
    $note   = input_str($_POST, 'note');

    if ($delta === null || abs($delta) > 1000000) {
        $error = 'Informe a quantidade como um número inteiro (positivo para entrada, negativo para saída).';
    } elseif (mb_strlen($note, 'UTF-8') > 255) {
        $error = 'A observação pode ter no máximo 255 caracteres.';
    } else {
        try {
            $newStock = adjust_stock($id, $delta, $reason, $note, (int) $admin['id']);
            flash('success', 'Estoque atualizado. Novo saldo: ' . $newStock . '.');
            redirect('/admin/stock.php?id=' . $id);
        } catch (ShopException $ex) {
            $error = $ex->getMessage();
        }
    }
}

$st = db()->prepare(
    'SELECT sm.delta, sm.reason, sm.note, sm.order_id, sm.created_at, u.name AS user_name
       FROM stock_movements sm
       LEFT JOIN users u ON u.id = sm.user_id
      WHERE sm.product_id = ?
      ORDER BY sm.id DESC
      LIMIT 50'
);
$st->execute([$id]);
$movements = $st->fetchAll();

$reasonLabels = [
    'initial'      => 'Estoque inicial',
    'restock'      => 'Reposição',
    'adjustment'   => 'Ajuste',
    'sale'         => 'Venda',
    'cancellation' => 'Cancelamento',
];

render_header('Estoque · ' . $product['name']);
?>
<h1>Estoque: <?= e($product['name']) ?></h1>
<?php render_admin_nav('products'); ?>

<div class="card form form--wide">
    <p>Saldo atual: <strong><?= (int) $product['stock'] ?></strong> unidade(s)</p>

    <form method="post" novalidate>
        <?= csrf_field() ?>
        <?php if ($error): ?>
            <p class="errors"><?= e($error) ?></p>
        <?php endif; ?>

        <div class="field">
            <label for="delta">Quantidade</label>
            <input type="number" id="delta" name="delta" value="<?= old('delta') ?>" required step="1" placeholder="ex.: 20 (entrada) ou -3 (saída)">
            <small>Use valores positivos para entrada e negativos para saída (perda, avaria…).</small>
        </div>
        <div class="field">
            <label for="reason">Motivo</label>
            <select id="reason" name="reason">
                <option value="restock" <?= ($_POST['reason'] ?? '') === 'restock' ? 'selected' : '' ?>>Reposição</option>
                <option value="adjustment" <?= ($_POST['reason'] ?? '') === 'adjustment' ? 'selected' : '' ?>>Ajuste / perda</option>
            </select>
        </div>
        <div class="field">
            <label for="note">Observação (opcional)</label>
            <input type="text" id="note" name="note" value="<?= old('note') ?>" maxlength="255">
        </div>
        <div class="actions">
            <button type="submit" class="btn">Registrar movimentação</button>
            <a href="<?= e(url('/admin/products.php')) ?>">Voltar</a>
        </div>
    </form>
</div>

<h2>Histórico (últimas 50 movimentações)</h2>
<div class="table-wrap">
    <table>
        <thead>
            <tr><th>Data</th><th>Motivo</th><th class="num">Qtd.</th><th>Observação</th><th>Responsável</th></tr>
        </thead>
        <tbody>
        <?php foreach ($movements as $m): ?>
            <tr>
                <td><?= e(fmt_date($m['created_at'])) ?></td>
                <td><?= e($reasonLabels[$m['reason']] ?? $m['reason']) ?></td>
                <td class="num <?= (int) $m['delta'] > 0 ? 'delta-pos' : 'delta-neg' ?>">
                    <?= (int) $m['delta'] > 0 ? '+' : '' ?><?= (int) $m['delta'] ?>
                </td>
                <td><?= e($m['note']) ?></td>
                <td><?= e($m['user_name'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php render_footer(); ?>
