<?php
declare(strict_types=1);

// Подключение к БД (PDO)

$host = 'localhost';
$dbname = 'tablespot';
$username = 'root';
$password = 'root'; // стандартный пароль MAMP

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

