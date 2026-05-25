<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$page_title = 'Бронирования';
require_admin();
$rid = current_admin_restaurant_id();

$period = isset($_GET['period']) ? (string)$_GET['period'] : 'today';
if (!in_array($period, ['today', 'week', 'month'], true)) {
    $period = 'today';
}

$today = date('Y-m-d');
$startDate = $today;
$endDate = $today;
if ($period === 'week') {
    $endDate = date('Y-m-d', strtotime('+6 days'));
} elseif ($period === 'month') {
    $endDate = date('Y-m-d', strtotime('+29 days'));
}

$error = '';
$csrf = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
    $bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;

    if (!validate_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Ошибка CSRF-токена.';
    } elseif ($bookingId <= 0) {
        $error = 'Некорректный booking_id.';
    } elseif ($action === 'confirm') {
        $stmt = $pdo->prepare("
            UPDATE bookings b
            JOIN tables t ON t.id = b.table_id
            SET b.status = 'confirmed'
            WHERE b.id = :bid AND t.restaurant_id = :rid AND b.status = 'pending'
        ");
        $stmt->execute([':bid' => $bookingId, ':rid' => $rid]);
        if ($stmt->rowCount() <= 0) {
            $error = 'Не удалось подтвердить (возможно, запись уже обработана).';
        } else {
            redirect("/admin/bookings.php?period=" . e($period));
        }
    } elseif ($action === 'cancel') {
        $stmt = $pdo->prepare("
            UPDATE bookings b
            JOIN tables t ON t.id = b.table_id
            SET b.status = 'cancelled'
            WHERE b.id = :bid AND t.restaurant_id = :rid AND b.status <> 'cancelled'
        ");
        $stmt->execute([':bid' => $bookingId, ':rid' => $rid]);
        if ($stmt->rowCount() <= 0) {
            $error = 'Не удалось отменить (возможно, запись уже отменена).';
        } else {
            redirect("/admin/bookings.php?period=" . e($period));
        }
    } else {
        $error = 'Некорректное действие.';
    }
}

$stmt = $pdo->prepare("
    SELECT
        b.id AS booking_id,
        b.status,
        b.booking_date,
        b.time_start,
        b.time_end,
        b.guests_count,
        u.name AS user_name,
        u.phone AS user_phone,
        t.table_number
    FROM bookings b
    JOIN users u ON u.id = b.user_id
    JOIN tables t ON t.id = b.table_id
    WHERE t.restaurant_id = :rid
      AND b.booking_date BETWEEN :startDate AND :endDate
    ORDER BY b.booking_date ASC, b.time_start ASC
");
$stmt->execute([':rid' => $rid, ':startDate' => $startDate, ':endDate' => $endDate]);
$bookings = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="admin-bookings">
    <h1>Бронирования: <?= e($period) ?></h1>

    <div class="admin-links">
        <a class="btn" href="<?= $appBaseUrl ?>/admin/bookings.php?period=today">Сегодня</a>
        <a class="btn" href="<?= $appBaseUrl ?>/admin/bookings.php?period=week">Неделя</a>
        <a class="btn" href="<?= $appBaseUrl ?>/admin/bookings.php?period=month">Месяц</a>
        <a class="btn secondary" href="<?= $appBaseUrl ?>/admin/tables.php">Столики</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="form-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (count($bookings) === 0): ?>
        <div class="empty">Нет бронирований за выбранный период.</div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Клиент</th>
                        <th>Дата/время</th>
                        <th>Столик</th>
                        <th>Гостей</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($bookings as $b): ?>
                    <?php
                    $bid = (int)$b['booking_id'];
                    $status = (string)$b['status'];
                    ?>
                    <tr>
                        <td><?= $bid ?></td>
                        <td>
                            <div><?= e((string)$b['user_name']) ?></div>
                            <div class="muted"><?= e((string)$b['user_phone']) ?></div>
                        </td>
                        <td>
                            <div><?= e((string)$b['booking_date']) ?></div>
                            <div><?= e((string)$b['time_start']) ?> - <?= e((string)$b['time_end']) ?></div>
                        </td>
                        <td>№<?= (int)$b['table_number'] ?></td>
                        <td><?= (int)$b['guests_count'] ?></td>
                        <td><span class="badge <?= e($status) ?>"><?= e($status) ?></span></td>
                        <td>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="booking_id" value="<?= $bid ?>">
                                <input type="hidden" name="action" value="confirm">
                                <?php if ($status === 'pending'): ?>
                                    <button class="btn" type="submit">Подтвердить</button>
                                <?php else: ?>
                                    <button class="btn" type="submit" disabled>Подтвердить</button>
                                <?php endif; ?>
                            </form>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="booking_id" value="<?= $bid ?>">
                                <input type="hidden" name="action" value="cancel">
                                <?php if ($status !== 'cancelled'): ?>
                                    <button class="btn danger" type="submit" onclick="return confirm('Отменить бронирование?');">Отменить</button>
                                <?php else: ?>
                                    <button class="btn danger" type="submit" disabled>Отменить</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

