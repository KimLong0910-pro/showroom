<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAdminPage() {
    if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit();
    }
}

function requireAdminApi() {
    if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(401);
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode([
            "status" => "unauthorized",
            "message" => "Từ chối truy cập! Bạn chưa đăng nhập quyền Admin."
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}
?>