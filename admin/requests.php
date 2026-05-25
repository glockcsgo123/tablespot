<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$page_title = 'Заявки на размещение';
require_admin();

$error = '';
$success = '';
$csrf = csrf_token();

$validStatuses = ['new', 'reviewed', 'approved', 'rejected'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Ошибка CSRF-токена.';
    } else {
        $requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
        $newStatus = isset($_POST['new_status']) ? (string)$_POST['new_status'] : '';

        if ($requestId <= 0 || !in_array($newStatus, $validStatuses, true)) {
            $error = 'Некорректные параметры.';
        } else {
            $stmt = $pdo->prepare("UPDATE placement_requests SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $newStatus, ':id' => $requestId]);
            $success = 'Статус обновлён.';
        }
    }
}

$requests = $pdo->query("SELECT * FROM placement_requests ORDER BY created_at DESC")->fetchAll();

$statusLabels = [
    'new'      => 'Новая',
    'reviewed' => 'На рассмотрении',
    'approved' => 'Одобрена',
    'rejected' => 'Отклонена',
];

require_once __DIR__ . '/../includes/header.php';
?>

<section class="admin-bookings">
    <h1>Заявки на размещение</h1>

    <div class="admin-links">
        <a class="btn secondary" href="<?= $appBaseUrl ?>/admin/index.php">Назад</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="form-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="form-success" role="status"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if (count($requests) === 0): ?>
        <div class="empty">Заявок пока нет.</div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ресторан</th>
                        <th>Контакт</th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Город</th>
                        <th>Дата</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $r): ?>
                    <?php $curStatus = (string)$r['status']; ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><?= e((string)$r['restaurant_name']) ?></td>
                        <td><?= e((string)$r['contact_name']) ?></td>
                        <td><?= e((string)$r['phone']) ?></td>
                        <td><?= e((string)$r['email']) ?></td>
                        <td><?= e((string)($r['city'] ?? '')) ?></td>
                        <td><?= e((string)$r['created_at']) ?></td>
                        <td>
                            <span class="badge <?= $curStatus === 'approved' ? 'confirmed' : ($curStatus === 'rejected' ? 'cancelled' : 'pending') ?>">
                                <?= e($statusLabels[$curStatus] ?? $curStatus) ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                                <select name="new_status" style="padding:6px;border-radius:8px;border:1px solid var(--border);">
                                    <?php foreach ($validStatuses as $s): ?>
                                        <option value="<?= e($s) ?>" <?= $s === $curStatus ? 'selected' : '' ?>><?= e($statusLabels[$s]) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn" type="submit" style="padding:6px 14px;">Сохранить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
