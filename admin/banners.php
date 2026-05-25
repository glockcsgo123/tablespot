<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$page_title = 'Управление баннерами';
require_admin();

$error = '';
$success = '';
$csrf = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Ошибка CSRF-токена.';
    } else {
        $action = isset($_POST['action']) ? (string)$_POST['action'] : '';

        if ($action === 'add') {
            $title      = isset($_POST['title']) ? trim((string)$_POST['title']) : '';
            $subtitle   = isset($_POST['subtitle']) ? trim((string)$_POST['subtitle']) : '';
            $buttonText = isset($_POST['button_text']) ? trim((string)$_POST['button_text']) : '';
            $buttonUrl  = isset($_POST['button_url']) ? trim((string)$_POST['button_url']) : '';
            $bgColor    = isset($_POST['bg_color']) ? trim((string)$_POST['bg_color']) : '#2D6A4F';
            $sortOrder  = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

            if ($title === '') {
                $error = 'Заголовок обязателен.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO banners (title, subtitle, button_text, button_url, bg_color, sort_order) VALUES (:t, :s, :bt, :bu, :bg, :so)");
                $stmt->execute([
                    ':t'  => $title,
                    ':s'  => $subtitle,
                    ':bt' => $buttonText,
                    ':bu' => $buttonUrl,
                    ':bg' => $bgColor,
                    ':so' => $sortOrder,
                ]);
                $success = 'Баннер добавлен.';
            }

        } elseif ($action === 'toggle') {
            $bannerId = isset($_POST['banner_id']) ? (int)$_POST['banner_id'] : 0;
            if ($bannerId > 0) {
                $stmt = $pdo->prepare("UPDATE banners SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id");
                $stmt->execute([':id' => $bannerId]);
                $success = 'Статус баннера изменён.';
            }

        } elseif ($action === 'delete') {
            $bannerId = isset($_POST['banner_id']) ? (int)$_POST['banner_id'] : 0;
            if ($bannerId > 0) {
                $stmt = $pdo->prepare("DELETE FROM banners WHERE id = :id");
                $stmt->execute([':id' => $bannerId]);
                $success = 'Баннер удалён.';
            }
        }
    }
}

$banners = $pdo->query("SELECT * FROM banners ORDER BY sort_order ASC, id DESC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="admin-tables">
    <h1>Управление баннерами</h1>

    <div class="admin-links">
        <a class="btn secondary" href="<?= $appBaseUrl ?>/admin/index.php">Назад</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="form-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="form-success" role="status"><?= e($success) ?></div>
    <?php endif; ?>

    <div class="admin-card">
        <h2>Добавить баннер</h2>
        <form method="post" class="add-table-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="add">

            <div class="form-row">
                <label for="title">Заголовок *</label>
                <input type="text" id="title" name="title" required maxlength="200">
            </div>
            <div class="form-row">
                <label for="subtitle">Подзаголовок</label>
                <input type="text" id="subtitle" name="subtitle" maxlength="300">
            </div>
            <div class="form-row">
                <label for="button_text">Текст кнопки</label>
                <input type="text" id="button_text" name="button_text" maxlength="100">
            </div>
            <div class="form-row">
                <label for="button_url">URL кнопки</label>
                <input type="text" id="button_url" name="button_url" maxlength="500" placeholder="/auth/register.php">
            </div>
            <div class="form-row">
                <label for="bg_color">Цвет фона</label>
                <input type="color" id="bg_color" name="bg_color" value="#2D6A4F">
            </div>
            <div class="form-row">
                <label for="sort_order">Порядок сортировки</label>
                <input type="number" id="sort_order" name="sort_order" value="0" min="0">
            </div>

            <button class="btn" type="submit">Добавить</button>
        </form>
    </div>

    <div class="admin-card">
        <h2>Текущие баннеры</h2>
        <?php if (count($banners) === 0): ?>
            <div class="empty">Баннеров пока нет.</div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Заголовок</th>
                            <th>Цвет</th>
                            <th>Порядок</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($banners as $b): ?>
                        <tr>
                            <td><?= (int)$b['id'] ?></td>
                            <td><?= e((string)$b['title']) ?></td>
                            <td><span style="display:inline-block;width:24px;height:24px;border-radius:4px;background:<?= e((string)$b['bg_color']) ?>;vertical-align:middle;"></span></td>
                            <td><?= (int)$b['sort_order'] ?></td>
                            <td><?= (int)$b['is_active'] ? '<span class="badge confirmed">Вкл</span>' : '<span class="badge cancelled">Выкл</span>' ?></td>
                            <td>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="banner_id" value="<?= (int)$b['id'] ?>">
                                    <button class="btn" type="submit"><?= (int)$b['is_active'] ? 'Выкл' : 'Вкл' ?></button>
                                </form>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="banner_id" value="<?= (int)$b['id'] ?>">
                                    <button class="btn danger" type="submit" onclick="return confirm('Удалить баннер?');">Удалить</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
