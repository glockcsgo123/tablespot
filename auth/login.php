<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$page_title = 'Вход';

if (is_logged_in()) {
    redirect('/profile.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if (!validate_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Ошибка CSRF-токена.';
    } elseif ($email === '' || $password === '') {
        $error = 'Заполните все поля.';
    } else {
        if (mb_strlen($email) > 100) {
            $error = 'Некорректный email.';
        } else {
            $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();
            if (!$user) {
                $error = 'Неверный email или пароль.';
            } else {
                if (!password_verify($password, (string)$user['password_hash'])) {
                    $error = 'Неверный email или пароль.';
                } else {
                    $_SESSION['user_id'] = (int)$user['id'];
                    redirect('/profile.php');
                }
            }
        }
    }
}

$csrf = csrf_token();

$flashSuccess = '';
if (!empty($_SESSION['flash_success'])) {
    $flashSuccess = (string)$_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-page">
    <div class="auth-card">
        <h1>Вход</h1>
        <?php if ($flashSuccess !== ''): ?>
            <div class="form-success" role="status"><?= e($flashSuccess) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="form-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form class="auth-form" method="post">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

            <div class="form-row">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required maxlength="100" autocomplete="email">
            </div>
            <div class="form-row">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" required maxlength="255" autocomplete="current-password">
            </div>

            <button class="btn btn-primary" type="submit">Войти</button>
            <p class="muted"><a href="<?= $appBaseUrl ?>/auth/forgot-password.php">Забыли пароль?</a></p>
            <p class="muted">Нет аккаунта? <a href="<?= $appBaseUrl ?>/auth/register.php">Регистрация</a></p>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

