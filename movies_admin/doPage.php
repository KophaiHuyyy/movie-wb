<?php
$do = isset($_GET['page_layout']) ? trim((string) $_GET['page_layout']) : "listfilm";
$target = __DIR__ . DIRECTORY_SEPARATOR . $do . ".php";

if (is_file($target)) {
    include $target;
} else {
    http_response_code(404);
    echo "Trang quản trị không tồn tại.";
}
?>
