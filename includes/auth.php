<?php
declare(strict_types=1);

// Хелперы авторизации и проверки доступа

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function redirect(string $url): void
{
    // Если редирект задан абсолютным путём (начинается с '/'),
    // префиксируем базовый URL приложения (работает в подпапке /tablespot).
    $appBaseUrl = '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptName = is_string($scriptName) ? $scriptName : '';
    $scriptDir = rtrim(str_replace(basename($scriptName), '', $scriptName), '/');
    $dirName = $scriptDir !== '' ? basename($scriptDir) : '';
    $appBaseUrl = $scriptDir;
    if (in_array($dirName, ['admin', 'api', 'auth', 'includes'], true)) {
        $appBaseUrl = dirname($scriptDir);
    }
    if ($appBaseUrl === '.') {
        $appBaseUrl = '';
    }

    if ($url !== '' && str_starts_with($url, '/')) {
        $base = rtrim($appBaseUrl, '/');
        $url = $base !== '' ? $base . $url : $url;
    }

    header("Location: {$url}");
    exit;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']) && is_numeric((string)$_SESSION['user_id']);
}

function current_user_id(): ?int
{
    if (!is_logged_in()) {
        return null;
    }
    return (int)$_SESSION['user_id'];
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('/auth/login.php');
    }
}

function is_admin_logged_in(): bool
{
    return isset($_SESSION['admin_id'], $_SESSION['admin_restaurant_id'])
        && is_numeric((string)$_SESSION['admin_id'])
        && is_numeric((string)$_SESSION['admin_restaurant_id']);
}

function current_admin_restaurant_id(): ?int
{
    if (!is_admin_logged_in()) {
        return null;
    }
    return (int)$_SESSION['admin_restaurant_id'];
}

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        redirect('/admin/login.php');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function validate_csrf(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals((string)$_SESSION['csrf_token'], $token);
}

function e(string $value): string
{
    // Экранирование для HTML
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

