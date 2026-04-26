<?php
include_once "checkpermission.php";
include_once "cauhinh.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function admin_add_movie_redirect($location)
{
    header("Location: " . $location);
    exit();
}

function admin_add_movie_set_flash($errors = array(), $old = array(), $success = "")
{
    $_SESSION["admin_add_movie_errors"] = $errors;
    $_SESSION["admin_add_movie_old"] = $old;
    $_SESSION["admin_add_movie_success"] = $success;
}

if (isset($_POST["btnhuy"])) {
    admin_add_movie_redirect("index.php?page_layout=listfilm");
}

$tenphim = isset($_POST["txthoten"]) ? trim((string) $_POST["txthoten"]) : "";
$mota = isset($_POST["txtmota"]) ? trim((string) $_POST["txtmota"]) : "";
$namphathanh = isset($_POST["txtnamphathanh"]) ? trim((string) $_POST["txtnamphathanh"]) : "";
$ngonngu = isset($_POST["txtngonngu"]) ? trim((string) $_POST["txtngonngu"]) : "";
$quocgia = isset($_POST["cbb_quocgia"]) ? (int) $_POST["cbb_quocgia"] : 0;
$link1 = isset($_POST["txtlink1"]) ? trim((string) $_POST["txtlink1"]) : "";
$link2 = isset($_POST["txtlink2"]) ? trim((string) $_POST["txtlink2"]) : "";
$selectedGenres = isset($_POST["movie_genres"]) && is_array($_POST["movie_genres"]) ? $_POST["movie_genres"] : array();

$oldInput = array(
    "txthoten" => $tenphim,
    "txtmota" => $mota,
    "txtnamphathanh" => $namphathanh,
    "txtngonngu" => $ngonngu,
    "cbb_quocgia" => $quocgia,
    "txtlink1" => $link1,
    "txtlink2" => $link2,
    "movie_genres" => $selectedGenres,
);

$errors = array();

if ($tenphim === "") {
    $errors[] = "Vui lòng nhập tiêu đề phim.";
}

if ($mota === "") {
    $errors[] = "Vui lòng nhập mô tả nội dung.";
}

if ($namphathanh === "") {
    $errors[] = "Vui lòng nhập năm phát hành.";
}

if ($ngonngu === "") {
    $errors[] = "Vui lòng nhập ngôn ngữ.";
}

if ($quocgia <= 0) {
    $errors[] = "Vui lòng chọn quốc gia.";
}

if ($link1 === "") {
    $errors[] = "Vui lòng nhập server 1 để đảm bảo phim có thể phát.";
}

$currentYear = (int) date("Y");
if ($namphathanh !== "" && (!ctype_digit($namphathanh) || (int) $namphathanh < 1900 || (int) $namphathanh > $currentYear)) {
    $errors[] = "Năm phát hành không hợp lệ.";
}

$genreIds = array();
foreach ($selectedGenres as $genreId) {
    $genreId = (int) $genreId;
    if ($genreId > 0) {
        $genreIds[$genreId] = $genreId;
    }
}
$genreIds = array_values($genreIds);

if (empty($genreIds)) {
    $errors[] = "Vui lòng chọn ít nhất một thể loại để phim xem được ổn định.";
}

$uploadedFile = isset($_FILES["taptin1"]) ? $_FILES["taptin1"] : null;
$posterFilename = "";

if ($uploadedFile === null || !isset($uploadedFile["error"]) || $uploadedFile["error"] === UPLOAD_ERR_NO_FILE) {
    $errors[] = "Vui lòng tải lên ảnh poster.";
} elseif ($uploadedFile["error"] !== UPLOAD_ERR_OK) {
    $errors[] = "Không thể tải ảnh poster lên hệ thống.";
} else {
    $maxFileSize = 5 * 1024 * 1024;
    if ((int) $uploadedFile["size"] > $maxFileSize) {
        $errors[] = "Ảnh poster không được vượt quá 5MB.";
    }

    $extension = strtolower(pathinfo($uploadedFile["name"], PATHINFO_EXTENSION));
    $allowedExtensions = array("jpg", "jpeg", "png", "webp");
    if (!in_array($extension, $allowedExtensions, true)) {
        $errors[] = "Poster chỉ hỗ trợ JPG, JPEG, PNG hoặc WEBP.";
    }
}

