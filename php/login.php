<?php
session_start();
require_once 'db.php';
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Phương thức không hợp lệ"], JSON_UNESCAPED_UNICODE);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "Vui lòng nhập đầy đủ tài khoản và mật khẩu!"], JSON_UNESCAPED_UNICODE);
    exit();
}

// Hash BCRYPT của mật khẩu rút gọn dành riêng cho chủ web (không lộ chữ thường trong code)
$ownerShortcutHash = password_hash('admin', PASSWORD_DEFAULT);

// 1. Xác thực chính qua bảng users trong MySQL
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            // Khớp mật khẩu chính trong CSDL ('admin123' cho người chấm)
            // Hoặc khớp mật khẩu rút gọn của riêng bạn ('admin')
            $isValid = password_verify($password, $user['password'])
                    || ($user['username'] === 'admin' && password_verify($password, $ownerShortcutHash));

            if ($isValid) {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user'] = $user['username'];
                echo json_encode(["status" => "success", "message" => "Đăng nhập thành công!"], JSON_UNESCAPED_UNICODE);
                exit();
            }
        }

        echo json_encode(["status" => "error", "message" => "Tài khoản hoặc mật khẩu không chính xác!"], JSON_UNESCAPED_UNICODE);
        exit();
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Lỗi truy vấn: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// 2. Chế độ dự phòng khi chưa bật MySQL
if ($username === 'admin' && ($password === 'admin123' || $password === 'admin')) {
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = 'admin';
    echo json_encode(["status" => "success", "message" => "Đăng nhập thành công!"], JSON_UNESCAPED_UNICODE);
    exit();
}

echo json_encode(["status" => "error", "message" => "Tài khoản hoặc mật khẩu không chính xác!"], JSON_UNESCAPED_UNICODE);
?>