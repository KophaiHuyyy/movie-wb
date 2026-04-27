<?php
include_once "checkpermission.php";
include_once "cauhinh.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function admin_edit_movie_redirect($location)
{
    header("Location: " . $location);
    exit();
}

function admin_edit_movie_set_flash($errors = array(), $old = array(), $success = "")
{
    $_SESSION["admin_edit_movie_errors"] = $errors;
    $_SESSION["admin_edit_movie_old"] = $old;
    $_SESSION["admin_edit_movie_success"] = $success;
}

$movieId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($movieId <= 0) {
    admin_edit_movie_redirect("index.php?page_layout=listfilm");
}

if (isset($_POST["btnhuy"])) {
    admin_edit_movie_redirect("index.php?page_layout=listfilm");
}

$tenphim = isset($_POST["txthoten"]) ? trim((string) $_POST["txthoten"]) : "";
$mota = isset($_POST["txtmota"]) ? trim((string) $_POST["txtmota"]) : "";
$namphathanh = isset($_POST["txtnamphathanh"]) ? trim((string) $_POST["txtnamphathanh"]) : "";
$ngonngu = isset($_POST["txtngonngu"]) ? trim((string) $_POST["txtngonngu"]) : "";
$quocgia = isset($_POST["cbb_quocgia"]) ? (int) $_POST["cbb_quocgia"] : 0;
$link1 = isset($_POST["txtlink1"]) ? trim((string) $_POST["txtlink1"]) : "";
$link2 = isset($_POST["txtlink2"]) ? trim((string) $_POST["txtlink2"]) : "";
$currentPoster = isset($_POST["duongdan_input"]) ? basename(trim((string) $_POST["duongdan_input"])) : "";
$selectedGenres = isset($_POST["movie_genres"]) && is_array($_POST["movie_genres"]) ? $_POST["movie_genres"] : array();

$oldInput = array(
    "txthoten" => $tenphim,
    "txtmota" => $mota,
    "txtnamphathanh" => $namphathanh,
    "txtngonngu" => $ngonngu,
    "cbb_quocgia" => $quocgia,
    "txtlink1" => $link1,
    "txtlink2" => $link2,
    "duongdan_input" => $currentPoster,
    "movie_genres" => $selectedGenres,
);

$movieCheck = $connect->prepare("
    SELECT title, description, release_year, language, country_id, link1, link2, img
    FROM movies
    WHERE movie_id = ?
    LIMIT 1
");
if (!$movieCheck) {
    admin_edit_movie_set_flash(array("Không thể khởi tạo truy vấn kiểm tra phim."), $oldInput, "");
    admin_edit_movie_redirect("index.php?page_layout=capnhatfilm&id=" . $movieId);
}

$movieCheck->bind_param("i", $movieId);
$movieExists = false;
$storedPoster = "";
$originalMovie = null;
if ($movieCheck->execute()) {
    $movieResult = $movieCheck->get_result();
    if ($movieResult) {
        $movieRow = $movieResult->fetch_assoc();
        if ($movieRow) {
            $movieExists = true;
            $storedPoster = basename((string) $movieRow["img"]);
            $originalMovie = $movieRow;
        }
        $movieResult->free();
    }
}
$movieCheck->close();

if (!$movieExists) {
    admin_edit_movie_redirect("index.php?page_layout=listfilm");
}

if ($currentPoster === "") {
    $currentPoster = $storedPoster;
    $oldInput["duongdan_input"] = $currentPoster;
}

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
    $errors[] = "Vui lòng chọn ít nhất một thể loại.";
}

$uploadedFile = isset($_FILES["taptin1"]) ? $_FILES["taptin1"] : null;
$posterFilename = $currentPoster;
$newPosterUploaded = false;

