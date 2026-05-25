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

function normalizePhone(string $value): ?string
{
    $value = trim($value);
    if (!preg_match('/^[0-9+][0-9]{6,20}$/', $value)) {
        return null;
    }
    return $value;
}

$csrfToken = $_POST['csrf_token'] ?? null;
if (!validate_csrf(is_string($csrfToken) ? $csrfToken : null)) {
    respond(['success' => false, 'error' => 'Ошибка CSRF-токена.'], 403);
}

$restaurantId = isset($_POST['restaurant_id']) ? (int)$_POST['restaurant_id'] : 0;
$tableId = isset($_POST['table_id']) ? (int)$_POST['table_id'] : 0;
$bookingDateRaw = isset($_POST['booking_date']) ? (string)$_POST['booking_date'] : '';
$timeStartRaw = isset($_POST['time_start']) ? (string)$_POST['time_start'] : '';
$timeEndRaw = isset($_POST['time_end']) ? (string)$_POST['time_end'] : '';
$guestsCount = isset($_POST['guests_count']) ? (int)$_POST['guests_count'] : 0;

$guestName = isset($_POST['guest_name']) ? trim((string)$_POST['guest_name']) : '';
$guestPhoneRaw = isset($_POST['guest_phone']) ? trim((string)$_POST['guest_phone']) : '';

if ($restaurantId <= 0 || $tableId <= 0) {
    respond(['success' => false, 'error' => 'Некорректные идентификаторы.'], 400);
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
if ($timeStart >= $timeEnd) {
    respond(['success' => false, 'error' => 'Некорректный диапазон времени.'], 400);
}
if ($guestsCount <= 0 || $guestsCount > 50) {
    respond(['success' => false, 'error' => 'Некорректное количество гостей.'], 400);
}

// Проверяем вместимость и принадлежность столика
$stmt = $pdo->prepare("
    SELECT t.id, t.capacity, r.work_hours_start, r.work_hours_end
    FROM tables t
    JOIN restaurants r ON r.id = t.restaurant_id
    WHERE t.id = :tid AND t.restaurant_id = :rid
    LIMIT 1
");
$stmt->execute([':tid' => $tableId, ':rid' => $restaurantId]);
$table = $stmt->fetch();
if (!$table) {
    respond(['success' => false, 'error' => 'Столик не найден.'], 404);
}
if ((int)$table['capacity'] < $guestsCount) {
    respond(['success' => false, 'error' => 'Недостаточная вместимость столика.'], 400);
}

// Проверяем рабочие часы ресторана
$workStart = (string)$table['work_hours_start'];
$workEnd = (string)$table['work_hours_end'];
if (!($timeStart >= $workStart && $timeEnd <= $workEnd)) {
    respond(['success' => false, 'error' => 'Время вне рабочих часов ресторана.'], 400);
}

$userId = current_user_id();
if ($userId === null) {
    $guestPhone = normalizePhone($guestPhoneRaw);
    if ($guestPhone === null) {
        respond(['success' => false, 'error' => 'Некорректный телефон.'], 400);
    }

    if ($guestName === '') {
        $guestName = 'Гость';
    }
    if (mb_strlen($guestName) > 100) {
        $guestName = mb_substr($guestName, 0, 100);
    }

    // Создадим/найдём пользователя по телефону
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = :phone LIMIT 1");
        $stmt->execute([':phone' => $guestPhone]);
        $existing = $stmt->fetch();
        if ($existing) {
            $userId = (int)$existing['id'];
        } else {
            $email = 'guest_' . substr(sha1($guestPhone), 0, 12) . '@tablespot.local';
            if (mb_strlen($email) > 100) {
                $email = substr($email, 0, 100);
            }

            // Пароль для гостя генерируем случайный, чтобы не давать вход без регистрации
            $randomPassword = bin2hex(random_bytes(10));
            $hash = password_hash($randomPassword, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, phone, password_hash)
                VALUES (:name, :email, :phone, :hash)
            ");
            $stmt->execute([
                ':name' => $guestName,
                ':email' => $email,
                ':phone' => $guestPhone,
                ':hash' => $hash,
            ]);
            $userId = (int)$pdo->lastInsertId();
        }

        // Проверяем пересечения бронирований (повторно на сервере)
        $stmt = $pdo->prepare("
            SELECT 1
            FROM bookings
            WHERE table_id = :tid
              AND booking_date = :bdate
              AND status IN ('pending', 'confirmed')
              AND time_start < :time_end
              AND time_end > :time_start
            LIMIT 1
        ");
        $stmt->execute([
            ':tid' => $tableId,
            ':bdate' => $bookingDate,
            ':time_end' => $timeEnd,
            ':time_start' => $timeStart
        ]);
        $conflict = $stmt->fetch();
        if ($conflict) {
            $pdo->rollBack();
            respond(['success' => false, 'error' => 'Этот столик уже занят на выбранное время.'], 409);
        }

        $stmt = $pdo->prepare("
            INSERT INTO bookings (user_id, table_id, booking_date, time_start, time_end, guests_count, status)
            VALUES (:uid, :tid, :bdate, :start, :end, :guests, 'pending')
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':tid' => $tableId,
            ':bdate' => $bookingDate,
            ':start' => $timeStart,
            ':end' => $timeEnd,
            ':guests' => $guestsCount,
        ]);
        $bookingId = (int)$pdo->lastInsertId();

        $pdo->commit();
        $_SESSION['user_id'] = $userId; // чтобы гость видел бронирование в кабинете
        respond(['success' => true, 'booking_id' => $bookingId, 'status' => 'pending']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        respond(['success' => false, 'error' => 'Ошибка при создании бронирования.'], 500);
    }
} else {
    // Логиннутый пользователь
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM bookings
            WHERE table_id = :tid
              AND booking_date = :bdate
              AND status IN ('pending', 'confirmed')
              AND time_start < :time_end
              AND time_end > :time_start
            LIMIT 1
        ");
        $stmt->execute([
            ':tid' => $tableId,
            ':bdate' => $bookingDate,
            ':time_end' => $timeEnd,
            ':time_start' => $timeStart
        ]);
        $conflict = $stmt->fetch();
        if ($conflict) {
            $pdo->rollBack();
            respond(['success' => false, 'error' => 'Этот столик уже занят на выбранное время.'], 409);
        }

        $stmt = $pdo->prepare("
            INSERT INTO bookings (user_id, table_id, booking_date, time_start, time_end, guests_count, status)
            VALUES (:uid, :tid, :bdate, :start, :end, :guests, 'pending')
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':tid' => $tableId,
            ':bdate' => $bookingDate,
            ':start' => $timeStart,
            ':end' => $timeEnd,
            ':guests' => $guestsCount,
        ]);
        $bookingId = (int)$pdo->lastInsertId();

        $pdo->commit();
        respond(['success' => true, 'booking_id' => $bookingId, 'status' => 'pending']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        respond(['success' => false, 'error' => 'Ошибка при создании бронирования.'], 500);
    }
}

