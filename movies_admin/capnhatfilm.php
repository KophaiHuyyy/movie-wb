<?php
include_once "checkpermission.php";
include_once "cauhinh.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists("admin_escape")) {
    function admin_escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
    }
}

if (!function_exists("admin_initials")) {
    function admin_initials($text)
    {
        $text = trim((string) $text);
        if ($text === "") {
            return "IT";
        }

        $parts = preg_split('/\s+/', $text);
        $initials = "";

        foreach ($parts as $part) {
            if ($part === "") {
                continue;
            }

            if (function_exists("mb_substr")) {
                $initials .= mb_strtoupper(mb_substr($part, 0, 1, "UTF-8"), "UTF-8");
            } else {
                $initials .= strtoupper(substr($part, 0, 1));
            }

            if (strlen($initials) >= 2) {
                break;
            }
        }

        return $initials !== "" ? $initials : "IT";
    }
}

if (!function_exists("admin_movie_poster")) {
    function admin_movie_poster($filename)
    {
        $safeName = basename((string) $filename);

        if ($safeName === "") {
            return "";
        }

        $relativePath = "hinhanhphim/" . $safeName;
        $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . "hinhanhphim" . DIRECTORY_SEPARATOR . $safeName;

        if (!is_file($absolutePath)) {
            return "";
        }

        return $relativePath;
    }
}

if (!function_exists("admin_format_number")) {
    function admin_format_number($value)
    {
        return number_format((float) $value, 0, ",", ".");
    }
}

$movieId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$errors = isset($_SESSION["admin_edit_movie_errors"]) && is_array($_SESSION["admin_edit_movie_errors"])
    ? $_SESSION["admin_edit_movie_errors"]
    : array();
$oldInput = isset($_SESSION["admin_edit_movie_old"]) && is_array($_SESSION["admin_edit_movie_old"])
    ? $_SESSION["admin_edit_movie_old"]
    : array();
$successMessage = isset($_SESSION["admin_edit_movie_success"]) ? (string) $_SESSION["admin_edit_movie_success"] : "";

unset($_SESSION["admin_edit_movie_errors"], $_SESSION["admin_edit_movie_old"], $_SESSION["admin_edit_movie_success"]);

$adminName = !empty($_SESSION["fullname"]) ? $_SESSION["fullname"] : (!empty($_SESSION["username"]) ? $_SESSION["username"] : "Admin");
$languageSuggestions = array("Tiếng Việt", "Tiếng Anh", "Vietsub", "Lồng tiếng", "Phụ đề");

$countries = array();
$countryResult = $connect->query("SELECT country_id, country_name FROM country ORDER BY country_name ASC");
if ($countryResult) {
    while ($row = $countryResult->fetch_assoc()) {
        $countries[] = $row;
    }
    $countryResult->free();
}

$genres = array();
$genreResult = $connect->query("SELECT theloai_id, ten_theloai FROM genres ORDER BY ten_theloai ASC");
if ($genreResult) {
    while ($row = $genreResult->fetch_assoc()) {
        $genres[] = $row;
    }
    $genreResult->free();
}

