<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mail.php';

$page_title = 'Рассылка';
require_admin();

$rid = current_admin_restaurant_id();
$csrf = csrf_token();
$error = '';
$success = '';

// Статистика подписчиков
$totalSubscribers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE email_marketing = 1")->fetchColumn();

// Рестораны с количеством подписанных гостей
$restaurantStats = $pdo->query("
    SELECT r.id, r.name, COUNT(DISTINCT u.id) AS guest_count
    FROM restaurants r
    JOIN `tables` t ON t.restaurant_id = r.id
    JOIN bookings b ON b.table_id = t.id
    JOIN users u ON u.id = b.user_id AND u.email_marketing = 1
    GROUP BY r.id, r.name
    ORDER BY guest_count DESC
")->fetchAll();

// Все рестораны для select
$allRestaurants = $pdo->query("SELECT id, name FROM restaurants WHERE is_active = 1 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Ошибка CSRF-токена.';
    } else {
        $targetRestaurant = isset($_POST['restaurant_id']) ? (int)$_POST['restaurant_id'] : 0;
        $subject          = isset($_POST['subject']) ? trim((string)$_POST['subject']) : '';
        $body             = isset($_POST['body']) ? trim((string)$_POST['body']) : '';

        if ($subject === '' || $body === '') {
            $error = 'Заполните тему и текст письма.';
        } else {
            // Получаем список email
            if ($targetRestaurant > 0) {
                $stmt = $pdo->prepare("
                    SELECT DISTINCT u.email
                    FROM users u
                    JOIN bookings b ON b.user_id = u.id
                    JOIN `tables` t ON t.id = b.table_id
                    WHERE t.restaurant_id = :rid AND u.email_marketing = 1
                    LIMIT 100
                ");
                $stmt->execute([':rid' => $targetRestaurant]);
            } else {
                $stmt = $pdo->query("SELECT DISTINCT email FROM users WHERE email_marketing = 1 LIMIT 100");
            }
            $emails = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            if (count($emails) === 0) {
                $error = 'Нет подписчиков для рассылки.';
            } else {
                $htmlBody = '
                <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
                    <div style="background:#2D6A4F;padding:24px;text-align:center;border-radius:12px 12px 0 0;">
                        <h1 style="color:white;margin:0;">TableSpot</h1>
                    </div>
                    <div style="padding:24px;background:#ffffff;border:1px solid #E5E7EB;">
                        ' . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) . '
                    </div>
                    <div style="padding:16px;text-align:center;color:#9CA3AF;font-size:12px;">
                        Вы получили это письмо, потому что подписаны на рассылку TableSpot.<br>
                        Чтобы отписаться, напишите на support@tablespot.local
                    </div>
                </div>';

                $sent = 0;
                foreach ($emails as $email) {
                    if (send_mail((string)$email, $subject, $htmlBody)) {
                        $sent++;
                    }
                }
                $success = "Рассылка завершена. Отправлено писем: {$sent} из " . count($emails) . '.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="admin-tables">
    <h1>Рассылка по базе гостей</h1>

    <div class="admin-links">
        <a class="btn secondary" href="<?= $appBaseUrl ?>/admin/index.php">Назад</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="form-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="form-success" role="status"><?= e($success) ?></div>
    <?php endif; ?>

    <div class="admin-card">
        <h2>Статистика подписчиков</h2>
        <p>Всего подписчиков (email_marketing=1): <strong><?= $totalSubscribers ?></strong></p>

        <?php if (count($restaurantStats) > 0): ?>
            <div class="admin-table-wrap" style="margin-top:12px;">
                <table class="admin-table">
                    <thead>
                        <tr><th>Ресторан</th><th>Гостей-подписчиков</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($restaurantStats as $rs): ?>
                        <tr>
                            <td><?= e((string)$rs['name']) ?></td>
                            <td><?= (int)$rs['guest_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="admin-card">
        <h2>Отправить рассылку</h2>
        <p class="muted">Лимит: 100 писем за одну отправку.</p>

        <form method="post" class="add-table-form" style="max-width:640px;">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

            <div class="form-row">
                <label for="restaurant_id">Ресторан</label>
                <select id="restaurant_id" name="restaurant_id">
                    <option value="0">Все рестораны</option>
                    <?php foreach ($allRestaurants as $rest): ?>
                        <option value="<?= (int)$rest['id'] ?>"><?= e((string)$rest['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="subject">Тема письма *</label>
                <input type="text" id="subject" name="subject" required maxlength="200">
            </div>

            <div class="form-row">
                <label for="body">Текст письма *</label>
                <textarea id="body" name="body" rows="8" required maxlength="5000"
                    style="padding:12px 14px;border:1.5px solid var(--border);border-radius:12px;font-family:inherit;font-size:15px;resize:vertical;width:100%;"></textarea>
            </div>

            <button class="btn btn-primary" type="submit" onclick="return confirm('Отправить рассылку?');">Отправить</button>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
