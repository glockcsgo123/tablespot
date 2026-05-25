<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$page_title = 'Вход админа';

if (is_admin_logged_in()) {
    redirect('/admin/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = isset($_POST['login']) ? trim((string)$_POST['login']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if (!validate_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Ошибка CSRF-токена.';
    } elseif ($login === '' || $password === '') {
        $error = 'Заполните все поля.';
    } else {
        $stmt = $pdo->prepare("SELECT id, restaurant_id, password_hash FROM admins WHERE login = :login LIMIT 1");
        $stmt->execute([':login' => $login]);
        $admin = $stmt->fetch();
        if (!$admin || !password_verify($password, (string)$admin['password_hash'])) {
            $error = 'Неверный логин или пароль.';
        } else {
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_restaurant_id'] = (int)$admin['restaurant_id'];
            redirect('/admin/index.php');
        }
    }
}

$csrf = csrf_token();
require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-page">
    <div class="auth-card">
        <h1>Вход администратора</h1>
        <?php if ($error !== ''): ?>
            <div class="form-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form class="auth-form" method="post">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

            <div class="form-row">
                <label for="login">Логин</label>
                <input type="text" id="login" name="login" required maxlength="50" autocomplete="username">
            </div>

            <div class="form-row">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" required maxlength="255" autocomplete="current-password">
            </div>

            <button class="btn btn-primary" type="submit">Войти</button>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

