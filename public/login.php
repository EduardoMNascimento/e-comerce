<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

if (current_user() !== null) {
    redirect('/');
}

// Hash fictício: mantém o tempo de resposta parecido quando o e-mail não existe.
const DUMMY_HASH = '$2y$10$nSrvsYJWhqWKzvFduJ3X3.lYl4PtvPxroCKi2usmOV.48cWpN8O2e';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email    = strtolower(input_str($_POST, 'email'));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    $st = db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $st->execute([$email]);
    $row = $st->fetch();

    $valid = password_verify($password, $row['password_hash'] ?? DUMMY_HASH) && $row !== false;

    if ($valid) {
        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
        }

        $intended = $_SESSION['intended'] ?? '/';
        login_user((int) $row['id']);
        unset($_SESSION['intended']);
        flash('success', 'Login realizado com sucesso.');
        redirect(safe_path($intended, '/'));
    }

    $error = 'E-mail ou senha incorretos.';
}

render_header('Entrar');
?>
<h1>Entrar</h1>

<form method="post" class="card form" novalidate>
    <?= csrf_field() ?>
    <?php if ($error): ?>
        <p class="errors"><?= e($error) ?></p>
    <?php endif; ?>

    <div class="field">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" value="<?= old('email') ?>" required autofocus autocomplete="email">
    </div>
    <div class="field">
        <label for="password">Senha</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn">Entrar</button>
    <p class="muted">Ainda não tem conta? <a href="<?= e(url('/register.php')) ?>">Cadastre-se</a>.</p>
</form>
<?php render_footer(); ?>
