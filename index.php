<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Каталог ресторанов';

// Загружаем ВСЕ активные рестораны — фильтрация на JS
$stmt = $pdo->prepare("SELECT r.id, r.name, r.address, r.description, r.cuisine_type, r.image, r.city, r.rating, r.lat, r.lng, r.work_hours_start, r.work_hours_end FROM restaurants r WHERE r.is_active = 1 ORDER BY r.rating DESC, r.id DESC");
$stmt->execute();
$restaurants = $stmt->fetchAll();

// Список кухонь для фильтра
$cuisineOptions = $pdo->query("SELECT DISTINCT cuisine_type FROM restaurants WHERE cuisine_type IS NOT NULL AND cuisine_type <> '' AND is_active = 1 ORDER BY cuisine_type ASC")->fetchAll();

// Баннеры
$banners = $pdo->query("SELECT id, title, subtitle, button_text, button_url, bg_color FROM banners WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();

// Избранные ID текущего пользователя
$favoriteIds = [];
if (is_logged_in()) {
    $stmtFav = $pdo->prepare("SELECT restaurant_id FROM favorites WHERE user_id = :uid");
    $stmtFav->execute([':uid' => current_user_id()]);
    $favoriteIds = array_column($stmtFav->fetchAll(), 'restaurant_id');
}
$csrf = csrf_token();

require_once __DIR__ . '/includes/header.php';
?>

<?php if (count($banners) > 0): ?>
<section class="banners-section">
    <div class="banner-carousel" id="banner-carousel">
        <?php foreach ($banners as $i => $b): ?>
            <div class="banner-slide <?= $i === 0 ? 'active' : '' ?>" style="background: <?= e((string)$b['bg_color']) ?>;">
                <div class="banner-content">
                    <div class="banner-title"><?= e((string)$b['title']) ?></div>
                    <?php if (!empty($b['subtitle'])): ?>
                        <div class="banner-subtitle"><?= e((string)$b['subtitle']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($b['button_text']) && !empty($b['button_url'])): ?>
                        <?php $btnUrl = (string)$b['button_url']; ?>
                        <?php if (str_starts_with($btnUrl, '/') || str_starts_with($btnUrl, 'http')): ?>
                            <a class="banner-btn" href="<?= $appBaseUrl . e($btnUrl) ?>"><?= e((string)$b['button_text']) ?></a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (count($banners) > 1): ?>
            <div class="banner-dots">
                <?php foreach ($banners as $i => $b): ?>
                    <button class="banner-dot <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>" aria-label="Слайд <?= $i + 1 ?>"></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<section class="home-hero">
    <div class="hero-inner">
        <span class="city-badge" style="margin-bottom: 8px;">Курск</span>
        <h1>Забронируйте идеальный столик</h1>
        <p>Лучшие рестораны Курска — онлайн, без звонков, 24/7</p>

        <div class="hero-search" role="search">
            <div class="search-field search-field--wide">
                <label for="cuisine-filter">Кухня</label>
                <div class="custom-select" id="cuisine-dropdown">
                    <button class="custom-select-btn" type="button">
                        <span class="custom-select-text">Все кухни</span>
                        <svg class="custom-select-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#D4A017" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </button>
                    <div class="custom-select-list">
                        <div class="custom-select-option selected" data-value="">Все кухни</div>
                        <?php foreach ($cuisineOptions as $row): ?>
                            <?php $opt = (string)($row['cuisine_type'] ?? ''); ?>
                            <div class="custom-select-option" data-value="<?= e($opt) ?>"><?= e($opt) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <select id="cuisine-filter" style="display:none;">
                    <option value="">Все кухни</option>
                    <?php foreach ($cuisineOptions as $row): ?>
                        <?php $opt = (string)($row['cuisine_type'] ?? ''); ?>
                        <option value="<?= e($opt) ?>"><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary hero-find-btn" type="button" id="hero-find-btn">Найти</button>
            <button class="btn btn-secondary hero-search-btn" type="button" id="filter-reset">Сбросить</button>
        </div>
    </div>
</section>

<div class="stats-bar">
    <div class="stats-inner">
        <div class="stat-item">
            <span class="stat-num"><?= count($restaurants) ?>+</span>
            <span class="stat-label">ресторанов</span>
        </div>
        <div class="stat-item">
            <span class="stat-num">24/7</span>
            <span class="stat-label">бронирование</span>
        </div>
        <div class="stat-item">
            <span class="stat-num">0 ₽</span>
            <span class="stat-label">комиссия</span>
        </div>
        <div class="stat-item">
            <span class="stat-num stat-num--icon" data-icon="location"></span>
            <span class="stat-label">Курск</span>
        </div>
    </div>
</div>

<div class="filter-bar">
    <div class="filter-bar-inner">
        <div class="filter-group">
            <span class="filter-label">Рейтинг:</span>
            <button class="filter-chip active" data-rating="" type="button">Любой</button>
            <button class="filter-chip" data-rating="4.0" type="button">4.0+</button>
            <button class="filter-chip" data-rating="4.5" type="button">4.5+</button>
        </div>
        <div class="filter-group">
            <button class="filter-chip filter-toggle" id="filter-open-now" type="button">Открыто сейчас</button>
        </div>
    </div>
</div>

<section class="restaurants-grid">
    <?php if (count($restaurants) === 0): ?>
        <div class="empty">Ничего не найдено.</div>
    <?php else: ?>
        <?php foreach ($restaurants as $idx => $r): ?>
            <?php
            $rid = (int)$r['id'];
            $img = !empty($r['image']) ? (string)$r['image'] : '';
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
            $ratingVal = number_format((float)($r['rating'] ?? 4.5), 1, '.', '');
            ?>
            <article class="restaurant-card" data-cuisine="<?= e((string)($r['cuisine_type'] ?? '')) ?>" data-rating="<?= $ratingVal ?>" data-hours-start="<?= e((string)$r['work_hours_start']) ?>" data-hours-end="<?= e((string)$r['work_hours_end']) ?>">
                <div class="restaurant-card-media">
                    <?php if ($imgSrc !== ''): ?>
                        <img class="restaurant-card-img" src="<?= e($imgSrc) ?>" alt="<?= e((string)$r['name']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="restaurant-card-placeholder" aria-hidden="true">
                            <span class="placeholder-icon"><svg width="32" height="32" viewBox="0 0 28 32" fill="none"><rect x="2" y="12" width="24" height="4" rx="2" fill="#333"/><rect x="4" y="16" width="4" height="10" rx="2" fill="#333"/><rect x="20" y="16" width="4" height="10" rx="2" fill="#333"/></svg></span>
                        </div>
                    <?php endif; ?>
                    <div class="card-rating-badge"><?= $ratingVal ?></div>
                    <button class="fav-btn <?= in_array($rid, $favoriteIds, true) ? 'is-fav' : '' ?>" data-id="<?= $rid ?>" data-csrf="<?= e($csrf) ?>" aria-label="Избранное">
                        <svg viewBox="0 0 24 24" width="22" height="22"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                    </button>
                </div>

                <div class="restaurant-card-body">
                    <div class="restaurant-card-top">
                        <div class="restaurant-card-main">
                            <h3 class="restaurant-card-title"><?= e((string)$r['name']) ?></h3>
                            <div class="card-meta">
                                <span class="cuisine-badge"><?= e((string)($r['cuisine_type'] ?? '')) ?></span>
                                <span class="city-badge"><?= e((string)($r['city'] ?? '')) ?></span>
                            </div>
                            <div class="restaurant-card-address"><?= e((string)($r['address'] ?? '')) ?></div>
                            <div class="restaurant-card-hours">
                                Часы: <?= e((string)$r['work_hours_start']) ?> – <?= e((string)$r['work_hours_end']) ?>
                            </div>
                        </div>
                    </div>

                    <a class="btn btn-primary restaurant-card-book" href="<?= $appBaseUrl ?>/restaurant.php?id=<?= $rid ?>">Забронировать</a>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php
// Рестораны с координатами для карты
$mapRestaurants = array_filter($restaurants, fn($r) => !empty($r['lat']) && !empty($r['lng']));
?>
<?php if (count($mapRestaurants) > 0): ?>
<section class="map-section">
    <h2>Рестораны на карте</h2>
    <div class="map-container" id="map"></div>
</section>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function() {
    var map = L.map('map', {
        scrollWheelZoom: false,
        attributionControl: false
    }).setView([51.7305, 36.1918], 13);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        subdomains: 'abcd',
        maxZoom: 19
    }).addTo(map);

    var restaurants = <?= json_encode(array_map(function($r) use ($appBaseUrl) {
        $img = !empty($r['image']) ? (string)$r['image'] : '';
        return [
            'name' => (string)$r['name'],
            'cuisine' => (string)($r['cuisine_type'] ?? ''),
            'rating' => number_format((float)($r['rating'] ?? 4.5), 1, '.', ''),
            'lat' => (float)$r['lat'],
            'lng' => (float)$r['lng'],
            'url' => $appBaseUrl . '/restaurant.php?id=' . (int)$r['id'],
            'image' => $img,
        ];
    }, array_values($mapRestaurants)), JSON_UNESCAPED_UNICODE) ?>;

    restaurants.forEach(function(r) {
        var markerHtml = r.image
            ? '<img src="' + r.image + '" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">'
            : '<svg width="20" height="20" viewBox="0 0 28 32" fill="none"><rect x="2" y="12" width="24" height="4" rx="2" fill="#555"/><rect x="4" y="16" width="4" height="10" rx="2" fill="#555"/><rect x="20" y="16" width="4" height="10" rx="2" fill="#555"/></svg>';
        var icon = L.divIcon({
            className: 'map-marker-avatar',
            html: markerHtml,
            iconSize: [44, 44],
            iconAnchor: [22, 22]
        });
        var popup = '<div style="font-family:Inter,sans-serif;padding:4px;">' +
                    '<strong style="color:#fff;font-size:15px;">' + r.name + '</strong><br>' +
                    '<span style="color:#8A8680;font-size:13px;">' + r.cuisine + '</span>' +
                    ' <span style="color:#D4A017;font-size:13px;">★ ' + r.rating + '</span><br>' +
                    '<a href="' + r.url + '" style="color:#D4A017;font-weight:600;font-size:13px;">Забронировать →</a>' +
                    '</div>';
        L.marker([r.lat, r.lng], {icon: icon}).addTo(map).bindPopup(popup);
    });
})();
</script>
<?php endif; ?>

