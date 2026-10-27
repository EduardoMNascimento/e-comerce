<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

/* ---------- Ações (POST) — padrão Post/Redirect/Get ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $action    = input_str($_POST, 'action');
    $next      = safe_path($_POST['next'] ?? null, '/cart.php');
    $user      = require_login($next); // visitante que clica em "Adicionar" volta ao catálogo após o login
    $userId    = (int) $user['id'];
    $productId = input_int($_POST, 'product_id');
    $quantity  = input_int($_POST, 'quantity');
    $error     = null;

    switch ($action) {
        case 'add':
            if ($productId === null || $quantity === null) {
                $error = 'Dados inválidos.';
                break;
            }
            $error = cart_add($userId, $productId, $quantity);
            if ($error === null) {
                flash('success', 'Produto adicionado ao carrinho.');
            }
            break;

        case 'update':
            if ($productId === null || $quantity === null) {
                $error = 'Dados inválidos.';
                break;
            }
            $error = cart_set_quantity($userId, $productId, $quantity);
            if ($error === null) {
                flash('success', 'Carrinho atualizado.');
            }
            break;

        case 'remove':
            if ($productId === null) {
                $error = 'Dados inválidos.';
                break;
            }
            cart_remove($userId, $productId);
            flash('success', 'Item removido do carrinho.');
            break;

        case 'clear':
            cart_clear($userId);
            flash('success', 'Carrinho esvaziado.');
            break;

        default:
            $error = 'Ação inválida.';
    }

    if ($error !== null) {
        flash('danger', $error);
    }
    redirect($next);
}

/* ---------- Exibição (GET) ---------- */
$user  = require_login();
$items = cart_items((int) $user['id']);
$total = cart_total_cents($items);

$hasProblem = false;
foreach ($items as $it) {
    if (cart_item_problem($it) !== null) {
        $hasProblem = true;
        break;
    }
}

render_header('Carrinho');
?>
<h1>Seu carrinho</h1>

<?php if (!$items): ?>
    <div class="empty">
        <p>Seu carrinho está vazio.</p>
        <a class="btn" href="<?= e(url('/')) ?>">Ver produtos</a>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Produto</th>
                    <th class="num">Preço</th>
                    <th>Quantidade</th>
                    <th class="num">Subtotal</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $it):
                $problem  = cart_item_problem($it);
                $subtotal = to_cents($it['price']) * (int) $it['quantity'];
                ?>
                <tr>
                    <td>
                        <?= e($it['name']) ?>
                        <?php if ($problem): ?>
                            <br><span class="badge badge--out"><?= e($problem) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="num"><?= e(money($it['price'])) ?></td>
                    <td>
                        <form method="post" action="<?= e(url('/cart.php')) ?>" class="qty-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="product_id" value="<?= (int) $it['product_id'] ?>">
                            <input type="number" name="quantity" value="<?= (int) $it['quantity'] ?>" min="0" max="999" aria-label="Quantidade de <?= e($it['name']) ?>">
                            <button type="submit" class="btn btn--secondary btn--small">Atualizar</button>
                        </form>
                    </td>
                    <td class="num"><?= e(money(cents_to_decimal($subtotal))) ?></td>
                    <td class="num">
                        <form method="post" action="<?= e(url('/cart.php')) ?>" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="<?= (int) $it['product_id'] ?>">
                            <button type="submit" class="link-button link-button--danger">Remover</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="summary">
        <form method="post" action="<?= e(url('/cart.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="btn btn--secondary">Esvaziar carrinho</button>
        </form>

        <div class="actions">
            <span class="total">Total: <?= e(money(cents_to_decimal($total))) ?></span>
            <form method="post" action="<?= e(url('/checkout.php')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn" <?= $hasProblem ? 'disabled' : '' ?>>Finalizar compra</button>
            </form>
        </div>
    </div>
    <?php if ($hasProblem): ?>
        <p class="muted">Resolva os itens sinalizados (ajuste a quantidade ou remova) para finalizar a compra.</p>
    <?php endif; ?>
<?php endif; ?>
<?php render_footer(); ?>
