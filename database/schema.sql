-- =====================================================================
-- E-Commerce básico — esquema do banco de dados
-- Compatível com MySQL 8+ e MariaDB 10.4+
-- Uso: mysql -u root -p < database/schema.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS ecommerce
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ecommerce;

-- ---------------------------------------------------------------------
-- Usuários (clientes e administradores)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Produtos (o campo "stock" é o estoque atual)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(150) NOT NULL,
    description TEXT NULL,
    price       DECIMAL(10,2) NOT NULL,
    stock       INT UNSIGNED NOT NULL DEFAULT 0,
    image_url   VARCHAR(255) NULL,
    active      TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_products_active (active),
    CONSTRAINT chk_products_price CHECK (price >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Carrinho (persistido por usuário; um item por produto)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cart_items (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity   INT UNSIGNED NOT NULL DEFAULT 1,
    added_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cart_user_product (user_id, product_id),
    CONSTRAINT fk_cart_user    FOREIGN KEY (user_id)    REFERENCES users (id)    ON DELETE CASCADE,
    CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT chk_cart_quantity CHECK (quantity > 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Pedidos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    total      DECIMAL(10,2) NOT NULL,
    status     ENUM('pending', 'paid', 'shipped', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_orders_user (user_id),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB;

-- Itens do pedido: guardam nome e preço da época da compra (snapshot)
CREATE TABLE IF NOT EXISTS order_items (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id     INT UNSIGNED NOT NULL,
    product_id   INT UNSIGNED NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    unit_price   DECIMAL(10,2) NOT NULL,
    quantity     INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id),
    CONSTRAINT fk_oi_order   FOREIGN KEY (order_id)   REFERENCES orders (id)   ON DELETE CASCADE,
    CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Histórico de movimentações de estoque (auditoria)
-- delta > 0 entrada | delta < 0 saída
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_movements (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NULL,
    order_id   INT UNSIGNED NULL,
    delta      INT NOT NULL,
    reason     ENUM('initial', 'restock', 'adjustment', 'sale', 'cancellation') NOT NULL,
    note       VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_stock_product (product_id, created_at),
    CONSTRAINT fk_sm_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_sm_user    FOREIGN KEY (user_id)    REFERENCES users (id)    ON DELETE SET NULL,
    CONSTRAINT fk_sm_order   FOREIGN KEY (order_id)   REFERENCES orders (id)   ON DELETE SET NULL
) ENGINE=InnoDB;
