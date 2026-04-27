<?php
include_once "checkpermission.php";
include_once "cauhinh.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$tenTheLoai = isset($_POST["txthoten"]) ? trim((string) $_POST["txthoten"]) : "";

if (isset($_POST["btnhuy"])) {
    header("Location: index.php?page_layout=list_theloai");
    exit();
}

if ($id <= 0) {
    $_SESSION["admin_genre_notice"] = array(
        "type" => "error",
        "message" => "Không xác định được thể loại cần cập nhật."
    );
    header("Location: index.php?page_layout=list_theloai");
    exit();
}

if ($tenTheLoai === "") {
    $_SESSION["admin_genre_notice"] = array(
        "type" => "error",
        "message" => "Vui lòng nhập tên thể loại trước khi cập nhật."
    );
    header("Location: index.php?page_layout=list_theloai&drawer=edit&id=" . $id);
    exit();
}

$statement = $connect->prepare("UPDATE genres SET ten_theloai = ? WHERE theloai_id = ?");

if ($statement && $statement->bind_param("si", $tenTheLoai, $id) && $statement->execute()) {
    $_SESSION["admin_genre_notice"] = array(
        "type" => "success",
        "message" => "Đã cập nhật thể loại thành công."
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
    "message" => "Không thể cập nhật thể loại ở thời điểm hiện tại."
);

$connect->close();
header("Location: index.php?page_layout=list_theloai&drawer=edit&id=" . $id);
exit();
?>
