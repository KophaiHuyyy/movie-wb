<?php
include_once "checkpermission.php";
include_once "cauhinh.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($id <= 0) {
    $_SESSION["admin_genre_notice"] = array(
        "type" => "error",
        "message" => "Không xác định được thể loại cần xóa."
    );
    header("Location: index.php?page_layout=list_theloai");
    exit();
}

$mappingStatement = $connect->prepare("SELECT COUNT(*) AS total FROM movie_genre WHERE theloai_id = ?");
$mappingTotal = 0;
$mappingCheckOk = false;

if ($mappingStatement && $mappingStatement->bind_param("i", $id) && $mappingStatement->execute()) {
    $mappingCheckOk = true;
    $result = $mappingStatement->get_result();
    if ($result) {
        $row = $result->fetch_assoc();
        $mappingTotal = isset($row["total"]) ? (int) $row["total"] : 0;
        $result->free();
    }
    $mappingStatement->close();
}

if (!$mappingCheckOk) {
    $_SESSION["admin_genre_notice"] = array(
        "type" => "error",
        "message" => "Không thể xác minh mapping của thể loại. Vui lòng thử lại."
    );
    $connect->close();
    header("Location: index.php?page_layout=list_theloai");
    exit();
}

if ($mappingTotal > 0) {
    $_SESSION["admin_genre_notice"] = array(
        "type" => "error",
        "message" => "Không thể xóa thể loại đang được gắn với phim. Hãy gỡ mapping trước."
    );
    $connect->close();
    header("Location: index.php?page_layout=list_theloai");
    exit();
}

$deleteStatement = $connect->prepare("DELETE FROM genres WHERE theloai_id = ?");

if ($deleteStatement && $deleteStatement->bind_param("i", $id) && $deleteStatement->execute()) {
    $_SESSION["admin_genre_notice"] = array(
        "type" => "success",
        "message" => "Đã xóa thể loại thành công."
    );
    $deleteStatement->close();
    $connect->close();
    header("Location: index.php?page_layout=list_theloai");
    exit();
}

if ($deleteStatement) {
    $deleteStatement->close();
}

$_SESSION["admin_genre_notice"] = array(
    "type" => "error",
    "message" => "Không thể xóa thể loại ở thời điểm hiện tại."
);

$connect->close();
header("Location: index.php?page_layout=list_theloai");
exit();
?>
