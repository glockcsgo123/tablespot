<?php
declare(strict_types=1);

// Подключение к БД (PDO)

$host = 'sql301.infinityfree.com';
$dbname = 'if0_42016706_tablespot';
$username = 'if0_42016706';
$password = 'p3lBZH1fb0ZeCc';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // На проде лучше заменить на логирование и нейтральный ответ
    die("Ошибка подключения: " . $e->getMessage());
}

