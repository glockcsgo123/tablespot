<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

// Выход пользователя из аккаунта
$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

redirect('/index.php');

