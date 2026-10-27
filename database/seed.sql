-- =====================================================================
-- Dados de exemplo (execute UMA vez, depois do schema.sql)
-- Uso: mysql -u root -p < database/seed.sql
--
-- Contas de teste (troque as senhas em qualquer uso real):
--   Admin:   admin@loja.com   / admin123
--   Cliente: cliente@loja.com / cliente123
-- =====================================================================

USE ecommerce;

INSERT INTO users (name, email, password_hash, role) VALUES
('Administrador', 'admin@loja.com',   '$2y$10$I/yNAwlX2LfFlgvR0WKDMuMioYrrkdkzC0r0HuSTpIq9Awax9TXJq', 'admin'),
('Cliente Teste', 'cliente@loja.com', '$2y$10$nSrvsYJWhqWKzvFduJ3X3.lYl4PtvPxroCKi2usmOV.48cWpN8O2e', 'customer');

INSERT INTO products (name, description, price, stock) VALUES
('Camiseta Básica Preta',   'Camiseta 100% algodão, modelagem regular.',                      49.90, 40),
('Tênis Esportivo Runner',  'Tênis leve com amortecimento para corrida e caminhada.',        279.90, 15),
('Mochila Urbana 20L',      'Mochila resistente à água com compartimento para notebook.',    159.00, 25),
('Fone de Ouvido Bluetooth','Bateria de até 20 horas, microfone embutido.',                  199.90, 12),
('Garrafa Térmica 500ml',   'Aço inoxidável, mantém a temperatura por 12 horas.',             69.90, 60),
('Caderno Universitário',   '200 folhas, capa dura, pautado.',                                24.50, 100),
('Mouse Sem Fio',           'Sensor óptico de 1600 DPI, receptor USB.',                       59.90, 30),
('Luminária de Mesa LED',   'Três níveis de brilho, base com carregador USB.',                89.90, 3);

-- Registra o estoque inicial no histórico de movimentações
INSERT INTO stock_movements (product_id, delta, reason, note)
SELECT id, stock, 'initial', 'Estoque inicial' FROM products;
