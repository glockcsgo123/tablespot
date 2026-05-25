<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mail.php';

$page_title = 'Разместить ресторан';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Ошибка CSRF-токена.';
    } else {
        $restaurantName = isset($_POST['restaurant_name']) ? trim((string)$_POST['restaurant_name']) : '';
        $contactName    = isset($_POST['contact_name']) ? trim((string)$_POST['contact_name']) : '';
        $phone          = isset($_POST['phone']) ? trim((string)$_POST['phone']) : '';
        $email          = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
        $cityVal        = isset($_POST['city']) ? trim((string)$_POST['city']) : 'Курск';
        $address        = isset($_POST['address']) ? trim((string)$_POST['address']) : '';
        $message        = isset($_POST['message']) ? trim((string)$_POST['message']) : '';

        if ($restaurantName === '' || $contactName === '' || $phone === '' || $email === '') {
            $error = 'Заполните все обязательные поля.';
        } elseif (mb_strlen($restaurantName) > 200) {
            $error = 'Название ресторана слишком длинное.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
            $error = 'Некорректный email.';
        } elseif (!preg_match('/^[0-9+][0-9\s\-]{5,20}$/', $phone)) {
            $error = 'Некорректный телефон.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO placement_requests (restaurant_name, contact_name, phone, email, city, address, message)
                VALUES (:rname, :cname, :phone, :email, :city, :address, :msg)
            ");
            $stmt->execute([
                ':rname'   => $restaurantName,
                ':cname'   => $contactName,
                ':phone'   => $phone,
                ':email'   => $email,
                ':city'    => $cityVal,
                ':address' => $address,
                ':msg'     => $message,
            ]);

            // Уведомление администратору
            $htmlBody = '
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
                <div style="background:#2D6A4F;padding:24px;text-align:center;border-radius:12px 12px 0 0;">
                    <h1 style="color:white;margin:0;">Новая заявка на размещение</h1>
                </div>
                <div style="padding:24px;background:#fff;border:1px solid #E5E7EB;">
                    <table style="width:100%;border-collapse:collapse;">
                        <tr><td style="padding:8px;font-weight:bold;">Ресторан:</td><td style="padding:8px;">' . htmlspecialchars($restaurantName, ENT_QUOTES, 'UTF-8') . '</td></tr>
                        <tr><td style="padding:8px;font-weight:bold;">Контакт:</td><td style="padding:8px;">' . htmlspecialchars($contactName, ENT_QUOTES, 'UTF-8') . '</td></tr>
                        <tr><td style="padding:8px;font-weight:bold;">Телефон:</td><td style="padding:8px;">' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '</td></tr>
                        <tr><td style="padding:8px;font-weight:bold;">Email:</td><td style="padding:8px;">' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</td></tr>
                        <tr><td style="padding:8px;font-weight:bold;">Город:</td><td style="padding:8px;">' . htmlspecialchars($cityVal, ENT_QUOTES, 'UTF-8') . '</td></tr>
                        <tr><td style="padding:8px;font-weight:bold;">Адрес:</td><td style="padding:8px;">' . htmlspecialchars($address, ENT_QUOTES, 'UTF-8') . '</td></tr>
                        <tr><td style="padding:8px;font-weight:bold;">Сообщение:</td><td style="padding:8px;">' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</td></tr>
                    </table>
                </div>
            </div>';

            send_mail(MAIL_FROM, 'Новая заявка на размещение: ' . $restaurantName, $htmlBody);

            $success = 'Заявка принята! Мы свяжемся с вами в течение 24 часов.';
        }
    }
}

$csrf = csrf_token();
require_once __DIR__ . '/includes/header.php';
?>

<section class="placement-page" style="margin-top:18px;">
    <div class="home-hero">
        <div class="hero-inner">
            <h1>Разместите ресторан на TableSpot</h1>
            <p>Бесплатно для ресторанов Курска и области</p>
        </div>
    </div>

    <div class="benefits-grid">
        <div class="benefit-card">
            <div class="benefit-icon"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#D4A017" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></div>
            <div class="benefit-title">Бесплатное размещение</div>
            <div class="benefit-text">Никаких ежемесячных платежей. Размещайте ресторан и принимайте бронирования бесплатно.</div>
        </div>
        <div class="benefit-card">
            <div class="benefit-icon"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#D4A017" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div>
            <div class="benefit-title">Онлайн-бронирования 24/7</div>
            <div class="benefit-text">Гости бронируют столики сами — без звонков и ожидания. В любое время суток.</div>
        </div>
        <div class="benefit-card">
            <div class="benefit-icon"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#D4A017" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div>
            <div class="benefit-title">База гостей</div>
            <div class="benefit-text">Рассылки и акции для ваших клиентов. Увеличивайте возврат гостей.</div>
        </div>
    </div>

    <div class="auth-card" style="max-width:640px;margin:32px auto;">
        <h2 style="margin-top:0;">Оставить заявку</h2>

        <?php if ($success !== ''): ?>
            <div class="form-success" role="status"><?= e($success) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="form-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success === ''): ?>
        <form class="auth-form" method="post">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

            <div class="form-row">
                <label for="restaurant_name">Название ресторана *</label>
                <input type="text" id="restaurant_name" name="restaurant_name" required maxlength="200">
            </div>

            <div class="form-row">
                <label for="contact_name">Имя контактного лица *</label>
                <input type="text" id="contact_name" name="contact_name" required maxlength="100">
            </div>

            <div class="form-row">
                <label for="phone">Телефон *</label>
                <input type="tel" id="phone" name="phone" required maxlength="20" placeholder="+7 999 123-45-67">
            </div>

            <div class="form-row">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required maxlength="100">
            </div>

            <div class="form-row">
                <label for="city">Город</label>
                <input type="text" id="city" name="city" maxlength="100" value="Курск">
            </div>

            <div class="form-row">
                <label for="address">Адрес ресторана</label>
                <input type="text" id="address" name="address" maxlength="255">
            </div>

            <div class="form-row">
                <label for="message">Сообщение / комментарий</label>
                <textarea id="message" name="message" rows="4" maxlength="2000" style="padding:12px 14px;border:1.5px solid var(--border);border-radius:12px;font-family:inherit;font-size:15px;resize:vertical;width:100%;"></textarea>
            </div>

            <button class="btn btn-primary" type="submit">Отправить заявку</button>
        </form>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
