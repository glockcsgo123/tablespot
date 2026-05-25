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

$csrfToken = $_POST['csrf_token'] ?? null;
if (!validate_csrf(is_string($csrfToken) ? $csrfToken : null)) {
    respond(['success' => false, 'error' => 'Ошибка CSRF-токена.'], 403);
}

$bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
if ($bookingId <= 0) {
    respond(['success' => false, 'error' => 'Некорректный booking_id.'], 400);
}

$pdo->beginTransaction();
try {
    if (is_logged_in()) {
        $userId = current_user_id();
        $stmt = $pdo->prepare("
            UPDATE bookings
            SET status = 'cancelled'
            WHERE id = :bid AND user_id = :uid AND status <> 'cancelled'
        ");
        $stmt->execute([':bid' => $bookingId, ':uid' => $userId]);
    } elseif (is_admin_logged_in()) {
        $rid = current_admin_restaurant_id();
        $stmt = $pdo->prepare("
            UPDATE bookings b
            JOIN tables t ON t.id = b.table_id
            SET b.status = 'cancelled'
            WHERE b.id = :bid AND t.restaurant_id = :rid AND b.status <> 'cancelled'
        ");
        $stmt->execute([':bid' => $bookingId, ':rid' => $rid]);
    } else {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'Требуется авторизация.'], 401);
    }

    if ($stmt->rowCount() <= 0) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'Бронирование не найдено или уже отменено.'], 404);
    }

    $pdo->commit();
    respond(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond(['success' => false, 'error' => 'Ошибка отмены бронирования.'], 500);
}

