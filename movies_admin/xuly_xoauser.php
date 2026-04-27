<?php
include_once "checkpermission.php";
include_once "cauhinh.php";

$userId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($userId <= 0) {
    header("Location: index.php?page_layout=list_user");
    exit();
}

$deleteReviews = $connect->prepare("DELETE FROM reviews WHERE user_id = ?");
$deleteWatchlist = $connect->prepare("DELETE FROM watchlist WHERE user_id = ?");
$deleteUser = $connect->prepare("DELETE FROM users WHERE user_id = ?");

$ok = false;

if (
    $deleteReviews && $deleteReviews->bind_param("i", $userId) && $deleteReviews->execute() &&
    $deleteWatchlist && $deleteWatchlist->bind_param("i", $userId) && $deleteWatchlist->execute() &&
    $deleteUser && $deleteUser->bind_param("i", $userId) && $deleteUser->execute()
) {
    $ok = true;
}

if ($deleteReviews) {
    $deleteReviews->close();
}
if ($deleteWatchlist) {
    $deleteWatchlist->close();
}
if ($deleteUser) {
    $deleteUser->close();
}

$connect->close();

if ($ok) {
    header("Location: index.php?page_layout=list_user&notice=deleted");
    exit();
}
?>
<script>
    window.location.href = 'index.php?page_layout=list_user';
    alert('Không thể xóa người dùng. Vui lòng thử lại.');
</script>
