<?php
declare(strict_types=1);

/**
 * Regras de negócio: carrinho, checkout, pedidos e estoque.
 */

/** Erro de regra de negócio, com mensagem segura para mostrar ao usuário. */
final class ShopException extends RuntimeException
{
}

/* ------------------------------------------------------------------
 * Carrinho
 * ----------------------------------------------------------------*/

function cart_items(int $userId): array
{
    $st = db()->prepare(
        'SELECT ci.product_id, ci.quantity, p.name, p.price, p.stock, p.active
           FROM cart_items ci
           JOIN products p ON p.id = ci.product_id
          WHERE ci.user_id = ?
          ORDER BY ci.id'
    );
    $st->execute([$userId]);
    return $st->fetchAll();
}

function cart_count(int $userId): int
{
    $st = db()->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE user_id = ?');
    $st->execute([$userId]);
    return (int) $st->fetchColumn();
}

function cart_total_cents(array $items): int
{
    $total = 0;
    foreach ($items as $it) {
        $total += to_cents($it['price']) * (int) $it['quantity'];
    }
    return $total;
}

/** Problema que impede a compra do item (ou null se estiver tudo certo). */
function cart_item_problem(array $item): ?string
{
    if (!(int) $item['active']) {
        return 'Produto indisponível';
    }
    if ((int) $item['stock'] === 0) {
        return 'Esgotado';
    }
    if ((int) $item['quantity'] > (int) $item['stock']) {
        return 'Estoque insuficiente (disponível: ' . (int) $item['stock'] . ')';
    }
    return null;
}

/** Adiciona ao carrinho. Retorna uma mensagem de erro ou null em caso de sucesso. */
function cart_add(int $userId, int $productId, int $qty): ?string
{
    if ($qty < 1 || $qty > 999) {
        return 'Quantidade inválida.';
    }

    $st = db()->prepare('SELECT name, stock, active FROM products WHERE id = ?');
    $st->execute([$productId]);
    $product = $st->fetch();
    if (!$product || !(int) $product['active']) {
        return 'Produto indisponível.';
    }

    $st = db()->prepare('SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ?');
    $st->execute([$userId, $productId]);
    $current = (int) ($st->fetchColumn() ?: 0);
    $wanted  = $current + $qty;

    if ($wanted > (int) $product['stock']) {
        return (int) $product['stock'] === 0
            ? '“' . $product['name'] . '” está esgotado.'
            : 'Estoque insuficiente para “' . $product['name'] . '”: disponível ' . (int) $product['stock']
              . ', já no carrinho ' . $current . '.';
    }

    $st = db()->prepare(
        'INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
    );
    $st->execute([$userId, $productId, $wanted]);
    return null;
}

/** Define a quantidade de um item (0 remove). Retorna mensagem de erro ou null. */
function cart_set_quantity(int $userId, int $productId, int $qty): ?string
{
    if ($qty <= 0) {
        cart_remove($userId, $productId);
        return null;
    }
    if ($qty > 999) {
        return 'Quantidade inválida.';
    }

    $st = db()->prepare('SELECT name, stock, active FROM products WHERE id = ?');
    $st->execute([$productId]);
    $product = $st->fetch();
    if (!$product || !(int) $product['active']) {
        return 'Produto indisponível.';
    }
    if ($qty > (int) $product['stock']) {
        return 'Estoque insuficiente para “' . $product['name'] . '”: disponível ' . (int) $product['stock'] . '.';
    }

    $st = db()->prepare('UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?');
    $st->execute([$qty, $userId, $productId]);
    return null;
}

function cart_remove(int $userId, int $productId): void
{
    $st = db()->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
    $st->execute([$userId, $productId]);
}

function cart_clear(int $userId): void
{
    $st = db()->prepare('DELETE FROM cart_items WHERE user_id = ?');
    $st->execute([$userId]);
}

/* ------------------------------------------------------------------
 * Checkout (transacional): valida estoque, cria o pedido e dá baixa
 * ----------------------------------------------------------------*/

/**
 * Finaliza a compra do carrinho do usuário e retorna o id do pedido.
 * Tudo ocorre em uma transação; as linhas do carrinho e dos produtos ficam
 * bloqueadas (FOR UPDATE), então duas compras simultâneas não vendem o mesmo estoque.
 *
 * @throws ShopException quando o carrinho está vazio ou falta estoque
 */
