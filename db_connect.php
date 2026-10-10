<?php
/**
 * Подключение к базе данных MySQL (OpenServer)
 * Используется PDO с подготовленными выражениями
 */

$db_host = '127.0.0.1';
$db_port = '3306';
$db_name = 'tour';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO(
        "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,           // Выбрасывать исключения при ошибках
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,      // Возвращать ассоциативные массивы
            PDO::ATTR_EMULATE_PREPARES => false,                    // Настоящие подготовленные выражения
        ]
    );
} catch (PDOException $e) {
    // Выводим ошибку в JSON-формате, если это AJAX-запрос
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Ошибка подключения к БД: ' . $e->getMessage()]);
        exit;
    }
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}

// Хелперы для безопасного доступа к переменным (совместимость с PHP 5.4+)
if (!function_exists('req_val')) {
    function req_val($key, $default = '') {
        if (isset($_POST[$key])) return $_POST[$key];
        if (isset($_GET[$key])) return $_GET[$key];
        return $default;
    }
}
if (!function_exists('post_val')) {
    function post_val($key, $default = '') {
        return isset($_POST[$key]) ? $_POST[$key] : $default;
    }
}
if (!function_exists('get_val')) {
    function get_val($key, $default = '') {
        return isset($_GET[$key]) ? $_GET[$key] : $default;
    }
}
if (!function_exists('arr_val')) {
    function arr_val($arr, $key, $default = '') {
        return (is_array($arr) && isset($arr[$key])) ? $arr[$key] : $default;
    }
}
