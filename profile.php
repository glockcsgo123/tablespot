<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Личный кабинет';
require_login();

$userId = current_user_id();
$csrf = csrf_token();

// Данные пользователя
$stmtUser = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = :uid LIMIT 1");
$stmtUser->execute([':uid' => $userId]);
$user = $stmtUser->fetch();

// Бронирования
$stmt = $pdo->prepare("
    SELECT
        b.id AS booking_id,
        b.status,
        b.booking_date,
        b.time_start,
        b.time_end,
        b.guests_count,
        r.name AS restaurant_name,
        t.table_number
    FROM bookings b
    JOIN `tables` t ON t.id = b.table_id
    JOIN restaurants r ON r.id = t.restaurant_id
    WHERE b.user_id = :uid
    ORDER BY b.booking_date DESC, b.time_start DESC
");
$stmt->execute([':uid' => $userId]);
$bookings = $stmt->fetchAll();

// Рекомендации: самая частая кухня пользователя
$stmtFav = $pdo->prepare("
    SELECT r2.cuisine_type
    FROM bookings b
    JOIN `tables` t ON t.id = b.table_id
    JOIN restaurants r2 ON r2.id = t.restaurant_id
    WHERE b.user_id = :uid
    GROUP BY r2.cuisine_type
    ORDER BY COUNT(*) DESC
    LIMIT 1
");
$stmtFav->execute([':uid' => $userId]);
$favCuisine = $stmtFav->fetchColumn();

$recommendations = [];
if ($favCuisine) {
    $stmtRec = $pdo->prepare("
        SELECT r.id, r.name, r.cuisine_type, r.image, r.rating
        FROM restaurants r
        WHERE r.cuisine_type = :cuisine
          AND r.id NOT IN (
            SELECT DISTINCT t2.restaurant_id
            FROM bookings b2
            JOIN `tables` t2 ON t2.id = b2.table_id
            WHERE b2.user_id = :uid
          )
          AND r.is_active = 1
        ORDER BY r.rating DESC
        LIMIT 3
    ");
    $stmtRec->execute([':cuisine' => $favCuisine, ':uid' => $userId]);
    $recommendations = $stmtRec->fetchAll();
}

// Если рекомендаций нет — топ-3 по рейтингу
if (count($recommendations) === 0) {
    $stmtTop = $pdo->prepare("
        SELECT r.id, r.name, r.cuisine_type, r.image, r.rating
        FROM restaurants r
        WHERE r.is_active = 1
          AND r.id NOT IN (
            SELECT DISTINCT t3.restaurant_id
            FROM bookings b3
            JOIN `tables` t3 ON t3.id = b3.table_id
            WHERE b3.user_id = :uid
          )
        ORDER BY r.rating DESC
        LIMIT 3
    ");
    $stmtTop->execute([':uid' => $userId]);
    $recommendations = $stmtTop->fetchAll();
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="profile-page">
    <h1>Привет, <?= e((string)($user['name'] ?? 'Гость')) ?></h1>

    <div class="admin-card" style="margin-bottom: 24px;">
        <h2 style="margin-top:0;">Мои данные</h2>
        <p><strong>Имя:</strong> <?= e((string)($user['name'] ?? '')) ?></p>
        <p><strong>Email:</strong> <?= e((string)($user['email'] ?? '')) ?></p>
        <p><strong>Телефон:</strong> <?= e((string)($user['phone'] ?? '')) ?></p>
    </div>

    <h2>Мои бронирования</h2>

    <?php if (count($bookings) === 0): ?>
        <div class="empty">Пока нет бронирований.</div>
    <?php else: ?>
        <div class="profile-list">
            <?php foreach ($bookings as $b): ?>
                <?php
                $bid = (int)$b['booking_id'];
                $status = (string)$b['status'];
                $statusLabels = [
                    'pending'   => 'Ожидает подтверждения',
                    'confirmed' => 'Подтверждено',
                    'cancelled' => 'Отменено',
                ];
                $statusLabel = $statusLabels[$status] ?? $status;
                ?>
                <div class="profile-item">
                    <div class="profile-item-main">
                        <div class="profile-item-title">
                            <strong><?= e((string)$b['restaurant_name']) ?></strong>
                            <span class="badge <?= e($status) ?>"><?= e($statusLabel) ?></span>
                        </div>
                        <div class="muted">
                            Дата: <?= e((string)$b['booking_date']) ?>,
                            Время: <?= e((string)$b['time_start']) ?>-<?= e((string)$b['time_end']) ?>,
                            Столик №<?= (int)$b['table_number'] ?>,
                            Гостей: <?= (int)$b['guests_count'] ?>
                        </div>
                    </div>
                    <div class="profile-item-actions">
                        <?php if ($status !== 'cancelled'): ?>
                            <button
                                class="btn danger cancel-booking-btn"
                                type="button"
                                data-booking-id="<?= $bid ?>"
                                data-csrf="<?= e($csrf) ?>"
                            >
                                Отменить
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div id="profile-error" class="form-error" role="alert"></div>

    <?php if (count($recommendations) > 0): ?>
    <section class="recommendations">
        <h2>Вам может понравиться</h2>
        <div class="rec-grid">
            <?php foreach ($recommendations as $rec): ?>
                <?php
                $recImg = !empty($rec['image']) && preg_match('~^https?://~i', (string)$rec['image'])
                    ? (string)$rec['image'] : '';
                ?>
                <div class="rec-card">
                    <?php if ($recImg !== ''): ?>
                        <img src="<?= e($recImg) ?>" alt="<?= e((string)$rec['name']) ?>" loading="lazy">
                    <?php else: ?>
                        <div style="height:140px;background:#222;display:flex;align-items:center;justify-content:center;"><svg width="32" height="32" viewBox="0 0 28 32" fill="none"><rect x="2" y="12" width="24" height="4" rx="2" fill="#333"/><rect x="4" y="16" width="4" height="10" rx="2" fill="#333"/><rect x="20" y="16" width="4" height="10" rx="2" fill="#333"/></svg></div>
                    <?php endif; ?>
                    <div class="rec-card-body">
                        <strong><?= e((string)$rec['name']) ?></strong>
                        <div class="muted"><?= e((string)($rec['cuisine_type'] ?? '')) ?> · <?= number_format((float)($rec['rating'] ?? 4.5), 1, '.', '') ?></div>
                        <a class="btn btn-primary" style="margin-top:10px;width:100%;" href="<?= $appBaseUrl ?>/restaurant.php?id=<?= (int)$rec['id'] ?>">Забронировать</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</section>

<script>
    var appBaseUrl = <?= json_encode($appBaseUrl, JSON_UNESCAPED_UNICODE) ?>;
    document.addEventListener('click', async function(e) {
        var btn = e.target.closest('.cancel-booking-btn');
        if (!btn) return;

        var bookingId = btn.getAttribute('data-booking-id');
        var csrfToken = btn.getAttribute('data-csrf');
        if (!confirm('Отменить бронирование?')) return;

        var errorEl = document.getElementById('profile-error');
        errorEl.textContent = '';

        try {
            var res = await fetch(appBaseUrl + '/api/cancel-booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    csrf_token: csrfToken,
                    booking_id: bookingId
                })
            });
            var data = await res.json();
            if (!data.success) {
                errorEl.textContent = data.error || 'Не удалось отменить бронирование.';
                return;
            }
            window.location.reload();
        } catch (err) {
            errorEl.textContent = 'Ошибка сети. Попробуйте ещё раз.';
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
