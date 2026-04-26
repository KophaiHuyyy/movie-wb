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

$errors = isset($_SESSION["admin_add_movie_errors"]) && is_array($_SESSION["admin_add_movie_errors"])
    ? $_SESSION["admin_add_movie_errors"]
    : array();
$oldInput = isset($_SESSION["admin_add_movie_old"]) && is_array($_SESSION["admin_add_movie_old"])
    ? $_SESSION["admin_add_movie_old"]
    : array();
$successMessage = isset($_SESSION["admin_add_movie_success"]) ? $_SESSION["admin_add_movie_success"] : "";

unset($_SESSION["admin_add_movie_errors"], $_SESSION["admin_add_movie_old"], $_SESSION["admin_add_movie_success"]);

$adminName = !empty($_SESSION["fullname"]) ? $_SESSION["fullname"] : (!empty($_SESSION["username"]) ? $_SESSION["username"] : "Admin");
$selectedGenres = isset($oldInput["movie_genres"]) && is_array($oldInput["movie_genres"]) ? $oldInput["movie_genres"] : array();
$languageSuggestions = array("Tiếng Việt", "Tiếng Anh", "Vietsub", "Lồng tiếng", "Phụ đề");
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm phim | ITMOVIES Admin</title>
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
                <a class="admin-logout" href="logout.php"
                    onclick="return confirm('Bạn có chắc chắn muốn đăng xuất không?')">
                    <span class="admin-nav-icon">&#10162;</span>
                    <span>Đăng xuất</span>
                </a>
            </div>
        </aside>

        <main class="admin-main add-movie-page">
            <header class="admin-topbar">
                <div class="admin-breadcrumbs">
                    <a href="index.php?page_layout=listfilm">Phim</a>
                    <span>/</span>
                    <strong>Thêm phim</strong>
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

            <section class="add-movie-heading">
                <div>
                    <h1>Thêm phim</h1>
                    <p>Cấu hình thông tin phim và tài nguyên lưu trữ trong cùng một phiên thao tác.</p>
                </div>

                <div class="server-pill server-pill-online">
                    <span class="server-pill-dot"></span>
                    <span>Đã kết nối Server</span>
                </div>
            </section>

            <?php if (!empty($errors)) { ?>
            <div class="admin-alert admin-alert-error">
                <strong>Không thể lưu phim:</strong>
                <ul>
                    <?php foreach ($errors as $error) { ?>
                    <li><?php echo admin_escape($error); ?></li>
                    <?php } ?>
                </ul>
            </div>
            <?php } ?>

            <?php if ($successMessage !== "") { ?>
            <div class="admin-alert admin-alert-success">
                <strong>Thành công:</strong>
                <span><?php echo admin_escape($successMessage); ?></span>
            </div>
            <?php } ?>

            <form class="add-movie-form" action="xuly_themfilm.php" method="post" enctype="multipart/form-data">
                <div class="add-movie-layout">
                    <div class="add-movie-main">
                        <section class="admin-card add-movie-section">
                            <div class="add-movie-section-head">
                                <span class="section-icon section-icon-red">i</span>
                                <div>
                                    <h2>Thông tin chính</h2>
                                    <p>Metadata cốt lõi sẽ được lưu vào bảng `movies` ngay trong bước đầu tiên.</p>
                                </div>
                            </div>

                            <div class="form-grid form-grid-single">
                                <label class="admin-field">
                                    <span>TIÊU ĐỀ PHIM</span>
                                    <input type="text" name="txthoten"
                                        value="<?php echo admin_escape(isset($oldInput["txthoten"]) ? $oldInput["txthoten"] : ""); ?>"
                                        placeholder="Nhập tên phim chính xác...">
                                </label>

                                <label class="admin-field">
                                    <span>MÔ TẢ NỘI DUNG</span>
                                    <textarea name="txtmota" rows="7"
                                        placeholder="Tóm tắt nội dung phim..."><?php echo admin_escape(isset($oldInput["txtmota"]) ? $oldInput["txtmota"] : ""); ?></textarea>
                                </label>
                            </div>

                            <div class="form-grid form-grid-double">
                                <label class="admin-field">
                                    <span>NĂM PHÁT HÀNH</span>
                                    <input type="number" min="1900" max="<?php echo date("Y"); ?>" name="txtnamphathanh"
                                        value="<?php echo admin_escape(isset($oldInput["txtnamphathanh"]) ? $oldInput["txtnamphathanh"] : date("Y")); ?>"
                                        placeholder="2024">
                                </label>

                                <label class="admin-field">
                                    <span>NGÔN NGỮ</span>
                                    <input type="text" list="language-suggestions" name="txtngonngu"
                                        value="<?php echo admin_escape(isset($oldInput["txtngonngu"]) ? $oldInput["txtngonngu"] : "Tiếng Việt"); ?>"
                                        placeholder="Tiếng Việt">
                                </label>
                            </div>
                            <datalist id="language-suggestions">
                                <?php foreach ($languageSuggestions as $languageSuggestion) { ?>
                                <option value="<?php echo admin_escape($languageSuggestion); ?>"></option>
                                <?php } ?>
                            </datalist>
                        </section>

                        <section class="admin-card add-movie-section">
                            <div class="add-movie-section-head">
                                <span class="section-icon section-icon-link">&#128279;</span>
                                <div>
                                    <h2>Nguồn phát Video</h2>
                                    <p>Server 1 là nguồn bắt buộc để đảm bảo người dùng có thể xem phim ngay sau khi
                                        lưu.</p>
                                </div>
                            </div>

                            <div class="form-grid form-grid-single">
                                <label class="admin-field">
                                    <span>SERVER 1 (PRIMARY)</span>
                                    <input type="text" name="txtlink1"
                                        value="<?php echo admin_escape(isset($oldInput["txtlink1"]) ? $oldInput["txtlink1"] : ""); ?>"
                                        placeholder="Nhập mã video hoặc link nguồn phát chính">
                                </label>

                                <label class="admin-field">
                                    <span>SERVER 2 (BACKUP)</span>
                                    <input type="text" name="txtlink2"
                                        value="<?php echo admin_escape(isset($oldInput["txtlink2"]) ? $oldInput["txtlink2"] : ""); ?>"
                                        placeholder="Có thể bỏ trống nếu chưa có server phụ">
                                </label>
                            </div>

                            <div class="add-movie-tip">
                                <span class="section-icon section-icon-warning">&#9888;</span>
                                <p>Vui lòng kiểm tra kỹ đường dẫn server trước khi lưu. `xemphim` đang dùng `link1`, còn
                                    `reviewphim` sẽ dùng `link2` nếu có, hoặc fallback sang `link1` sau khi được harden.
                                </p>
                            </div>
                        </section>
                    </div>

                    <aside class="add-movie-side">
                        <section class="admin-card add-movie-section">
                            <div class="add-movie-section-head">
                                <span class="section-icon section-icon-red">&#128247;</span>
                                <div>
                                    <h2>Ảnh Poster</h2>
                                    <p>Ảnh sẽ được lưu vào thư mục `movies_admin/hinhanhphim/` và DB chỉ lưu filename.
                                    </p>
                                </div>
                            </div>

                            <label class="poster-upload-box">
                                <input class="poster-upload-input" id="poster-upload-input" type="file" name="taptin1"
                                    accept=".jpg,.jpeg,.png,.webp">
                                <span class="poster-upload-icon">&#8682;</span>
                                <strong>Kéo thả ảnh vào đây</strong>
                                <small>Hỗ trợ JPG, JPEG, PNG, WEBP, tối đa 5MB</small>
                                <span class="poster-upload-button">Chọn tập tin</span>
                            </label>
                            <div class="poster-preview" id="poster-preview" hidden>
                                <img id="poster-preview-image" src="" alt="Xem trước poster phim">
                                <p class="poster-preview-name" id="poster-preview-name"></p>
                            </div>
                        </section>

                        <section class="admin-card add-movie-section">
                            <div class="add-movie-section-head">
                                <span class="section-icon section-icon-tag">&#127991;</span>
                                <div>
                                    <h2>Phân loại</h2>
                                    <p>Chọn thể loại ngay trong cùng phiên để phim có thể xem và gợi ý liên quan ổn
                                        định.</p>
                                </div>
                            </div>

                            <div class="form-grid form-grid-single">
                                <label class="admin-field">
                                    <span>QUỐC GIA</span>
                                    <div class="select-row">
                                        <select name="cbb_quocgia">
                                            <option value="0">Chọn quốc gia</option>
                                            <?php foreach ($countries as $country) { ?>
                                            <option value="<?php echo (int) $country["country_id"]; ?>"
                                                <?php echo isset($oldInput["cbb_quocgia"]) && (int) $oldInput["cbb_quocgia"] === (int) $country["country_id"] ? "selected" : ""; ?>>
                                                <?php echo admin_escape($country["country_name"]); ?>
                                            </option>
                                            <?php } ?>
                                        </select>
                                        <a class="inline-manage-link" href="index.php?page_layout=themquocgia">Thêm quốc
                                            gia</a>
                                    </div>
                                </label>
                            </div>

                            <div class="genre-selector">
                                <span class="genre-selector-label">THỂ LOẠI PHIM</span>
                                <div class="genre-selector-grid">
                                    <?php foreach ($genres as $genre) { ?>
                                    <?php $isChecked = in_array((string) $genre["theloai_id"], $selectedGenres, true) || in_array((int) $genre["theloai_id"], $selectedGenres, true); ?>
                                    <label class="genre-option">

                                        <input type="checkbox" name="movie_genres[]"
                                            value="<?php echo (int) $genre["theloai_id"]; ?>"
                                            <?php echo $isChecked ? "checked" : ""; ?>>
                                        <span><?php echo admin_escape($genre["ten_theloai"]); ?></span>
                                    </label>
                                    <?php } ?>
                                </div>
                            </div>

                            <div class="visibility-card">
                                <div class="visibility-card-copy">
                                    <span class="section-icon section-icon-green">&#128065;</span>
                                    <div>
                                        <strong>Hiển thị cho người dùng ngay</strong>
                                        <p>Sau khi lưu thành công và đã có thể loại, phim sẽ đủ dữ liệu để vào được màn
                                            xem chi tiết và xem phim.</p>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </aside>
                </div>

                <div class="add-movie-actions">
                    <a class="admin-secondary-btn" href="index.php?page_layout=listfilm">Hủy</a>
                    <button class="admin-primary-btn" type="submit" name="btnthem">Lưu</button>
                </div>
            </form>
        </main>
    </div>
    <script>
    (function() {
        var input = document.getElementById("poster-upload-input");
        var preview = document.getElementById("poster-preview");
        var previewImage = document.getElementById("poster-preview-image");
        var previewName = document.getElementById("poster-preview-name");

        if (!input || !preview || !previewImage || !previewName) {
            return;
        }

        input.addEventListener("change", function(event) {
            var file = event.target.files && event.target.files[0] ? event.target.files[0] : null;

            if (!file || file.type.indexOf("image/") !== 0) {
                preview.hidden = true;
                previewImage.removeAttribute("src");
                previewName.textContent = "";
                return;
            }

            var reader = new FileReader();

            reader.onload = function(loadEvent) {
                previewImage.src = loadEvent.target.result;
                previewName.textContent = file.name;
                preview.hidden = false;
            };

            reader.readAsDataURL(file);
        });
    })();
    </script>
</body>

</html>