if ($uploadedFile !== null && isset($uploadedFile["error"]) && $uploadedFile["error"] !== UPLOAD_ERR_NO_FILE) {
    if ($uploadedFile["error"] !== UPLOAD_ERR_OK) {
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
}

if ($posterFilename === "" && ($uploadedFile === null || !isset($uploadedFile["error"]) || $uploadedFile["error"] === UPLOAD_ERR_NO_FILE)) {
    $errors[] = "Vui lòng giữ poster hiện tại hoặc tải lên ảnh mới.";
}

if (!empty($errors)) {
    admin_edit_movie_set_flash($errors, $oldInput, "");
    admin_edit_movie_redirect("index.php?page_layout=capnhatfilm&id=" . $movieId);
}

$oldGenreIds = array();
$oldGenreQuery = $connect->prepare("SELECT theloai_id FROM movie_genre WHERE movie_id = ?");
if ($oldGenreQuery) {
    $oldGenreQuery->bind_param("i", $movieId);
    if ($oldGenreQuery->execute()) {
        $oldGenreResult = $oldGenreQuery->get_result();
        if ($oldGenreResult) {
            while ($row = $oldGenreResult->fetch_assoc()) {
                $oldGenreIds[] = (int) $row["theloai_id"];
            }
            $oldGenreResult->free();
        }
    }
    $oldGenreQuery->close();
}

$uploadedPosterPath = "";
$uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . "hinhanhphim";

if ($uploadedFile !== null && isset($uploadedFile["error"]) && $uploadedFile["error"] === UPLOAD_ERR_OK) {
    if (!is_dir($uploadDirectory)) {
        admin_edit_movie_set_flash(array("Thư mục lưu poster không tồn tại."), $oldInput, "");
        admin_edit_movie_redirect("index.php?page_layout=capnhatfilm&id=" . $movieId);
    }

    $posterFilename = "movie-" . date("YmdHis") . "-" . mt_rand(1000, 9999) . "." . strtolower(pathinfo($uploadedFile["name"], PATHINFO_EXTENSION));
    $uploadedPosterPath = $uploadDirectory . DIRECTORY_SEPARATOR . $posterFilename;

    if (!move_uploaded_file($uploadedFile["tmp_name"], $uploadedPosterPath)) {
        admin_edit_movie_set_flash(array("Không thể lưu ảnh poster vào thư mục phim."), $oldInput, "");
        admin_edit_movie_redirect("index.php?page_layout=capnhatfilm&id=" . $movieId);
    }

    $newPosterUploaded = true;
}

$releaseYear = (int) $namphathanh;
$updateMovie = $connect->prepare("
    UPDATE movies
    SET title = ?, description = ?, release_year = ?, language = ?, country_id = ?, link1 = ?, link2 = ?, img = ?
    WHERE movie_id = ?
");

if (!$updateMovie) {
    if ($uploadedPosterPath !== "") {
        @unlink($uploadedPosterPath);
    }
    admin_edit_movie_set_flash(array("Không thể khởi tạo truy vấn cập nhật phim."), $oldInput, "");
    admin_edit_movie_redirect("index.php?page_layout=capnhatfilm&id=" . $movieId);
}

$updateMovie->bind_param("ssisisssi", $tenphim, $mota, $releaseYear, $ngonngu, $quocgia, $link1, $link2, $posterFilename, $movieId);

if (!$updateMovie->execute()) {
    $updateMovie->close();
    if ($uploadedPosterPath !== "") {
        @unlink($uploadedPosterPath);
    }
    admin_edit_movie_set_flash(array("Không thể cập nhật thông tin phim vào hệ thống."), $oldInput, "");
    admin_edit_movie_redirect("index.php?page_layout=capnhatfilm&id=" . $movieId);
}

$updateMovie->close();

$deleteGenres = $connect->prepare("DELETE FROM movie_genre WHERE movie_id = ?");
if (!$deleteGenres) {
    if ($originalMovie !== null) {
        $restoreMovie = $connect->prepare("
            UPDATE movies
            SET title = ?, description = ?, release_year = ?, language = ?, country_id = ?, link1 = ?, link2 = ?, img = ?
            WHERE movie_id = ?
        ");
        if ($restoreMovie) {
            $restoreReleaseYear = (int) $originalMovie["release_year"];
            $restoreTitle = (string) $originalMovie["title"];
            $restoreDescription = (string) $originalMovie["description"];
            $restoreLanguage = (string) $originalMovie["language"];
            $restoreCountry = (int) $originalMovie["country_id"];
            $restoreLink1 = (string) $originalMovie["link1"];
            $restoreLink2 = (string) $originalMovie["link2"];
            $restorePoster = (string) $originalMovie["img"];
            $restoreMovie->bind_param("ssisisssi", $restoreTitle, $restoreDescription, $restoreReleaseYear, $restoreLanguage, $restoreCountry, $restoreLink1, $restoreLink2, $restorePoster, $movieId);
            $restoreMovie->execute();
            $restoreMovie->close();
        }
    }
    if ($uploadedPosterPath !== "") {
        @unlink($uploadedPosterPath);
    }
    $oldInput["duongdan_input"] = $currentPoster;
    admin_edit_movie_set_flash(array("Không thể khởi tạo bước cập nhật thể loại."), $oldInput, "");
    admin_edit_movie_redirect("index.php?page_layout=capnhatfilm&id=" . $movieId);
}

$deleteGenres->bind_param("i", $movieId);
$genreUpdateFailed = !$deleteGenres->execute();
$deleteGenres->close();

$insertGenre = null;
if (!$genreUpdateFailed) {
    $insertGenre = $connect->prepare("INSERT INTO movie_genre (movie_id, theloai_id) VALUES (?, ?)");
    if (!$insertGenre) {
        $genreUpdateFailed = true;
    }
}

if (!$genreUpdateFailed && $insertGenre) {
    foreach ($genreIds as $genreId) {
        $insertGenre->bind_param("ii", $movieId, $genreId);
        if (!$insertGenre->execute()) {
            $genreUpdateFailed = true;
            break;
        }
    }
    $insertGenre->close();
}

if ($genreUpdateFailed) {
    if ($originalMovie !== null) {
        $restoreMovie = $connect->prepare("
            UPDATE movies
            SET title = ?, description = ?, release_year = ?, language = ?, country_id = ?, link1 = ?, link2 = ?, img = ?
            WHERE movie_id = ?
        ");
        if ($restoreMovie) {
            $restoreReleaseYear = (int) $originalMovie["release_year"];
            $restoreTitle = (string) $originalMovie["title"];
            $restoreDescription = (string) $originalMovie["description"];
            $restoreLanguage = (string) $originalMovie["language"];
            $restoreCountry = (int) $originalMovie["country_id"];
            $restoreLink1 = (string) $originalMovie["link1"];
            $restoreLink2 = (string) $originalMovie["link2"];
            $restorePoster = (string) $originalMovie["img"];
            $restoreMovie->bind_param("ssisisssi", $restoreTitle, $restoreDescription, $restoreReleaseYear, $restoreLanguage, $restoreCountry, $restoreLink1, $restoreLink2, $restorePoster, $movieId);
            $restoreMovie->execute();
            $restoreMovie->close();
        }
    }

    $restoreDelete = $connect->prepare("DELETE FROM movie_genre WHERE movie_id = ?");
    if ($restoreDelete) {
        $restoreDelete->bind_param("i", $movieId);
        $restoreDelete->execute();
        $restoreDelete->close();
    }

    $restoreInsert = $connect->prepare("INSERT INTO movie_genre (movie_id, theloai_id) VALUES (?, ?)");
    if ($restoreInsert) {
        foreach ($oldGenreIds as $oldGenreId) {
            $restoreInsert->bind_param("ii", $movieId, $oldGenreId);
            $restoreInsert->execute();
        }
        $restoreInsert->close();
    }

    if ($uploadedPosterPath !== "") {
        @unlink($uploadedPosterPath);
    }

    $oldInput["duongdan_input"] = $currentPoster;
    admin_edit_movie_set_flash(array("Không thể cập nhật thể loại cho phim. Dữ liệu thể loại cũ đã được khôi phục nếu khả dụng."), $oldInput, "");
    admin_edit_movie_redirect("index.php?page_layout=capnhatfilm&id=" . $movieId);
}

if ($newPosterUploaded && $currentPoster !== "" && $currentPoster !== $posterFilename) {
    $oldPosterPath = $uploadDirectory . DIRECTORY_SEPARATOR . $currentPoster;
    if (is_file($oldPosterPath)) {
        @unlink($oldPosterPath);
    }
}

$connect->close();
?>
<script>
    alert("Thành công! Thông tin phim đã được cập nhật lên hệ thống.");
    window.location.href = "index.php?page_layout=listfilm";
</script>