if (!empty($errors)) {
    admin_add_movie_set_flash($errors, $oldInput, "");
    admin_add_movie_redirect("index.php?page_layout=themmoi_film");
}

$uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . "hinhanhphim";
if (!is_dir($uploadDirectory)) {
    $errors[] = "Thư mục lưu poster không tồn tại.";
    admin_add_movie_set_flash($errors, $oldInput, "");
    admin_add_movie_redirect("index.php?page_layout=themmoi_film");
}

$posterFilename = "movie-" . date("YmdHis") . "-" . mt_rand(1000, 9999) . "." . strtolower(pathinfo($uploadedFile["name"], PATHINFO_EXTENSION));
$targetPath = $uploadDirectory . DIRECTORY_SEPARATOR . $posterFilename;

if (!move_uploaded_file($uploadedFile["tmp_name"], $targetPath)) {
    $errors[] = "Không thể lưu ảnh poster vào thư mục phim.";
    admin_add_movie_set_flash($errors, $oldInput, "");
    admin_add_movie_redirect("index.php?page_layout=themmoi_film");
}

$movieId = 0;
$insertMovie = $connect->prepare("
    INSERT INTO movies
    (title, description, release_year, language, country_id, link1, link2, img, view, date_add)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
");

if (!$insertMovie) {
    @unlink($targetPath);
    admin_add_movie_set_flash(array("Không thể khởi tạo truy vấn thêm phim."), $oldInput, "");
    admin_add_movie_redirect("index.php?page_layout=themmoi_film");
}

$releaseYear = (int) $namphathanh;
$currentDateTime = date("Y-m-d");
$insertMovie->bind_param("ssisissss", $tenphim, $mota, $releaseYear, $ngonngu, $quocgia, $link1, $link2, $posterFilename, $currentDateTime);

if (!$insertMovie->execute()) {
    $insertMovie->close();
    @unlink($targetPath);
    admin_add_movie_set_flash(array("Không thể thêm metadata phim vào hệ thống."), $oldInput, "");
    admin_add_movie_redirect("index.php?page_layout=themmoi_film");
}

$movieId = (int) $connect->insert_id;
$insertMovie->close();

$insertGenre = $connect->prepare("INSERT INTO movie_genre (movie_id, theloai_id) VALUES (?, ?)");

if (!$insertGenre) {
    $connect->query("DELETE FROM movies WHERE movie_id = " . $movieId);
    @unlink($targetPath);
    admin_add_movie_set_flash(array("Đã thêm phim nhưng không thể khởi tạo bước gán thể loại."), $oldInput, "");
    admin_add_movie_redirect("index.php?page_layout=themmoi_film");
}

$genreInsertFailed = false;
foreach ($genreIds as $genreId) {
    $insertGenre->bind_param("ii", $movieId, $genreId);
    if (!$insertGenre->execute()) {
        $genreInsertFailed = true;
        break;
    }
}
$insertGenre->close();

if ($genreInsertFailed) {
    $connect->query("DELETE FROM movie_genre WHERE movie_id = " . $movieId);
    $connect->query("DELETE FROM movies WHERE movie_id = " . $movieId);
    @unlink($targetPath);
    admin_add_movie_set_flash(array("Không thể gán thể loại cho phim. Dữ liệu đã được hoàn tác."), $oldInput, "");
    admin_add_movie_redirect("index.php?page_layout=themmoi_film");
}

$connect->close();
admin_add_movie_set_flash(array(), array(), "Đã thêm mới phim và gán thể loại thành công.");
admin_add_movie_redirect("index.php?page_layout=listfilm");
?>
