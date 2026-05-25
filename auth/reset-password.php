<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$page_title = 'Сброс пароля';

$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
$error = '';
$tokenValid = false;
$tokenEmail = '';

if ($token !== '' && strlen($token) === 64 && ctype_xdigit($token)) {
    $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = :token AND used = 0 AND expires_at > NOW() LIMIT 1");
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();
    if ($row) {
        $tokenValid = true;
        $tokenEmail = (string)$row['email'];
    }
}

if (!$tokenValid && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $error = 'Ссылка недействительна или истекла.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postToken = isset($_POST['token']) ? trim((string)$_POST['token']) : '';

    if (!validate_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Ошибка CSRF-токена.';
    } else {
        // Повторно проверяем токен
        $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = :token AND used = 0 AND expires_at > NOW() LIMIT 1");
        $stmt->execute([':token' => $postToken]);
        $row = $stmt->fetch();

        if (!$row) {
            $error = 'Ссылка недействительна или истекла.';
        } else {
            $tokenEmail = (string)$row['email'];
            $tokenValid = true;

            $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
            $password2 = isset($_POST['password2']) ? (string)$_POST['password2'] : '';

            if (mb_strlen($password) < 6) {
                $error = 'Пароль должен быть минимум 6 символов.';
            } elseif ($password !== $password2) {
                $error = 'Пароли не совпадают.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);

                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE email = :email");
                    $stmt->execute([':hash' => $hash, ':email' => $tokenEmail]);

                    $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = :token");
                    $stmt->execute([':token' => $postToken]);

                    $pdo->commit();

                    $_SESSION['flash_success'] = 'Пароль успешно изменён. Войдите.';
                    redirect('/auth/login.php');
                } catch (\Throwable $ex) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $error = 'Ошибка при сбросе пароля. Попробуйте снова.';
                }
            }
        }
    }

    $token = $postToken;
}

$csrf = csrf_token();
require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-page">
    <div class="auth-card">
        <h1>Сброс пароля</h1>

        <?php if ($error !== ''): ?>
            <div class="form-error" role="alert"><?= e($error) ?></div>
            <?php if (!$tokenValid): ?>
                <p class="muted"><a href="<?= $appBaseUrl ?>/auth/forgot-password.php">Запросить новую ссылку</a></p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($tokenValid): ?>
        <form class="auth-form" method="post">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="form-row">
                <label for="password">Новый пароль</label>
                <input type="password" id="password" name="password" required minlength="6" maxlength="255" autocomplete="new-password">
            </div>

            <div class="form-row">
                <label for="password2">Повторите пароль</label>
                <input type="password" id="password2" name="password2" required minlength="6" maxlength="255" autocomplete="new-password">
            </div>

            <button class="btn btn-primary" type="submit">Сохранить пароль</button>
        </form>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
