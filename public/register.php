<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

if (current_user() !== null) {
    redirect('/');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name     = input_str($_POST, 'name');
    $email    = strtolower(input_str($_POST, 'email'));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm  = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';

    $nameLength = mb_strlen($name, 'UTF-8');
    if ($nameLength < 2 || $nameLength > 100) {
        $errors[] = 'Informe seu nome (2 a 100 caracteres).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errors[] = 'Informe um e-mail válido.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'A senha deve ter pelo menos 8 caracteres.';
    } elseif (strlen($password) > 72) {
        $errors[] = 'A senha deve ter no máximo 72 caracteres.'; // limite do bcrypt
    }
    if ($password !== $confirm) {
        $errors[] = 'A confirmação de senha não confere.';
    }

    if (!$errors) {
        try {
            db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)')
                ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);

            login_user((int) db()->lastInsertId());
            flash('success', 'Conta criada com sucesso. Bem-vindo(a)!');
            redirect('/');
        } catch (PDOException $ex) {
            if (($ex->errorInfo[1] ?? null) === 1062) { // chave duplicada
                $errors[] = 'Este e-mail já está cadastrado.';
            } else {
                throw $ex;
            }
        }
    }
}

render_header('Criar conta');
?>
<h1>Criar conta</h1>

<form method="post" class="card form" novalidate>
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
        <input type="text" id="name" name="name" value="<?= old('name') ?>" required maxlength="100" autocomplete="name">
    </div>
    <div class="field">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" value="<?= old('email') ?>" required maxlength="150" autocomplete="email">
    </div>
    <div class="field">
        <label for="password">Senha</label>
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        <small>Mínimo de 8 caracteres.</small>
    </div>
    <div class="field">
        <label for="password_confirm">Confirmar senha</label>
        <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
    </div>
    <button type="submit" class="btn">Criar conta</button>
    <p class="muted">Já tem conta? <a href="<?= e(url('/login.php')) ?>">Entrar</a>.</p>
</form>
<?php render_footer(); ?>
