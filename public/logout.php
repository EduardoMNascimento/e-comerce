<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

// Logout só por POST com token CSRF (evita que um link/imagem deslogue o usuário).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/');
}

csrf_check();
logout_user();
flash('success', 'Você saiu da sua conta.');
redirect('/');
