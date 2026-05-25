<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$page_title = 'Админ-панель';
require_admin();

$rid = current_admin_restaurant_id();

function countBookings(PDO $pdo, int $rid, string $startDate, string $endDate): array
{
    $stmt = $pdo->prepare("
        SELECT
            SUM(status = 'pending') AS pending_count,
            SUM(status = 'confirmed') AS confirmed_count,
            SUM(status = 'cancelled') AS cancelled_count
        FROM bookings b
        JOIN `tables` t ON t.id = b.table_id
        WHERE t.restaurant_id = :rid
          AND b.booking_date BETWEEN :startDate AND :endDate
    ");
    $stmt->execute([
        ':rid' => $rid,
        ':startDate' => $startDate,
        ':endDate' => $endDate
    ]);
    $row = $stmt->fetch();
    return [
        'pending' => (int)($row['pending_count'] ?? 0),
        'confirmed' => (int)($row['confirmed_count'] ?? 0),
        'cancelled' => (int)($row['cancelled_count'] ?? 0),
    ];
}

$today = date('Y-m-d');
$weekEnd = date('Y-m-d', strtotime('+6 days'));
$monthEnd = date('Y-m-d', strtotime('+29 days'));

$todayCounts = countBookings($pdo, $rid, $today, $today);
$weekCounts = countBookings($pdo, $rid, $today, $weekEnd);
$monthCounts = countBookings($pdo, $rid, $today, $monthEnd);

// Новые заявки на размещение
$newRequestsCount = (int)$pdo->query("SELECT COUNT(*) FROM placement_requests WHERE status = 'new'")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="admin-dashboard">
    <h1>Админ-панель</h1>

    <div class="admin-links">
        <a class="btn" href="<?= $appBaseUrl ?>/admin/bookings.php?period=today">Бронирования (сегодня)</a>
        <a class="btn" href="<?= $appBaseUrl ?>/admin/bookings.php?period=week">Бронирования (неделя)</a>
        <a class="btn" href="<?= $appBaseUrl ?>/admin/bookings.php?period=month">Бронирования (месяц)</a>
        <a class="btn secondary" href="<?= $appBaseUrl ?>/admin/tables.php">Столики</a>
        <a class="btn secondary" href="<?= $appBaseUrl ?>/admin/banners.php">Баннеры</a>
        <a class="btn secondary" href="<?= $appBaseUrl ?>/admin/requests.php">Заявки на размещение<?= $newRequestsCount > 0 ? ' (' . $newRequestsCount . ')' : '' ?></a>
        <a class="btn secondary" href="<?= $appBaseUrl ?>/admin/newsletter.php">Рассылка</a>
    </div>

    <div class="stats">
        <div class="stat">
            <h3>Сегодня</h3>
            <div class="muted">Ожидают: <?= $todayCounts['pending'] ?>, Подтверждены: <?= $todayCounts['confirmed'] ?></div>
        </div>
        <div class="stat">
            <h3>Неделя</h3>
            <div class="muted">Ожидают: <?= $weekCounts['pending'] ?>, Подтверждены: <?= $weekCounts['confirmed'] ?></div>
        </div>
        <div class="stat">
            <h3>Месяц</h3>
            <div class="muted">Ожидают: <?= $monthCounts['pending'] ?>, Подтверждены: <?= $monthCounts['confirmed'] ?></div>
        </div>
    </div>

    <?php if ($newRequestsCount > 0): ?>
    <div class="admin-card" style="margin-top:16px;">
        <h3>Новые заявки на размещение: <?= $newRequestsCount ?></h3>
        <a class="btn" href="<?= $appBaseUrl ?>/admin/requests.php">Посмотреть</a>
    </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
