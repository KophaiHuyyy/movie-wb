<?php
include_once "checkpermission.php";
include_once "cauhinh.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenTheLoai = isset($_POST["txtTenQuocGia"]) ? trim((string) $_POST["txtTenQuocGia"]) : "";

if (!isset($_POST["btnThem"])) {
    header("Location: index.php?page_layout=list_theloai");
    exit();
}

if ($tenTheLoai === "") {
    $_SESSION["admin_genre_notice"] = array(
        "type" => "error",
        "message" => "Vui lòng nhập tên thể loại trước khi lưu."
    );
    header("Location: index.php?page_layout=list_theloai&drawer=add");
    exit();
}

$statement = $connect->prepare("INSERT INTO genres (ten_theloai) VALUES (?)");

if ($statement && $statement->bind_param("s", $tenTheLoai) && $statement->execute()) {
    $_SESSION["admin_genre_notice"] = array(
        "type" => "success",
        "message" => "Đã thêm thể loại vào cơ sở dữ liệu."
    );
    $statement->close();
    $connect->close();
    header("Location: index.php?page_layout=list_theloai");
    exit();
}

if ($statement) {
    $statement->close();
}

$_SESSION["admin_genre_notice"] = array(
    "type" => "error",
    "message" => "Không thể thêm thể loại ở thời điểm hiện tại."
);

$connect->close();
header("Location: index.php?page_layout=list_theloai&drawer=add");
exit();
?>
