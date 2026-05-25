<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Бронирование';

$restaurantId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($restaurantId <= 0) {
    http_response_code(400);
    echo "Некорректный параметр ресторана.";
    exit;
}

$stmt = $pdo->prepare("SELECT id, name, address, description, cuisine_type, image, city, rating, work_hours_start, work_hours_end FROM restaurants WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $restaurantId]);
$restaurant = $stmt->fetch();

if (!$restaurant) {
    http_response_code(404);
    echo "Ресторан не найден.";
    exit;
}

$isUserLogged = is_logged_in();
$userId = current_user_id();
$csrf = csrf_token();

require_once __DIR__ . '/includes/header.php';
?>

<section class="restaurant-page">
    <div class="restaurant-banner">
        <?php
        $img = !empty($restaurant['image']) ? (string)$restaurant['image'] : '';
        $imgSrc = '';
        if ($img !== '' && preg_match('~^https?://~i', $img)) {
            $imgSrc = $img;
        } else {
            $imgFile = $img !== '' ? basename($img) : '';
            $imgPath = $imgFile !== '' ? (__DIR__ . '/assets/images/' . $imgFile) : '';
            if ($imgFile !== '' && is_file($imgPath)) {
                $imgSrc = rtrim($appBaseUrl, '/') . '/assets/images/' . $imgFile;
            }
        }
        ?>

        <?php if ($imgSrc !== ''): ?>
            <img class="restaurant-banner-img" src="<?= e($imgSrc) ?>" alt="<?= e((string)$restaurant['name']) ?>" loading="lazy" style="object-position:center;">
        <?php else: ?>
            <div class="restaurant-banner-placeholder" aria-hidden="true">
                <span class="placeholder-icon"><svg width="48" height="48" viewBox="0 0 28 32" fill="none"><rect x="2" y="12" width="24" height="4" rx="2" fill="#333"/><rect x="4" y="16" width="4" height="10" rx="2" fill="#333"/><rect x="20" y="16" width="4" height="10" rx="2" fill="#333"/></svg></span>
            </div>
        <?php endif; ?>
        <div class="restaurant-banner-overlay-text" style="position:absolute;bottom:40px;left:0;right:0;z-index:2;text-align:center;padding:0 20px;">
            <h1 style="font-family:'Playfair Display',serif;font-size:clamp(28px,5vw,48px);color:#fff;margin:0;text-shadow:0 2px 20px rgba(0,0,0,0.5);"><?= e((string)$restaurant['name']) ?></h1>
            <div style="margin-top:10px;"><span class="cuisine-badge"><?= e((string)$restaurant['cuisine_type']) ?></span></div>
        </div>
    </div>

    <div class="restaurant-layout">
        <div class="restaurant-details">
            <h1 class="restaurant-title"><?= e((string)$restaurant['name']) ?></h1>
            <div class="restaurant-subline">
                <span class="cuisine-badge"><?= e((string)$restaurant['cuisine_type']) ?></span>
                <div class="restaurant-address"><?= e((string)$restaurant['address']) ?></div>
            </div>

            <div class="restaurant-description">
                <?= nl2br(e((string)$restaurant['description'])) ?>
            </div>

            <div class="restaurant-hours">
                Часы работы: <strong><?= e((string)$restaurant['work_hours_start']) ?></strong> – <strong><?= e((string)$restaurant['work_hours_end']) ?></strong>
            </div>
        </div>

        <aside class="booking-aside">
            <div class="booking-card booking-card-sticky">
                <h2 class="booking-title">Забронировать столик</h2>

                <form id="booking-form" class="booking-form" method="post">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="restaurant_id" id="restaurant_id" value="<?= (int)$restaurant['id'] ?>">

            <div class="form-row">
                <label for="booking_date">Дата</label>
                <input type="date" id="booking_date" name="booking_date" required>
            </div>

            <div class="form-row">
                <label for="time_start">Время (начало)</label>
                <select id="time_start" name="time_start" required></select>
            </div>

            <div class="form-row">
                <label for="time_end">Время (окончание)</label>
                <select id="time_end" name="time_end" required></select>
            </div>

            <div class="form-row">
                <label for="guests_count">Гостей</label>
                <select id="guests_count" name="guests_count" required>
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <?php if (!$isUserLogged): ?>
                <div class="form-row">
                    <label for="guest_name">Имя (для брони)</label>
                    <input type="text" id="guest_name" name="guest_name" placeholder="Например, Артем" maxlength="100">
                </div>
                <div class="form-row">
                    <label for="guest_phone">Телефон</label>
                    <input type="tel" id="guest_phone" name="guest_phone" placeholder="+7 999 123-45-67" required>
                    <small class="muted">Без регистрации достаточно номера телефона.</small>
                </div>
            <?php endif; ?>

            <input type="hidden" id="selected_table_id" name="table_id" value="">

            <div class="tables-block">
                <div class="tables-header">
                    <h3>Доступные столики</h3>
                    <span id="tables-status" class="muted"></span>
                </div>
                <div id="tables-container" class="tables-container">
                    <div class="skeleton">Выберите дату и время.</div>
                </div>
            </div>

            <button id="book-btn" class="btn btn-primary" type="submit" disabled>
                Забронировать
            </button>

            <div id="booking-error" class="form-error" role="alert"></div>
                </form>
            </div>
        </aside>
</section>

<script>
    window.TABLESPOT_CONFIG = {
        restaurantId: <?= (int)$restaurant['id'] ?>,
        workHoursStart: "<?= e((string)$restaurant['work_hours_start']) ?>",
        workHoursEnd: "<?= e((string)$restaurant['work_hours_end']) ?>",
        isUserLogged: <?= $isUserLogged ? 'true' : 'false' ?>,
        appBaseUrl: <?= json_encode($appBaseUrl, JSON_UNESCAPED_UNICODE) ?>,
        csrfToken: <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>
    };
    window.TABLESPOT_RESTAURANT_CONTEXT = {
        defaultGuestsCount: 2
    };
</script>

<script src="<?= $appBaseUrl ?>/assets/js/app.js" defer></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

