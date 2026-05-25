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

if (!is_logged_in()) {
    respond(['success' => false, 'error' => 'auth_required'], 401);
}

$csrfToken = $_POST['csrf_token'] ?? null;
if (!validate_csrf(is_string($csrfToken) ? $csrfToken : null)) {
    respond(['success' => false, 'error' => 'csrf'], 403);
}

$restaurantId = isset($_POST['restaurant_id']) ? (int)$_POST['restaurant_id'] : 0;
if ($restaurantId <= 0) {
    respond(['success' => false, 'error' => 'invalid_id'], 400);
}

$userId = current_user_id();

// Check if already favorited
$stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = :uid AND restaurant_id = :rid LIMIT 1");
$stmt->execute([':uid' => $userId, ':rid' => $restaurantId]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $pdo->prepare("DELETE FROM favorites WHERE id = :id");
    $stmt->execute([':id' => (int)$existing['id']]);
    respond(['success' => true, 'favorited' => false]);
} else {
    $stmt = $pdo->prepare("INSERT INTO favorites (user_id, restaurant_id) VALUES (:uid, :rid)");
    $stmt->execute([':uid' => $userId, ':rid' => $restaurantId]);
    respond(['success' => true, 'favorited' => true]);
}
