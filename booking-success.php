<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Бронирование оформлено';

$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if ($bookingId <= 0) {
    http_response_code(400);
    echo "Некорректный booking_id.";
    exit;
}

$booking = null;
$stmt = $pdo->prepare("
    SELECT
        b.id AS booking_id,
        b.status,
        b.booking_date,
        b.time_start,
        b.time_end,
        b.guests_count,
        u.id AS user_id,
        t.id AS table_id,
        t.table_number,
        r.id AS restaurant_id,
        r.name AS restaurant_name
    FROM bookings b
    JOIN users u ON u.id = b.user_id
    JOIN tables t ON t.id = b.table_id
    JOIN restaurants r ON r.id = t.restaurant_id
    WHERE b.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $bookingId]);
$booking = $stmt->fetch();

require_once __DIR__ . '/includes/header.php';
?>

<section class="success">
    <h1>Готово!</h1>
    <?php if (!$booking): ?>
        <p>Не удалось загрузить информацию о бронировании.</p>
    <?php else: ?>
        <?php $status = (string)$booking['status']; ?>
        <p>
            Ваше бронирование оформлено<?= $status === 'pending' ? ', администратор скоро подтвердит.' : '.' ?>
        </p>

        <?php if (is_logged_in() && (int)$booking['user_id'] === (int)current_user_id()): ?>
            <div class="success-details">
                <p><strong>Ресторан:</strong> <?= e((string)$booking['restaurant_name']) ?></p>
                <p><strong>Столик:</strong> №<?= (int)$booking['table_number'] ?></p>
                <p><strong>Дата:</strong> <?= e((string)$booking['booking_date']) ?></p>
                <p><strong>Время:</strong> <?= e((string)$booking['time_start']) ?> - <?= e((string)$booking['time_end']) ?></p>
                <p><strong>Гостей:</strong> <?= (int)$booking['guests_count'] ?></p>
                <p><strong>Статус:</strong> <?= e($status) ?></p>
            </div>
            <a class="btn btn-secondary" href="<?= $appBaseUrl ?>/profile.php">Перейти в кабинет</a>
        <?php else: ?>
            <a class="btn btn-secondary" href="<?= $appBaseUrl ?>/auth/login.php">Войти, чтобы посмотреть брони</a>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