<section class="placement-cta">
    <div class="cta-watermark" aria-hidden="true">500+</div>
    <div class="placement-cta-inner">
        <div class="cta-content">
            <h3>Владелец ресторана?</h3>
            <p>Разместите своё заведение бесплатно. Получайте онлайн-бронирования уже сегодня.</p>
            <div class="cta-benefits">
                <span class="cta-benefit-item"><span class="cta-check">✓</span> Бесплатное размещение</span>
                <span class="cta-benefit-item"><span class="cta-check">✓</span> Онлайн-бронирования 24/7</span>
                <span class="cta-benefit-item"><span class="cta-check">✓</span> Управление столиками</span>
                <span class="cta-benefit-item"><span class="cta-check">✓</span> Статистика и аналитика</span>
            </div>
        </div>
        <a class="btn-outline-white cta-main-btn" href="<?= $appBaseUrl ?>/placement.php">Разместить ресторан</a>
    </div>
</section>

<script>
(function() {
    var carousel = document.getElementById('banner-carousel');
    if (!carousel) return;
    var slides = carousel.querySelectorAll('.banner-slide');
    var dots = carousel.querySelectorAll('.banner-dot');
    if (slides.length <= 1) return;
    var current = 0;
    function goTo(idx) {
        slides[current].classList.remove('active');
        if (dots[current]) dots[current].classList.remove('active');
        current = idx % slides.length;
        slides[current].classList.add('active');
        if (dots[current]) dots[current].classList.add('active');
    }
    dots.forEach(function(dot) {
        dot.addEventListener('click', function() {
            goTo(parseInt(this.getAttribute('data-index'), 10));
        });
    });
    setInterval(function() { goTo(current + 1); }, 5000);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
