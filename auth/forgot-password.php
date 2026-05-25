<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mail.php';

$page_title = 'Восстановление пароля';

if (is_logged_in()) {
    redirect('/profile.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';

    if (!validate_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Ошибка CSRF-токена.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Введите корректный email.';
    } else {
        // Проверяем, есть ли пользователь
        $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);

            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires)");
            $stmt->execute([
                ':email'   => $email,
                ':token'   => $token,
                ':expires' => $expiresAt,
            ]);

            $resetUrl = APP_URL . '/auth/reset-password.php?token=' . $token;

            $html = '
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
                <div style="background:#2D6A4F;padding:24px;text-align:center;border-radius:12px 12px 0 0;">
                    <h1 style="color:white;margin:0;font-size:24px;">TableSpot</h1>
                </div>
                <div style="padding:32px;background:#ffffff;border:1px solid #E5E7EB;">
                    <h2 style="margin-top:0;">Восстановление пароля</h2>
                    <p>Вы запросили сброс пароля. Ссылка действительна 1 час.</p>
                    <div style="text-align:center;margin:32px 0;">
                        <a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '"
                           style="background:#2D6A4F;color:white;padding:14px 32px;border-radius:12px;text-decoration:none;font-weight:bold;font-size:16px;display:inline-block;">
                            Сбросить пароль
                        </a>
                    </div>
                    <p style="color:#6B7280;font-size:13px;">Если вы не запрашивали сброс — просто проигнорируйте это письмо.</p>
                </div>
                <div style="padding:16px;text-align:center;color:#9CA3AF;font-size:12px;">
                    &copy; ' . date('Y') . ' TableSpot
                </div>
            </div>';

            send_mail($email, 'TableSpot — восстановление пароля', $html);
        }

        // Одинаковое сообщение в обоих случаях
        $message = 'Если этот email зарегистрирован — письмо отправлено.';
    }
}

$csrf = csrf_token();
require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-page">
    <div class="auth-card">
        <h1>Восстановление пароля</h1>

        <?php if ($message !== ''): ?>
            <div class="form-success" role="status"><?= e($message) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="form-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($message === ''): ?>
        <form class="auth-form" method="post">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

            <div class="form-row">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required maxlength="100" autocomplete="email" placeholder="Ваш email">
            </div>

            <button class="btn btn-primary" type="submit">Отправить ссылку</button>
            <p class="muted"><a href="<?= $appBaseUrl ?>/auth/login.php">Вернуться ко входу</a></p>
        </form>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
