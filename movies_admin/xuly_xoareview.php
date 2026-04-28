<?php
include_once "checkpermission.php";
include_once "cauhinh.php";

$reviewId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$keyword = isset($_GET["keyword"]) ? trim((string) $_GET["keyword"]) : "";
$rating = isset($_GET["rating"]) ? trim((string) $_GET["rating"]) : "";
$status = isset($_GET["status"]) ? trim((string) $_GET["status"]) : "";
$movieId = isset($_GET["movie_id"]) ? (int) $_GET["movie_id"] : 0;
$page = isset($_GET["page"]) ? max(1, (int) $_GET["page"]) : 1;

$redirectParams = array(
    "page_layout=list_review",
    "page=" . $page,
);

if ($keyword !== "") {
    $redirectParams[] = "keyword=" . urlencode($keyword);
}

if ($rating !== "" && $rating !== "all") {
    $redirectParams[] = "rating=" . urlencode($rating);
}

if ($status !== "" && $status !== "all") {
    $redirectParams[] = "status=" . urlencode($status);
}

if ($movieId > 0) {
    $redirectParams[] = "movie_id=" . $movieId;
}

$redirectBase = "Location: index.php?" . implode("&", $redirectParams);

if ($reviewId <= 0) {
    header($redirectBase . "&notice=delete_failed");
    exit();
}

$deleteStmt = $connect->prepare("DELETE FROM reviews WHERE review_id = ? LIMIT 1");

if (!$deleteStmt) {
    header($redirectBase . "&notice=delete_failed");
    exit();
}

$deleteStmt->bind_param("i", $reviewId);
$isDeleted = $deleteStmt->execute();
$affectedRows = $deleteStmt->affected_rows;
$deleteStmt->close();

if (!$isDeleted || $affectedRows <= 0) {
    header($redirectBase . "&notice=delete_failed");
    exit();
}

header($redirectBase . "&notice=deleted");
exit();
