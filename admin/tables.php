<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$page_title = 'Управление столиками';
require_admin();
$rid = current_admin_restaurant_id();

$error = '';
$success = '';
$csrf = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
    $csrfOk = validate_csrf($_POST['csrf_token'] ?? null);

    if (!$csrfOk) {
        $error = 'Ошибка CSRF-токена.';
    } elseif ($action === 'add') {
        $tableNumber = isset($_POST['table_number']) ? (int)$_POST['table_number'] : 0;
        $capacity = isset($_POST['capacity']) ? (int)$_POST['capacity'] : 0;

        if ($tableNumber <= 0 || $tableNumber > 999) {
            $error = 'Некорректный номер столика.';
        } elseif ($capacity <= 0 || $capacity > 50) {
            $error = 'Некорректная вместимость.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO tables (restaurant_id, table_number, capacity) VALUES (:rid, :tn, :cap)");
                $stmt->execute([':rid' => $rid, ':tn' => $tableNumber, ':cap' => $capacity]);
                $success = 'Столик добавлен.';
            } catch (Throwable $e) {
                $error = 'Не удалось добавить столик (возможно, номер занят).';
            }
        }
    } elseif ($action === 'update') {
        $tableId = isset($_POST['table_id']) ? (int)$_POST['table_id'] : 0;
        $capacity = isset($_POST['capacity']) ? (int)$_POST['capacity'] : 0;
        $tableNumber = isset($_POST['table_number']) ? (int)$_POST['table_number'] : 0;

        if ($tableId <= 0 || $tableNumber <= 0 || $capacity <= 0) {
            $error = 'Некорректные параметры.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE tables
                    SET table_number = :tn, capacity = :cap
                    WHERE id = :id AND restaurant_id = :rid
                ");
                $stmt->execute([':tn' => $tableNumber, ':cap' => $capacity, ':id' => $tableId, ':rid' => $rid]);
                if ($stmt->rowCount() <= 0) {
                    $error = 'Столик не найден или нет изменений.';
                } else {
                    $success = 'Столик обновлён.';
                }
            } catch (Throwable $e) {
                $error = 'Не удалось сохранить изменения (возможен конфликт номера).';
            }
        }
    } elseif ($action === 'delete') {
        $tableId = isset($_POST['table_id']) ? (int)$_POST['table_id'] : 0;
        if ($tableId <= 0) {
            $error = 'Некорректный table_id.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM tables WHERE id = :id AND restaurant_id = :rid");
            $stmt->execute([':id' => $tableId, ':rid' => $rid]);
            if ($stmt->rowCount() <= 0) {
                $error = 'Не удалось удалить столик.';
            } else {
                $success = 'Столик удалён.';
            }
        }
    } else {
        $error = 'Некорректное действие.';
    }
}

$stmt = $pdo->prepare("SELECT id, table_number, capacity FROM tables WHERE restaurant_id = :rid ORDER BY table_number ASC");
$stmt->execute([':rid' => $rid]);
$tables = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="admin-tables">
    <h1>Управление столиками</h1>

    <div class="admin-links">
        <a class="btn" href="<?= $appBaseUrl ?>/admin/bookings.php?period=today">Бронирования</a>
        <a class="btn secondary" href="<?= $appBaseUrl ?>/admin/index.php">Назад</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="form-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="form-success" role="status"><?= e($success) ?></div>
    <?php endif; ?>

    <div class="admin-card">
        <h2>Добавить столик</h2>
        <form method="post" class="add-table-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="add">

            <div class="form-row">
                <label for="table_number">Номер столика</label>
                <input type="number" id="table_number" name="table_number" required min="1" max="999">
            </div>

            <div class="form-row">
                <label for="capacity">Вместимость</label>
                <input type="number" id="capacity" name="capacity" required min="1" max="50">
            </div>

            <button class="btn" type="submit">Добавить</button>
        </form>
    </div>

    <div class="admin-card">
        <h2>Текущие столики</h2>

        <?php if (count($tables) === 0): ?>
            <div class="empty">Столиков пока нет.</div>
        <?php else: ?>
            <div class="tables-admin-list">
                <?php foreach ($tables as $t): ?>
                    <?php $tid = (int)$t['id']; ?>
                    <div class="tables-admin-item">
                        <div class="tables-admin-item-main">
                            <div class="muted">ID: <?= $tid ?></div>
                            <form method="post" class="inline-form" style="margin-top:10px;">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="table_id" value="<?= $tid ?>">

                                <div class="form-row">
                                    <label>Номер</label>
                                    <input type="number" name="table_number" value="<?= (int)$t['table_number'] ?>" required min="1" max="999">
                                </div>
                                <div class="form-row">
                                    <label>Вместимость</label>
                                    <input type="number" name="capacity" value="<?= (int)$t['capacity'] ?>" required min="1" max="50">
                                </div>

                                <button class="btn" type="submit">Сохранить</button>
                            </form>
                        </div>
                        <div class="tables-admin-item-actions">
                            <form method="post" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="table_id" value="<?= $tid ?>">
                                <button class="btn danger" type="submit" onclick="return confirm('Удалить столик?');">Удалить</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

