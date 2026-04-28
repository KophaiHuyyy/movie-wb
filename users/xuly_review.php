<?php
include_once "cauhinh.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function review_build_return_url($pageLayout, $movieId)
{
    $allowedPages = array('xemphim', 'chitietphim');
    $pageLayout = in_array($pageLayout, $allowedPages, true) ? $pageLayout : 'xemphim';

    return "index.php?page_layout=" . $pageLayout . "&id=" . (int) $movieId;
}

function review_redirect($movieId, $pageLayout = 'xemphim')
{
    header("Location: " . review_build_return_url($pageLayout, $movieId));
    exit();
}

function review_set_flash($type, $message)
{
    $_SESSION['review_flash'] = array(
        'type' => $type,
        'message' => $message,
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    review_set_flash('error', 'Yeu cau khong hop le.');
    review_redirect(isset($_POST['movie_id']) ? (int) $_POST['movie_id'] : 0);
}

$movieId = isset($_POST['movie_id']) ? (int) $_POST['movie_id'] : 0;
$rating = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
$comment = isset($_POST['comment']) ? trim((string) $_POST['comment']) : '';
$returnPage = isset($_POST['return_page']) ? trim((string) $_POST['return_page']) : 'xemphim';
$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$isLoggedIn = $currentUserId > 0 && isset($_SESSION['username']) && isset($_SESSION['role']);

if (!$isLoggedIn) {
    $_SESSION['redirect'] = review_build_return_url($returnPage, $movieId);
    review_set_flash('error', 'Vui long dang nhap de gui danh gia.');
    header("Location: login.php");
    exit();
}

if ($movieId <= 0) {
    review_set_flash('error', 'Phim khong hop le.');
    review_redirect(0, $returnPage);
}

if ($rating < 1 || $rating > 5) {
    review_set_flash('error', 'Vui long chon so sao tu 1 den 5.');
    review_redirect($movieId, $returnPage);
}

if ($comment === '') {
    review_set_flash('error', 'Vui long nhap noi dung binh luan.');
    review_redirect($movieId, $returnPage);
}

if (mb_strlen($comment, 'UTF-8') > 1000) {
    review_set_flash('error', 'Noi dung binh luan khong duoc vuot qua 1000 ky tu.');
    review_redirect($movieId, $returnPage);
}

$movieStmt = $connect->prepare("SELECT movie_id FROM movies WHERE movie_id = ? LIMIT 1");
if (!$movieStmt) {
    review_set_flash('error', 'Khong the kiem tra thong tin phim.');
    review_redirect($movieId, $returnPage);
}

$movieStmt->bind_param("i", $movieId);
$movieStmt->execute();
$movieResult = $movieStmt->get_result();
$movieExists = $movieResult && $movieResult->num_rows > 0;
$movieStmt->close();

if (!$movieExists) {
    review_set_flash('error', 'Phim khong ton tai hoac da bi xoa.');
    review_redirect($movieId, $returnPage);
}

$existingReviewId = 0;
$existingStmt = $connect->prepare("
    SELECT review_id
    FROM reviews
    WHERE movie_id = ? AND user_id = ?
    ORDER BY review_id DESC
    LIMIT 1
");

if (!$existingStmt) {
    review_set_flash('error', 'Khong the kiem tra danh gia hien tai.');
    review_redirect($movieId, $returnPage);
}

$existingStmt->bind_param("ii", $movieId, $currentUserId);
$existingStmt->execute();
$existingResult = $existingStmt->get_result();
if ($existingResult && $existingRow = $existingResult->fetch_assoc()) {
    $existingReviewId = (int) $existingRow['review_id'];
}
$existingStmt->close();

if ($existingReviewId > 0) {
    $updateStmt = $connect->prepare("
        UPDATE reviews
        SET rating = ?, comment = ?, review_date = NOW()
        WHERE review_id = ? AND user_id = ?
        LIMIT 1
    ");

    if (!$updateStmt) {
        review_set_flash('error', 'Khong the cap nhat danh gia luc nay.');
        review_redirect($movieId, $returnPage);
    }

    $updateStmt->bind_param("isii", $rating, $comment, $existingReviewId, $currentUserId);
    $isSaved = $updateStmt->execute();
    $updateStmt->close();

    if (!$isSaved) {
        review_set_flash('error', 'Cap nhat danh gia that bai, vui long thu lai.');
        review_redirect($movieId, $returnPage);
    }

    review_set_flash('success', 'Da cap nhat danh gia cua ban.');
    review_redirect($movieId, $returnPage);
}

$insertStmt = $connect->prepare("
    INSERT INTO reviews (movie_id, user_id, rating, comment, review_date, is_hidden)
    VALUES (?, ?, ?, ?, NOW(), 0)
");

if (!$insertStmt) {
    review_set_flash('error', 'Khong the luu danh gia luc nay.');
    review_redirect($movieId, $returnPage);
}

$insertStmt->bind_param("iiis", $movieId, $currentUserId, $rating, $comment);
$isInserted = $insertStmt->execute();
$insertStmt->close();

if (!$isInserted) {
    review_set_flash('error', 'Gui danh gia that bai, vui long thu lai.');
    review_redirect($movieId, $returnPage);
}

review_set_flash('success', 'Da gui danh gia cua ban.');
review_redirect($movieId, $returnPage);
