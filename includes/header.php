<?php
declare(strict_types=1);

// Базовый URL приложения (например, "/tablespot"), работает и в подпапке.
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptName = is_string($scriptName) ? $scriptName : '';
$scriptDir = rtrim(str_replace(basename((string)$scriptName), '', (string)$scriptName), '/');
$dirName = $scriptDir !== '' ? basename($scriptDir) : '';

$appBaseUrl = $scriptDir;
if (in_array($dirName, ['admin', 'api', 'auth', 'includes'], true)) {
    $appBaseUrl = dirname($scriptDir);
}
if ($appBaseUrl === '.' || $appBaseUrl === null) {
    $appBaseUrl = '';
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($page_title) ? e($page_title) : 'TableSpot' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;600&family=DM+Sans:wght@300;400;500&display=swap">
    <link rel="stylesheet" href="<?= $appBaseUrl ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= $appBaseUrl ?>/assets/css/modern.css">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="<?= $appBaseUrl ?>/index.php">
            <svg class="logo-icon" width="28" height="32" viewBox="0 0 28 32" fill="none"><circle cx="14" cy="6" r="5" stroke="#D4A017" stroke-width="2"/><circle cx="14" cy="6" r="2" fill="#D4A017"/><path d="M14 11 L9 7 Q14 -1 19 7 Z" fill="#D4A017"/><rect x="2" y="18" width="24" height="4" rx="2" fill="#D4A017"/><rect x="4" y="22" width="4" height="10" rx="2" fill="#D4A017"/><rect x="20" y="22" width="4" height="10" rx="2" fill="#D4A017"/></svg><span class="logo-word"><span class="logo-text">Table</span><span class="logo-spot">Spot</span></span>
        </a>
        <nav class="nav">
            <a class="nav-link" href="<?= $appBaseUrl ?>/index.php">Рестораны</a>
            <a class="nav-link" href="<?= $appBaseUrl ?>/placement.php">Разместить ресторан</a>

            <?php if (is_logged_in()): ?>
                <a class="nav-link" href="<?= $appBaseUrl ?>/favorites.php">Избранное</a>
                <a class="nav-link" href="<?= $appBaseUrl ?>/profile.php">Кабинет</a>
                <a class="nav-btn" href="<?= $appBaseUrl ?>/auth/logout.php">Выйти</a>
            <?php else: ?>
                <a class="nav-btn" href="<?= $appBaseUrl ?>/auth/login.php">Войти</a>
                <a class="nav-btn nav-btn-secondary" href="<?= $appBaseUrl ?>/auth/register.php">Регистрация</a>
            <?php endif; ?>

            <?php if (is_admin_logged_in()): ?>
                <a class="nav-btn nav-btn-ghost" href="<?= $appBaseUrl ?>/admin/index.php">Админ</a>
            <?php else: ?>
                <a class="nav-btn nav-btn-ghost" href="<?= $appBaseUrl ?>/admin/login.php">Админ</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">

