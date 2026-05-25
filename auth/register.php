<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$page_title = 'Регистрация';

if (is_logged_in()) {
    redirect('/profile.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
    $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim((string)$_POST['phone']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $password2 = isset($_POST['password2']) ? (string)$_POST['password2'] : '';

    if (!validate_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Ошибка CSRF-токена.';
    } elseif ($name === '' || $email === '' || $phone === '' || $password === '' || $password2 === '') {
        $error = 'Заполните все поля.';
    } else {
        if (mb_strlen($name) > 100) {
            $error = 'Имя слишком длинное.';
        } elseif (mb_strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Некорректный email.';
        } elseif (!preg_match('/^[0-9+][0-9]{6,20}$/', $phone)) {
            $error = 'Некорректный телефон. Пример: +79991234567';
        } elseif (mb_strlen($password) < 6) {
            $error = 'Пароль должен быть минимум 6 символов.';
        } elseif ($password !== $password2) {
            $error = 'Пароли не совпадают.';
        } else {
            // Проверяем уникальность
            $emailMarketing = isset($_POST['email_marketing']) ? 1 : 0;

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email OR phone = :phone LIMIT 1");
            $stmt->execute([':email' => $email, ':phone' => $phone]);
            $exists = $stmt->fetch();
            if ($exists) {
                $error = 'Пользователь с таким email или телефоном уже существует.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, phone, password_hash, email_marketing)
                    VALUES (:name, :email, :phone, :hash, :em)
                ");
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':hash' => $hash,
                    ':em' => $emailMarketing,
                ]);
                // Сразу логиним
                $id = (int)$pdo->lastInsertId();
                $_SESSION['user_id'] = $id;
                redirect('/profile.php');
            }
        }
    }
}

$csrf = csrf_token();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-page">
    <div class="auth-card">
        <h1>Регистрация</h1>
        <?php if ($error !== ''): ?>
            <div class="form-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form class="auth-form" method="post">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

            <div class="form-row">
                <label for="name">Имя</label>
                <input type="text" id="name" name="name" required maxlength="100" autocomplete="name">
            </div>

            <div class="form-row">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required maxlength="100" autocomplete="email">
            </div>

            <div class="form-row">
                <label for="phone">Телефон</label>
                <input type="tel" id="phone" name="phone" required maxlength="20" autocomplete="tel" placeholder="+79991234567">
            </div>

            <div class="form-row">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" required minlength="6" maxlength="255" autocomplete="new-password">
            </div>

            <div class="form-row">
                <label for="password2">Повторите пароль</label>
                <input type="password" id="password2" name="password2" required minlength="6" maxlength="255" autocomplete="new-password">
            </div>

            <div class="form-row" style="flex-direction:row;align-items:center;gap:10px;">
                <input type="checkbox" id="email_marketing" name="email_marketing" value="1" checked style="width:auto;">
                <label for="email_marketing" style="font-size:13px;color:var(--text);">
                    Я согласен получать информацию об акциях ресторанов-партнёров TableSpot.
                    <a href="<?= $appBaseUrl ?>/privacy.php" target="_blank">Политика конфиденциальности</a>
                </label>
            </div>

            <button class="btn btn-primary" type="submit">Создать аккаунт</button>
            <p class="muted">Уже есть аккаунт? <a href="<?= $appBaseUrl ?>/auth/login.php">Вход</a></p>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