$movie = null;
if ($movieId > 0) {
    $movieStatement = $connect->prepare("
        SELECT movie_id, title, description, release_year, language, country_id, link1, link2, img, COALESCE(view, 0) AS total_views
        FROM movies
        WHERE movie_id = ?
        LIMIT 1
    ");

    if ($movieStatement) {
        $movieStatement->bind_param("i", $movieId);
        if ($movieStatement->execute()) {
            $movieResult = $movieStatement->get_result();
            if ($movieResult) {
                $movie = $movieResult->fetch_assoc();
                $movieResult->free();
            }
        }
        $movieStatement->close();
    }
}

$currentGenreIds = array();
if ($movie !== null) {
    $genreMapStatement = $connect->prepare("SELECT theloai_id FROM movie_genre WHERE movie_id = ?");
    if ($genreMapStatement) {
        $genreMapStatement->bind_param("i", $movieId);
        if ($genreMapStatement->execute()) {
            $genreMapResult = $genreMapStatement->get_result();
            if ($genreMapResult) {
                while ($genreRow = $genreMapResult->fetch_assoc()) {
                    $currentGenreIds[] = (int) $genreRow["theloai_id"];
                }
                $genreMapResult->free();
            }
        }
        $genreMapStatement->close();
    }
}

$selectedGenres = isset($oldInput["movie_genres"]) && is_array($oldInput["movie_genres"])
    ? $oldInput["movie_genres"]
    : $currentGenreIds;

$formValues = array(
    "txthoten" => $movie !== null ? (string) $movie["title"] : "",
    "txtmota" => $movie !== null ? (string) $movie["description"] : "",
    "txtnamphathanh" => $movie !== null ? (string) $movie["release_year"] : date("Y"),
    "txtngonngu" => $movie !== null ? (string) $movie["language"] : "Tiếng Việt",
    "cbb_quocgia" => $movie !== null ? (int) $movie["country_id"] : 0,
    "txtlink1" => $movie !== null ? (string) $movie["link1"] : "",
    "txtlink2" => $movie !== null ? (string) $movie["link2"] : "",
    "duongdan_input" => $movie !== null ? (string) $movie["img"] : "",
);

foreach ($oldInput as $key => $value) {
    if (array_key_exists($key, $formValues)) {
        $formValues[$key] = $value;
    }
}

$posterFilename = trim((string) $formValues["duongdan_input"]);
$posterUrl = admin_movie_poster($posterFilename);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa phim | ITMOVIES Admin</title>
    <link rel="stylesheet" href="style_admin.css">
</head>
<body>
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <div class="admin-brand">
                <div class="admin-brand-mark">IT</div>
                <div>
                    <p class="admin-brand-title">ITMOVIES</p>
                    <p class="admin-brand-subtitle">ADMIN DIRECTOR</p>
                </div>
            </div>

            <nav class="admin-nav">
                <a class="admin-nav-item" href="index.php">
                    <span class="admin-nav-icon">&#9638;</span>
                    <span>Tổng quan</span>
                </a>
                <a class="admin-nav-item is-active has-indicator" href="index.php?page_layout=listfilm">
                    <span class="admin-nav-icon">&#127916;</span>
                    <span>Quản lý phim</span>
                </a>
                <a class="admin-nav-item" href="index.php?page_layout=list_theloai">
                    <span class="admin-nav-icon">&#9673;</span>
                    <span>Thể loại</span>
                </a>
                <a class="admin-nav-item" href="index.php?page_layout=list_theloai_phim">
                    <span class="admin-nav-icon">&#8644;</span>
                    <span>Mapping</span>
                </a>
                <a class="admin-nav-item" href="index.php?page_layout=themquocgia">
                    <span class="admin-nav-icon">&#127760;</span>
                    <span>Quốc gia</span>
                </a>
                <a class="admin-nav-item" href="index.php?page_layout=list_user">
                    <span class="admin-nav-icon">&#128101;</span>
                    <span>Người dùng</span>
                </a>
                <a class="admin-nav-item" href="index.php#latest-reviews">
                    <span class="admin-nav-icon">&#9733;</span>
                    <span>Đánh giá</span>
                </a>
                <a class="admin-nav-item" href="index.php#top-movies">
                    <span class="admin-nav-icon">&#128200;</span>
                    <span>Phân tích</span>
                </a>
            </nav>

            <div class="admin-sidebar-footer">
                <a class="admin-nav-item" href="index.php?page_layout=listfilm">
                    <span class="admin-nav-icon">&#9881;</span>
                    <span>Cài đặt</span>
                </a>
                <a class="admin-logout" href="logout.php" onclick="return confirm('Bạn có chắc chắn muốn đăng xuất không?')">
                    <span class="admin-nav-icon">&#10162;</span>
                    <span>Đăng xuất</span>
                </a>
            </div>
        </aside>

        <main class="admin-main add-movie-page movie-form-page">
            <header class="admin-topbar">
                <div class="admin-breadcrumbs">
                    <a href="index.php?page_layout=listfilm">Phim</a>
                    <span>/</span>
                    <strong>Sửa phim</strong>
                </div>

                <div class="admin-topbar-actions">
                    <button class="admin-icon-button" type="button" aria-label="Thông báo">&#128276;</button>
                    <button class="admin-icon-button" type="button" aria-label="Cài đặt">&#9881;</button>
                    <div class="admin-profile">
                        <div class="admin-avatar"><?php echo admin_escape(admin_initials($adminName)); ?></div>
                        <div class="admin-profile-tooltip">
                            <p class="admin-profile-name"><?php echo admin_escape($adminName); ?></p>
                            <p class="admin-profile-role">Quản trị viên</p>
                        </div>
                    </div>
                </div>
            </header>

            <?php if ($movie === null) { ?>
                <section class="admin-card admin-empty-state movie-form-empty">
                    <h1>Không tìm thấy phim</h1>
                    <p>Phim bạn cần cập nhật không tồn tại hoặc ID không hợp lệ.</p>
                    <a class="admin-secondary-btn" href="index.php?page_layout=listfilm">Quay lại danh sách phim</a>
                </section>
            <?php } else { ?>
                <section class="add-movie-heading">
                    <div>
                        <h1>Sửa phim</h1>
                        <p>Cập nhật thông tin phim và tài nguyên lưu trữ.</p>
                        <div class="movie-form-meta">
                            <span class="movie-meta-pill">ID: MOV-<?php echo (int) $movie["movie_id"]; ?></span>
                            <span class="movie-meta-pill">Lượt xem: <?php echo admin_escape(admin_format_number($movie["total_views"])); ?></span>
                        </div>
                    </div>

                    <div class="server-pill server-pill-online">
                        <span class="server-pill-dot"></span>
                        <span>Đã kết nối Server</span>
                    </div>
                </section>

                <?php if (!empty($errors)) { ?>
                    <div class="admin-alert admin-alert-error">
                        <strong>Có lỗi xảy ra! Vui lòng kiểm tra lại thông tin.</strong>
                        <ul>
                            <?php foreach ($errors as $error) { ?>
                                <li><?php echo admin_escape($error); ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <?php if ($successMessage !== "") { ?>
                    <div class="admin-alert admin-alert-success">
                        <strong>Thành công!</strong>
                        <span><?php echo admin_escape($successMessage); ?></span>
                    </div>
                <?php } ?>

                <form class="add-movie-form" action="xuly_capnhatfilm.php?id=<?php echo (int) $movieId; ?>" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="duongdan_input" value="<?php echo admin_escape($posterFilename); ?>">

                    <div class="add-movie-layout movie-form-layout">
                        <div class="add-movie-main movie-main-column">
                            <section class="admin-card add-movie-section movie-form-card">
                                <div class="add-movie-section-head">
                                    <span class="section-icon section-icon-red">i</span>
                                    <div>
                                        <h2>Thông tin chính</h2>
                                        <p>Cập nhật metadata cốt lõi để trang chi tiết, danh sách và bộ lọc của phim luôn đồng bộ.</p>
                                    </div>
                                </div>

                                <div class="form-grid form-grid-single">
                                    <label class="admin-field">
                                        <span>Tiêu đề phim</span>
                                        <input type="text" name="txthoten" value="<?php echo admin_escape($formValues["txthoten"]); ?>" placeholder="Nhập tên phim chính xác...">
                                    </label>

                                    <label class="admin-field">
                                        <span>Mô tả nội dung</span>
                                        <textarea name="txtmota" rows="7" placeholder="Tóm tắt nội dung phim..."><?php echo admin_escape($formValues["txtmota"]); ?></textarea>
                                    </label>
                                </div>

                                <div class="form-grid form-grid-double">
                                    <label class="admin-field">
                                        <span>Năm phát hành</span>
                                        <input type="number" min="1900" max="<?php echo date("Y"); ?>" name="txtnamphathanh" value="<?php echo admin_escape($formValues["txtnamphathanh"]); ?>" placeholder="2024">
                                    </label>

                                    <label class="admin-field">
                                        <span>Ngôn ngữ</span>
                                        <input type="text" list="language-suggestions" name="txtngonngu" value="<?php echo admin_escape($formValues["txtngonngu"]); ?>" placeholder="Tiếng Việt">
                                    </label>
                                </div>

                                <div class="form-grid form-grid-single movie-form-country">
                                    <label class="admin-field">
                                        <span>Quốc gia</span>
                                        <div class="select-row">
                                            <select name="cbb_quocgia">
                                                <option value="0">Chọn quốc gia</option>
                                                <?php foreach ($countries as $country) { ?>
                                                    <option value="<?php echo (int) $country["country_id"]; ?>" <?php echo (int) $formValues["cbb_quocgia"] === (int) $country["country_id"] ? "selected" : ""; ?>>
                                                        <?php echo admin_escape($country["country_name"]); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                            <a class="inline-manage-link" href="index.php?page_layout=themquocgia">Thêm quốc gia</a>
                                        </div>
                                    </label>

                                    <?php if (empty($countries)) { ?>
                                        <div class="admin-empty-state admin-empty-state-compact">Chưa có dữ liệu quốc gia để gán cho phim.</div>
                                    <?php } ?>
                                </div>
                            </section>

                            <section class="admin-card add-movie-section movie-form-card">
                                <div class="add-movie-section-head">
                                    <span class="section-icon section-icon-link">&#128279;</span>
                                    <div>
                                        <h2>Nguồn phát Video</h2>
                                        <p>Giữ đúng nguồn phát hiện tại để người dùng xem phim không bị đứt luồng truy cập.</p>
                                    </div>
                                </div>

                                <div class="form-grid form-grid-single">
                                    <label class="admin-field">
                                        <span>Server 1 (Primary)</span>
                                        <input type="text" name="txtlink1" value="<?php echo admin_escape($formValues["txtlink1"]); ?>" placeholder="Nhập mã video hoặc link nguồn phát chính">
                                    </label>

                                    <label class="admin-field">
                                        <span>Server 2 (Backup)</span>
                                        <input type="text" name="txtlink2" value="<?php echo admin_escape($formValues["txtlink2"]); ?>" placeholder="Có thể để trống nếu chưa có server phụ">
                                    </label>
                                </div>

                                <div class="add-movie-tip">
                                    <span class="section-icon section-icon-warning">&#9888;</span>
                                    <p>Vui lòng kiểm tra kỹ đường dẫn server trước khi lưu.</p>
                                </div>
                            </section>
                        </div>

                        <aside class="add-movie-side movie-side-column">
                            <section class="admin-card add-movie-section movie-form-card poster-upload-card">
                                <div class="add-movie-section-head">
                                    <span class="section-icon section-icon-red">&#128247;</span>
                                    <div>
                                        <h2>Ảnh Poster</h2>
                                        <p>Giữ poster cũ nếu bạn không tải ảnh mới, chỉ thay thế khi cần cập nhật hình đại diện của phim.</p>
                                    </div>
                                </div>

                                <div class="poster-preview<?php echo $posterUrl === "" ? " is-empty" : ""; ?>" id="poster-preview">
                                    <img id="poster-preview-image" src="<?php echo admin_escape($posterUrl); ?>" alt="Poster hiện tại của phim" <?php echo $posterUrl === "" ? "hidden" : ""; ?>>
                                    <div class="poster-preview-placeholder" id="poster-preview-placeholder" <?php echo $posterUrl !== "" ? "hidden" : ""; ?>>
                                        <span class="poster-preview-placeholder-icon">&#127916;</span>
                                        <strong>Chưa có poster hợp lệ</strong>
                                        <small>Hãy chọn ảnh mới để cập nhật hiển thị cho phim.</small>
                                    </div>
                                    <p class="poster-preview-name" id="poster-preview-name"><?php echo admin_escape($posterFilename !== "" ? $posterFilename : "Poster hiện tại chưa sẵn sàng"); ?></p>
                                </div>

                                <label class="poster-upload-box">
                                    <input class="poster-upload-input" id="poster-upload-input" type="file" name="taptin1" accept=".jpg,.jpeg,.png,.webp">
                                    <span class="poster-upload-icon">&#8682;</span>
                                    <strong>Kéo thả ảnh vào đây hoặc chọn tập tin</strong>
                                    <small>Hỗ trợ JPG, PNG, WEBP, tối đa 5MB</small>
                                    <span class="poster-upload-button">Chọn ảnh mới</span>
                                </label>
                            </section>

                            <section class="admin-card add-movie-section movie-form-card">
                                <div class="add-movie-section-head">
                                    <span class="section-icon section-icon-tag">&#127991;</span>
                                    <div>
                                        <h2>Phân loại</h2>
                                        <p>Giữ đúng nhóm thể loại để phim tiếp tục xuất hiện đúng ở bộ lọc, gợi ý liên quan và trang chủ.</p>
                                    </div>
                                </div>

                                <?php if (empty($genres)) { ?>
                                    <div class="admin-empty-state admin-empty-state-compact">Chưa có thể loại nào để gán cho phim.</div>
                                <?php } else { ?>
                                    <div class="genre-selector">
                                        <span class="genre-selector-label">Thể loại phim</span>
                                        <div class="genre-selector-grid">
                                            <?php foreach ($genres as $genre) { ?>
                                                <?php $isChecked = in_array((string) $genre["theloai_id"], $selectedGenres, true) || in_array((int) $genre["theloai_id"], $selectedGenres, true); ?>
                                                <label class="genre-option">
                                                    <input type="checkbox" name="movie_genres[]" value="<?php echo (int) $genre["theloai_id"]; ?>" <?php echo $isChecked ? "checked" : ""; ?>>
                                                    <span><?php echo admin_escape($genre["ten_theloai"]); ?></span>
                                                </label>
                                            <?php } ?>
                                        </div>
                                    </div>
                                <?php } ?>
                            </section>

                            <section class="admin-card add-movie-section movie-form-card">
                                <div class="add-movie-section-head">
                                    <span class="section-icon section-icon-green">&#128065;</span>
                                    <div>
                                        <h2>Trạng thái hiển thị</h2>
                                        <p>Phim sẽ tiếp tục hiển thị cho người dùng ngay sau khi cập nhật thành công với dữ liệu hiện có.</p>
                                    </div>
                                </div>

                                <div class="visibility-card">
                                    <div class="visibility-card-copy">
                                        <span class="section-icon section-icon-green">&#10003;</span>
                                        <div>
                                            <strong>Hiển thị cho người dùng ngay</strong>
                                            <p>Không thêm cột trạng thái mới. Đây là mô tả UI để xác nhận phim vẫn dùng flow hiển thị hiện tại của hệ thống.</p>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </aside>
                    </div>

                    <div class="sticky-action-bar">
                        <div class="sticky-action-copy">
                            <span class="section-icon section-icon-warning">&#9888;</span>
                            <p>Vui lòng kiểm tra kỹ thông tin phim trước khi cập nhật.</p>
                        </div>

                        <div class="sticky-action-actions">
                            <a class="admin-secondary-btn" href="index.php?page_layout=listfilm">Hủy</a>
                            <button class="admin-primary-btn" type="submit" name="btncapnhat">Cập nhật</button>
                        </div>
                    </div>
                </form>
            <?php } ?>
        </main>
    </div>

    <datalist id="language-suggestions">
        <?php foreach ($languageSuggestions as $languageSuggestion) { ?>
            <option value="<?php echo admin_escape($languageSuggestion); ?>"></option>
        <?php } ?>
    </datalist>

    <script>
        (function () {
            var input = document.getElementById("poster-upload-input");
            var preview = document.getElementById("poster-preview");
            var previewImage = document.getElementById("poster-preview-image");
            var previewName = document.getElementById("poster-preview-name");
            var previewPlaceholder = document.getElementById("poster-preview-placeholder");

            if (!input || !preview || !previewImage || !previewName || !previewPlaceholder) {
                return;
            }

            input.addEventListener("change", function (event) {
                var file = event.target.files && event.target.files[0] ? event.target.files[0] : null;

                if (!file || file.type.indexOf("image/") !== 0) {
                    return;
                }

                var reader = new FileReader();

                reader.onload = function (loadEvent) {
                    preview.classList.remove("is-empty");
                    previewImage.src = loadEvent.target.result;
                    previewImage.hidden = false;
                    previewPlaceholder.hidden = true;
                    previewName.textContent = file.name;
                };

                reader.readAsDataURL(file);
            });
        })();
    </script>
</body>
</html>
