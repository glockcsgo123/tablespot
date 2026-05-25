<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Избранное';
require_login();

$userId = current_user_id();
$csrf = csrf_token();

$stmt = $pdo->prepare("
    SELECT r.id, r.name, r.address, r.cuisine_type, r.image, r.city, r.rating, r.work_hours_start, r.work_hours_end
    FROM favorites f
    JOIN restaurants r ON r.id = f.restaurant_id
    WHERE f.user_id = :uid AND r.is_active = 1
    ORDER BY f.created_at DESC
");
$stmt->execute([':uid' => $userId]);
$restaurants = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<section class="profile-page" style="margin-top:24px;">
    <h1>Избранное</h1>

    <?php if (count($restaurants) === 0): ?>
        <div class="empty">У вас пока нет избранных ресторанов.</div>
    <?php else: ?>
        <div class="restaurants-grid">
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
                <article class="restaurant-card">
                    <div class="restaurant-card-media">
                        <?php if ($imgSrc !== ''): ?>
                            <img class="restaurant-card-img" src="<?= e($imgSrc) ?>" alt="<?= e((string)$r['name']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="restaurant-card-placeholder" aria-hidden="true">
                                <span class="placeholder-icon"><svg width="32" height="32" viewBox="0 0 28 32" fill="none"><rect x="2" y="12" width="24" height="4" rx="2" fill="#333"/><rect x="4" y="16" width="4" height="10" rx="2" fill="#333"/><rect x="20" y="16" width="4" height="10" rx="2" fill="#333"/></svg></span>
                            </div>
                        <?php endif; ?>
                        <div class="card-rating-badge"><?= $ratingVal ?></div>
                        <button class="fav-btn is-fav" data-id="<?= $rid ?>" data-csrf="<?= e($csrf) ?>" aria-label="Убрать из избранного">
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
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
