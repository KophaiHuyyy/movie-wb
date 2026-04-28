<?php
include_once "checkpermission.php";
include_once "cauhinh.php";

$reviewId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$keyword = isset($_GET["keyword"]) ? trim((string) $_GET["keyword"]) : "";
$rating = isset($_GET["rating"]) ? trim((string) $_GET["rating"]) : "";
$status = isset($_GET["status"]) ? trim((string) $_GET["status"]) : "";
$movieId = isset($_GET["movie_id"]) ? (int) $_GET["movie_id"] : 0;
$page = isset($_GET["page"]) ? max(1, (int) $_GET["page"]) : 1;
$visibility = isset($_GET["visibility"]) ? trim((string) $_GET["visibility"]) : "";

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

if ($reviewId <= 0 || !in_array($visibility, array("an", "hien"), true)) {
    header($redirectBase . "&notice=visibility_failed");
    exit();
}

$targetHidden = $visibility === "an" ? 1 : 0;
$selectStmt = $connect->prepare("SELECT is_hidden FROM reviews WHERE review_id = ? LIMIT 1");

if (!$selectStmt) {
    header($redirectBase . "&notice=visibility_failed");
    exit();
}

$selectStmt->bind_param("i", $reviewId);
$selectStmt->execute();
$selectResult = $selectStmt->get_result();
$currentReview = $selectResult ? $selectResult->fetch_assoc() : null;
$selectStmt->close();

if (!$currentReview) {
    header($redirectBase . "&notice=visibility_failed");
    exit();
}

if ((int) $currentReview["is_hidden"] !== $targetHidden) {
    $updateStmt = $connect->prepare("
        UPDATE reviews
        SET is_hidden = ?
        WHERE review_id = ?
        LIMIT 1
    ");

    if (!$updateStmt) {
        header($redirectBase . "&notice=visibility_failed");
        exit();
    }

    $updateStmt->bind_param("ii", $targetHidden, $reviewId);
    $isUpdated = $updateStmt->execute();
    $updateStmt->close();

    if (!$isUpdated) {
        header($redirectBase . "&notice=visibility_failed");
        exit();
    }
}

header($redirectBase . "&notice=" . ($targetHidden === 1 ? "hidden" : "shown"));
exit();
