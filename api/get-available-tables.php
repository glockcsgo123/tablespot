<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

function respond(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function toMysqlTime(string $value): ?string
{
    // Поддержим форматы HH:MM и HH:MM:SS
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
        return null;
    }
    if (strlen($value) === 5) {
        return $value . ':00';
    }
    return $value;
}

function parseDate(string $value): ?string
{
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        return null;
    }
    return $value;
}

$csrfToken = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
if (!validate_csrf(is_string($csrfToken) ? $csrfToken : null)) {
    respond(['success' => false, 'error' => 'Ошибка CSRF-токена.'], 403);
}

$restaurantId = isset($_POST['restaurant_id']) ? (int)$_POST['restaurant_id'] : (int)($_GET['restaurant_id'] ?? 0);
$bookingDateRaw = isset($_POST['booking_date']) ? (string)$_POST['booking_date'] : (string)($_GET['booking_date'] ?? '');
$timeStartRaw = isset($_POST['time_start']) ? (string)$_POST['time_start'] : (string)($_GET['time_start'] ?? '');
$timeEndRaw = isset($_POST['time_end']) ? (string)$_POST['time_end'] : (string)($_GET['time_end'] ?? '');
$guestsCount = isset($_POST['guests_count']) ? (int)$_POST['guests_count'] : (int)($_GET['guests_count'] ?? 0);

if ($restaurantId <= 0) {
    respond(['success' => false, 'error' => 'Некорректный restaurant_id.'], 400);
}

$bookingDate = parseDate($bookingDateRaw);
$timeStart = toMysqlTime($timeStartRaw);
$timeEnd = toMysqlTime($timeEndRaw);

if (!$bookingDate) {
    respond(['success' => false, 'error' => 'Некорректная дата.'], 400);
}
if (!$timeStart || !$timeEnd) {
    respond(['success' => false, 'error' => 'Некорректное время.'], 400);
}
if ($guestsCount <= 0 || $guestsCount > 50) {
    respond(['success' => false, 'error' => 'Некорректное количество гостей.'], 400);
}

if ($timeStart >= $timeEnd) {
    respond(['success' => false, 'error' => 'Время начала должно быть раньше окончания.'], 400);
}

// Проверяем, что время входит в рабочие часы
$stmt = $pdo->prepare("SELECT work_hours_start, work_hours_end FROM restaurants WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $restaurantId]);
$restaurant = $stmt->fetch();
if (!$restaurant) {
    respond(['success' => false, 'error' => 'Ресторан не найден.'], 404);
}

$workStart = (string)$restaurant['work_hours_start'];
$workEnd = (string)$restaurant['work_hours_end'];

if (!($timeStart >= $workStart && $timeEnd <= $workEnd)) {
    respond(['success' => false, 'error' => 'Выбранное время вне рабочих часов ресторана.'], 400);
}

// Достаем свободные столики подходящей вместимости и без пересечений по времени
$sql = "
    SELECT t.id, t.table_number, t.capacity
    FROM tables t
    WHERE t.restaurant_id = :rid
      AND t.capacity >= :guests
      AND NOT EXISTS (
        SELECT 1
        FROM bookings b
        WHERE b.table_id = t.id
          AND b.booking_date = :bdate
          AND b.status IN ('pending', 'confirmed')
          AND b.time_start < :time_end
          AND b.time_end > :time_start
      )
    ORDER BY t.table_number ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':rid' => $restaurantId,
    ':guests' => $guestsCount,
    ':bdate' => $bookingDate,
    ':time_start' => $timeStart,
    ':time_end' => $timeEnd,
]);
$tables = $stmt->fetchAll();

respond([
    'success' => true,
    'tables' => array_map(static function ($t) {
        return [
            'table_id' => (int)$t['id'],
            'table_number' => (int)$t['table_number'],
            'capacity' => (int)$t['capacity'],
        ];
    }, $tables)
]);