function place_order(int $userId): int
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        // ORDER BY p.id mantém a ordem dos bloqueios consistente (evita deadlocks)
        $st = $pdo->prepare(
            'SELECT ci.product_id, ci.quantity, p.name, p.price, p.stock, p.active
               FROM cart_items ci
               JOIN products p ON p.id = ci.product_id
              WHERE ci.user_id = ?
              ORDER BY p.id
                FOR UPDATE'
        );
        $st->execute([$userId]);
        $items = $st->fetchAll();

        if (!$items) {
            throw new ShopException('Seu carrinho está vazio.');
        }

        $totalCents = 0;
        foreach ($items as $it) {
            $problem = cart_item_problem($it);
            if ($problem !== null) {
                throw new ShopException('“' . $it['name'] . '”: ' . lcfirst($problem)
                    . '. Ajuste o carrinho e tente novamente.');
            }
            $totalCents += to_cents($it['price']) * (int) $it['quantity'];
        }

        $pdo->prepare('INSERT INTO orders (user_id, total) VALUES (?, ?)')
            ->execute([$userId, cents_to_decimal($totalCents)]);
        $orderId = (int) $pdo->lastInsertId();

        $insertItem = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity)
             VALUES (?, ?, ?, ?, ?)'
        );
        $decrement = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
        $movement  = $pdo->prepare(
            'INSERT INTO stock_movements (product_id, user_id, order_id, delta, reason, note)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($items as $it) {
            $pid = (int) $it['product_id'];
            $qty = (int) $it['quantity'];

            $insertItem->execute([$orderId, $pid, $it['name'], $it['price'], $qty]);

            $decrement->execute([$qty, $pid, $qty]);
            if ($decrement->rowCount() !== 1) {
                throw new ShopException('Estoque insuficiente para “' . $it['name'] . '”.');
            }

            $movement->execute([$pid, $userId, $orderId, -$qty, 'sale', 'Pedido #' . $orderId]);
        }

        $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$userId]);

        $pdo->commit();
        return $orderId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* ------------------------------------------------------------------
 * Pedidos
 * ----------------------------------------------------------------*/

/** Anexa a lista de itens (chave "items") a cada pedido. */
function attach_order_items(array $orders): array
{
    if (!$orders) {
        return [];
    }

    $ids = array_map('intval', array_column($orders, 'id'));
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $st = db()->prepare(
        "SELECT order_id, product_name, unit_price, quantity
           FROM order_items WHERE order_id IN ($in) ORDER BY id"
    );
    $st->execute($ids);

    $byOrder = [];
    foreach ($st->fetchAll() as $row) {
        $byOrder[(int) $row['order_id']][] = $row;
    }
    foreach ($orders as &$order) {
        $order['items'] = $byOrder[(int) $order['id']] ?? [];
    }
    unset($order);

    return $orders;
}

function user_orders(int $userId): array
{
    $st = db()->prepare('SELECT id, total, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC');
    $st->execute([$userId]);
    return attach_order_items($st->fetchAll());
}

/**
 * Altera o status de um pedido (uso administrativo).
 * Ao cancelar, devolve os itens ao estoque. Pedido cancelado não pode ser reaberto.
 *
 * @throws ShopException
 */
function change_order_status(int $orderId, string $newStatus, int $actorId): void
{
    if (!in_array($newStatus, ['pending', 'paid', 'shipped', 'cancelled'], true)) {
        throw new ShopException('Status inválido.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $st = $pdo->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
        $st->execute([$orderId]);
        $current = $st->fetchColumn();

        if ($current === false) {
            throw new ShopException('Pedido não encontrado.');
        }
        if ($current === 'cancelled') {
            throw new ShopException('Pedidos cancelados não podem ser alterados.');
        }
        if ($current === $newStatus) {
            $pdo->commit();
            return;
        }

        if ($newStatus === 'cancelled') {
            $st = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? ORDER BY product_id');
            $st->execute([$orderId]);

            $restock  = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
            $movement = $pdo->prepare(
                'INSERT INTO stock_movements (product_id, user_id, order_id, delta, reason, note)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($st->fetchAll() as $row) {
                $restock->execute([(int) $row['quantity'], (int) $row['product_id']]);
                $movement->execute([
                    (int) $row['product_id'], $actorId, $orderId,
                    (int) $row['quantity'], 'cancellation', 'Cancelamento do pedido #' . $orderId,
                ]);
            }
        }

        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$newStatus, $orderId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* ------------------------------------------------------------------
 * Estoque (uso administrativo)
 * ----------------------------------------------------------------*/

/**
 * Entrada (delta > 0) ou saída (delta < 0) manual de estoque, com registro no histórico.
 * Retorna o novo saldo.
 *
 * @throws ShopException
 */
function adjust_stock(int $productId, int $delta, string $reason, ?string $note, int $actorId): int
{
    if ($delta === 0) {
        throw new ShopException('Informe uma quantidade diferente de zero.');
    }
    if (!in_array($reason, ['restock', 'adjustment'], true)) {
        throw new ShopException('Motivo inválido.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $st = $pdo->prepare('SELECT stock FROM products WHERE id = ? FOR UPDATE');
        $st->execute([$productId]);
        $current = $st->fetchColumn();

        if ($current === false) {
            throw new ShopException('Produto não encontrado.');
        }

        $new = (int) $current + $delta;
        if ($new < 0) {
            throw new ShopException('O estoque não pode ficar negativo (saldo atual: ' . (int) $current . ').');
        }
        if ($new > 1000000) {
            throw new ShopException('Estoque acima do limite permitido (1.000.000).');
        }

        $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?')->execute([$new, $productId]);
        $pdo->prepare(
            'INSERT INTO stock_movements (product_id, user_id, delta, reason, note) VALUES (?, ?, ?, ?, ?)'
        )->execute([$productId, $actorId, $delta, $reason, $note !== '' ? $note : null]);

        $pdo->commit();
        return $new;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
