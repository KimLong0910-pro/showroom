<?php
require_once 'db.php';
header("Content-Type: application/json; charset=UTF-8");

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!$input && isset($_POST['message'])) {
    $input = $_POST;
}

function toLowerUtf8($str) {
    return function_exists('mb_strtolower') ? mb_strtolower($str, 'UTF-8') : strtolower($str);
}

function strPosUtf8($haystack, $needle) {
    return function_exists('mb_strpos') ? mb_strpos($haystack, $needle) : strpos($haystack, $needle);
}

function removeAccents($str) {
    $str = preg_replace("/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/u", 'a', $str);
    $str = preg_replace("/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/u", 'e', $str);
    $str = preg_replace("/(ì|í|ị|ỉ|ĩ)/u", 'i', $str);
    $str = preg_replace("/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/u", 'o', $str);
    $str = preg_replace("/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/u", 'u', $str);
    $str = preg_replace("/(ỳ|ý|ỵ|ỷ|ỹ)/u", 'y', $str);
    $str = preg_replace("/(đ)/u", 'd', $str);
    return $str;
}

$message = toLowerUtf8(trim($input['message'] ?? ''));

if (empty($message)) {
    echo json_encode([
        "reply" => "Chào quý khách! Em là Trợ lý AI Showroom 4TL. Em có thể tư vấn mẫu xe, mức giá, chương trình trả góp hay đăng ký lái thử cho quý khách."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$msgNoAccent = removeAccents($message);
$reply = "";

// ƯU TIÊN 1: TRA CỨU TỪ BẢNG TRI THỨC HUẤN LUYỆN (bot_knowledge)
if (isset($pdo) && $pdo) {
    try {
        $stmt = $pdo->query("SELECT keywords, response FROM bot_knowledge ORDER BY id DESC");
        $knowledges = $stmt->fetchAll();

        $bestScore = 0;
        $bestResponse = "";

        foreach ($knowledges as $row) {
            $kwList = explode(',', $row['keywords']);
            $score = 0;
            foreach ($kwList as $kw) {
                $kwClean = toLowerUtf8(trim($kw));
                $kwNoAccent = removeAccents($kwClean);
                if (empty($kwClean)) continue;

                if (strPosUtf8($message, $kwClean) !== false || strpos($msgNoAccent, $kwNoAccent) !== false) {
                    $score += 3;
                } else {
                    $msgWords = explode(' ', $msgNoAccent);
                    foreach ($msgWords as $w) {
                        if (strlen($w) >= 4 && strlen($kwNoAccent) >= 4) {
                            similar_text($w, $kwNoAccent, $percent);
                            if ($percent >= 82) {
                                $score += 1;
                            }
                        }
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestResponse = $row['response'];
            }
        }

        if ($bestScore >= 3) {
            $reply = nl2br(htmlspecialchars($bestResponse, ENT_QUOTES, 'UTF-8'));
        }
    } catch (Exception $e) {
        // Bỏ qua nếu bảng chưa tồn tại
    }
}

// ƯU TIÊN 2: LẤY DANH SÁCH XE TỪ PDO (CÓ DỮ LIỆU DỰ PHÒNG NẾU LỖI DB)
$cars = [];
if (isset($pdo) && $pdo) {
    try {
        $cars = $pdo->query("SELECT * FROM cars ORDER BY id DESC")->fetchAll();
    } catch (Exception $e) {
        $cars = [];
    }
}

if (empty($cars)) {
    $cars = [
        ['name' => 'VinFast VF3', 'brand' => 'VinFast', 'price' => 240000000, 'engine' => 'Động cơ điện đơn (43 HP)', 'fuel' => 'Điện'],
        ['name' => 'VinFast VF5 Plus', 'brand' => 'VinFast', 'price' => 468000000, 'engine' => 'Động cơ điện (134 HP)', 'fuel' => 'Điện'],
        ['name' => 'VinFast VF8 Plus', 'brand' => 'VinFast', 'price' => 1270000000, 'engine' => '2 Động cơ điện (402 HP)', 'fuel' => 'Điện'],
        ['name' => 'Mercedes-Benz C-Class C300 AMG', 'brand' => 'Mercedes-Benz', 'price' => 2099000000, 'engine' => '2.0L Turbo EQ Boost (258 HP)', 'fuel' => 'Xăng Mild-Hybrid'],
        ['name' => 'Porsche 911 Carrera S', 'brand' => 'Porsche', 'price' => 8500000000, 'engine' => '3.0L Boxer Twin-Turbo (450 HP)', 'fuel' => 'Xăng'],
        ['name' => 'Mercedes-AMG G63', 'brand' => 'Mercedes-Benz', 'price' => 11750000000, 'engine' => '4.0L V8 Bi-Turbo (585 HP)', 'fuel' => 'Xăng'],
        ['name' => 'Lamborghini Urus S', 'brand' => 'Lamborghini', 'price' => 16500000000, 'engine' => '4.0L V8 Twin-Turbo (666 HP)', 'fuel' => 'Xăng'],
        ['name' => 'Ferrari F8 Tributo', 'brand' => 'Ferrari', 'price' => 19000000000, 'engine' => '3.9L V8 Twin-Turbo (710 HP)', 'fuel' => 'Xăng'],
    ];
}

if (!$reply && !empty($cars)) {
    foreach ($cars as $car) {
        $carNameLower = toLowerUtf8($car['name']);
        $carNameNoAccent = removeAccents($carNameLower);
        $words = explode(' ', $carNameNoAccent);
        $lastWord = end($words);

        if (strPosUtf8($message, $carNameLower) !== false ||
            strpos($msgNoAccent, $carNameNoAccent) !== false ||
            (strlen($lastWord) >= 3 && strpos($msgNoAccent, $lastWord) !== false) ||
            ($car['name'] === 'VinFast VF3' && (strpos($msgNoAccent, 'vf3') !== false || strpos($msgNoAccent, 'vf 3') !== false)) ||
            ($car['name'] === 'Mercedes-AMG G63' && (strpos($msgNoAccent, 'g63') !== false || strpos($msgNoAccent, 'g 63') !== false))
        ) {
            $priceFormatted = number_format((float)$car['price'], 0, ',', '.');
            $reply = "Dạ, mẫu xe <strong>{$car['name']}</strong> ({$car['brand']}) hiện có giá niêm yết là <strong>{$priceFormatted} VNĐ</strong>.<br>✨ Động cơ: {$car['engine']} | Nhiên liệu: {$car['fuel']}.<br>Quý khách có thể bấm <strong>Đặt Cọc</strong> hoặc <strong>Lái Thử</strong> ngay bên dưới thẻ xe ạ!";
            break;
        }
    }
}

// ƯU TIÊN 3: TƯ VẤN THEO NGÂN SÁCH & CÂU HỎI MẶC ĐỊNH
if (!$reply && (strpos($msgNoAccent, 'duoi') !== false || strpos($msgNoAccent, 'tam') !== false || strpos($msgNoAccent, 'ngan sach') !== false)) {
    $limitPrice = 0;
    $label = "";
    if (strpos($msgNoAccent, '500') !== false) { $limitPrice = 500000000; $label = "dưới 500 triệu"; }
    elseif (strpos($msgNoAccent, '1 ty') !== false || strpos($msgNoAccent, '1ty') !== false) { $limitPrice = 1000000000; $label = "dưới 1 tỷ"; }
    elseif (strpos($msgNoAccent, '2 ty') !== false || strpos($msgNoAccent, '2ty') !== false) { $limitPrice = 2000000000; $label = "dưới 2 tỷ"; }
    elseif (strpos($msgNoAccent, '3 ty') !== false || strpos($msgNoAccent, '3ty') !== false) { $limitPrice = 3000000000; $label = "dưới 3 tỷ"; }

    if ($limitPrice > 0 && !empty($cars)) {
        $matches = array_filter($cars, fn($c) => (float)$c['price'] <= $limitPrice);
        if (!empty($matches)) {
            usort($matches, fn($a, $b) => (float)$b['price'] - (float)$a['price']);
            $list = [];
            foreach (array_slice($matches, 0, 4) as $m) {
                $p = number_format((float)$m['price'], 0, ',', '.');
                $list[] = "• <strong>{$m['name']}</strong>: {$p} VNĐ";
            }
            $reply = "Dạ với tầm tài chính <strong>{$label}</strong>, Showroom 4TL đề xuất các mẫu xe:<br>" . implode("<br>", $list);
        }
    }
}

if (!$reply) {
    if (strpos($msgNoAccent, 'tra gop') !== false || strpos($msgNoAccent, 'vay') !== false) {
        $reply = "Showroom 4TL hỗ trợ vay trả góp lên đến <strong>80% giá trị xe</strong> trong tối đa 7 năm (84 tháng). Quý khách có thể kéo thử thanh <strong>Dự Toán Trả Góp</strong> ngay trên trang chủ!";
    } elseif (strpos($msgNoAccent, 'lai thu') !== false) {
        $reply = "Quý khách hãy bấm nút <strong>'Lái Thử'</strong> trên bất kỳ mẫu xe nào, điền tên và SĐT, hệ thống sẽ lưu lịch hẹn ngay để chuyên viên mang xe tới tận nơi ạ!";
    } else {
        $reply = "Cảm ơn quý khách đã nhắn tin! Quý khách có thể hỏi về giá xe cụ thể (VD: <em>giá VF3, G63, Porsche 911</em>), ngân sách (<em>xe dưới 2 tỷ</em>) hoặc gọi Hotline VIP <strong>0706.850.014</strong> ạ!";
    }
}

echo json_encode(["reply" => $reply], JSON_UNESCAPED_UNICODE);
?>