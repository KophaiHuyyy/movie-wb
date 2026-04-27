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

if (!function_exists("admin_bind_params")) {
    function admin_bind_params($statement, $types, &$params)
    {
        if ($types === "" || empty($params)) {
            return;
        }

        $bindValues = array($types);

        foreach ($params as $index => $value) {
            $bindValues[] = &$params[$index];
        }

        call_user_func_array(array($statement, "bind_param"), $bindValues);
    }
}

if (!function_exists("admin_fetch_rows_prepared")) {
    function admin_fetch_rows_prepared($connect, $sql, $types = "", $params = array())
    {
        $rows = array();
        $statement = $connect->prepare($sql);

        if (!$statement) {
            return $rows;
        }

        if ($types !== "" && !empty($params)) {
            admin_bind_params($statement, $types, $params);
        }

        if (!$statement->execute()) {
            $statement->close();
            return $rows;
        }

        $result = $statement->get_result();

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }

            $result->free();
        }

        $statement->close();

        return $rows;
    }
}

if (!function_exists("admin_fetch_scalar_prepared")) {
    function admin_fetch_scalar_prepared($connect, $sql, $field, $types = "", $params = array(), $default = 0)
    {
        $statement = $connect->prepare($sql);

        if (!$statement) {
            return $default;
        }

        if ($types !== "" && !empty($params)) {
            admin_bind_params($statement, $types, $params);
        }

        if (!$statement->execute()) {
            $statement->close();
            return $default;
        }

        $result = $statement->get_result();
        $value = $default;

        if ($result) {
            $row = $result->fetch_assoc();
            if ($row && isset($row[$field]) && $row[$field] !== null) {
                $value = $row[$field];
            }

            $result->free();
        }

        $statement->close();

        return $value;
    }
}

if (!function_exists("admin_format_number")) {
    function admin_format_number($value)
    {
        return number_format((float) $value, 0, ",", ".");
    }
}

if (!function_exists("admin_slugify_preview")) {
    function admin_slugify_preview($text)
    {
        $text = trim((string) $text);
        if ($text === "") {
            return "the-loai-phim";
        }

        if (function_exists("iconv")) {
            $converted = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim((string) $text, '-');

        return $text !== "" ? $text : "the-loai-phim";
    }
}

if (!function_exists("admin_genre_color")) {
    function admin_genre_color($seed)
    {
        $palette = array("#f7a39a", "#4a80e8", "#a24df2", "#1fbb84", "#ff963c");
        $seed = (int) $seed;

        return $palette[abs($seed) % count($palette)];
    }
}

if (!function_exists("admin_genre_list_url")) {
    function admin_genre_list_url($params = array())
    {
        $query = array_merge(array("page_layout" => "list_theloai"), $params);

        foreach ($query as $key => $value) {
            if ($value === "" || $value === null) {
                unset($query[$key]);
            }
        }

        return "index.php?" . http_build_query($query);
    }
}

$adminName = !empty($_SESSION["fullname"]) ? $_SESSION["fullname"] : (!empty($_SESSION["username"]) ? $_SESSION["username"] : "Admin");
$keyword = isset($_GET["keyword"]) ? trim((string) $_GET["keyword"]) : "";
$drawerMode = isset($_GET["drawer"]) ? trim((string) $_GET["drawer"]) : "";
$drawerMode = in_array($drawerMode, array("add", "edit"), true) ? $drawerMode : "";
$editId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

$notice = isset($_SESSION["admin_genre_notice"]) && is_array($_SESSION["admin_genre_notice"])
    ? $_SESSION["admin_genre_notice"]
    : array();
unset($_SESSION["admin_genre_notice"]);

$totalGenres = (int) admin_fetch_scalar_prepared($connect, "SELECT COUNT(*) AS total FROM genres", "total", "", array(), 0);
$taggedMovies = (int) admin_fetch_scalar_prepared($connect, "SELECT COUNT(DISTINCT movie_id) AS total FROM movie_genre", "total", "", array(), 0);
$popularGenreRows = admin_fetch_rows_prepared(
    $connect,
    "SELECT g.theloai_id, g.ten_theloai, COUNT(mg.movie_id) AS movie_total
     FROM genres g
     LEFT JOIN movie_genre mg ON mg.theloai_id = g.theloai_id
     GROUP BY g.theloai_id, g.ten_theloai
     ORDER BY movie_total DESC, g.theloai_id ASC
     LIMIT 1"
);
$popularGenre = !empty($popularGenreRows) ? $popularGenreRows[0] : null;

$genreSql = "SELECT g.theloai_id, g.ten_theloai, COUNT(mg.movie_id) AS movie_total
    FROM genres g
    LEFT JOIN movie_genre mg ON mg.theloai_id = g.theloai_id";
$genreTypes = "";
$genreParams = array();

if ($keyword !== "") {
    $genreSql .= " WHERE g.ten_theloai LIKE ?";
    $genreTypes .= "s";
    $genreParams[] = "%" . $keyword . "%";
}

$genreSql .= " GROUP BY g.theloai_id, g.ten_theloai ORDER BY g.theloai_id ASC";
$genres = admin_fetch_rows_prepared($connect, $genreSql, $genreTypes, $genreParams);
$filteredTotal = count($genres);

$editingGenre = null;
if ($drawerMode === "edit" && $editId > 0) {
    $editRows = admin_fetch_rows_prepared(
        $connect,
        "SELECT theloai_id, ten_theloai FROM genres WHERE theloai_id = ? LIMIT 1",
        "i",
        array($editId)
    );

    if (!empty($editRows)) {
        $editingGenre = $editRows[0];
    } else {
        $drawerMode = "";
        $notice = array(
            "type" => "error",
            "message" => "Không tìm thấy thể loại cần chỉnh sửa."
        );
    }
}

$drawerOpen = $drawerMode !== "";
$slugSeed = $editingGenre ? $editingGenre["ten_theloai"] : "";
$selectedColor = admin_genre_color($editingGenre ? $editingGenre["theloai_id"] : 0);
$colorOptions = array("#f7a39a", "#4a80e8", "#a24df2", "#1fbb84", "#ff963c");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý thể loại | ITMOVIES Admin</title>
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
                <a class="admin-nav-item" href="index.php?page_layout=listfilm">
                    <span class="admin-nav-icon">&#127916;</span>
                    <span>Quản lý phim</span>
                </a>
                <a class="admin-nav-item is-active has-indicator" href="index.php?page_layout=list_theloai">
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

        <main class="admin-main genre-page<?php echo $drawerOpen ? " has-drawer" : ""; ?>">
            <header class="admin-topbar">
                <form class="admin-search admin-search-wide" action="index.php" method="get">
                    <input type="hidden" name="page_layout" value="list_theloai">
                    <span class="admin-search-icon">&#9906;</span>
                    <input type="search" name="keyword" value="<?php echo admin_escape($keyword); ?>" placeholder="Tìm kiếm thể loại...">
                </form>

                <div class="admin-topbar-actions">
                    <a class="admin-primary-btn" href="<?php echo admin_escape(admin_genre_list_url(array("keyword" => $keyword, "drawer" => "add"))); ?>">Thêm thể loại</a>
                    <div class="admin-profile">
                        <div class="admin-avatar"><?php echo admin_escape(admin_initials($adminName)); ?></div>
                        <div class="admin-profile-tooltip">
                            <p class="admin-profile-name"><?php echo admin_escape($adminName); ?></p>
                            <p class="admin-profile-role">Quản trị viên</p>
                        </div>
                    </div>
                </div>
            </header>

            <?php if (!empty($notice["message"])) { ?>
                <div class="admin-alert <?php echo $notice["type"] === "success" ? "admin-alert-success" : "admin-alert-error"; ?>">
                    <strong><?php echo $notice["type"] === "success" ? "Thành công:" : "Lưu ý:"; ?></strong>
                    <span><?php echo admin_escape($notice["message"]); ?></span>
                </div>
            <?php } ?>

            <div class="genre-layout">
                <div class="genre-content">
                    <section class="genre-header">
                        <div>
                            <h1>Quản lý thể loại</h1>
                            <p>Sắp xếp và tổ chức các danh mục phim trên hệ thống.</p>
                        </div>

                        <a class="admin-secondary-btn genre-mobile-add" href="<?php echo admin_escape(admin_genre_list_url(array("keyword" => $keyword, "drawer" => "add"))); ?>">Thêm thể loại</a>
                    </section>

                    <section class="genre-stats">
                        <article class="stat-card genre-stat-card genre-stat-card-rose">
                            <div class="genre-stat-top">
                                <span class="genre-stat-icon">&#9673;</span>
                                <span class="genre-stat-meta">Danh mục</span>
                            </div>
                            <p class="stat-card-label">Tổng thể loại</p>
                            <p class="stat-card-value"><?php echo admin_escape(admin_format_number($totalGenres)); ?></p>
                            <p class="stat-card-meta">Số bản ghi hiện có trong bảng `genres`.</p>
                        </article>

                        <article class="stat-card genre-stat-card genre-stat-card-blue">
                            <div class="genre-stat-top">
                                <span class="genre-stat-icon">&#127871;</span>
                                <span class="genre-stat-meta">Movie tags</span>
                            </div>
                            <p class="stat-card-label">Phim được gắn nhãn</p>
                            <p class="stat-card-value"><?php echo admin_escape(admin_format_number($taggedMovies)); ?></p>
                            <p class="stat-card-meta">Đếm theo số phim khác nhau trong `movie_genre`.</p>
                        </article>

                        <article class="stat-card genre-stat-card genre-stat-card-green">
                            <div class="genre-stat-top">
                                <span class="genre-stat-icon">&#9733;</span>
                                <span class="genre-stat-meta">Xu hướng</span>
                            </div>
                            <p class="stat-card-label">Thể loại phổ biến nhất</p>
                            <p class="stat-card-value genre-stat-value-text"><?php echo admin_escape($popularGenre ? $popularGenre["ten_theloai"] : "Chưa có dữ liệu"); ?></p>
                            <p class="stat-card-meta">
                                <?php echo $popularGenre ? admin_escape(admin_format_number($popularGenre["movie_total"]) . " phim đang được gắn.") : "Chưa có mapping thể loại - phim."; ?>
                            </p>
                        </article>
                    </section>

                    <section class="genre-table-card">
                        <div class="genre-table-head">
                            <div>
                                <h2>Danh sách thể loại</h2>
                                <p><?php echo $keyword !== "" ? "Kết quả tìm kiếm theo từ khóa hiện tại." : "Toàn bộ thể loại đang hiển thị trên hệ thống."; ?></p>
                            </div>

                            <?php if ($keyword !== "") { ?>
                                <a class="admin-secondary-btn" href="index.php?page_layout=list_theloai">Xóa bộ lọc</a>
                            <?php } ?>
                        </div>

                        <?php if (empty($genres)) { ?>
                            <div class="admin-empty-state genre-empty-state">
                                <strong>Chưa có thể loại phù hợp để hiển thị.</strong>
                                <p><?php echo $keyword !== "" ? "Hãy thử từ khóa khác hoặc thêm một thể loại mới." : "Bắt đầu bằng cách tạo thể loại đầu tiên cho kho phim."; ?></p>
                                <a class="admin-primary-btn" href="<?php echo admin_escape(admin_genre_list_url(array("keyword" => $keyword, "drawer" => "add"))); ?>">Mở form thêm thể loại</a>
                            </div>
                        <?php } else { ?>
                            <div class="genre-table-scroll">
                                <table class="genre-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Tên thể loại</th>
                                            <th>Số lượng phim</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($genres as $genre) { ?>
                                            <?php $genreColor = admin_genre_color($genre["theloai_id"]); ?>
                                            <tr>
                                                <td class="genre-id-cell">#G-<?php echo admin_escape($genre["theloai_id"]); ?></td>
                                                <td>
                                                    <div class="genre-name-cell">
                                                        <span class="genre-dot" style="background-color: <?php echo admin_escape($genreColor); ?>;"></span>
                                                        <span><?php echo admin_escape($genre["ten_theloai"]); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="genre-count-pill"><?php echo admin_escape(admin_format_number($genre["movie_total"])); ?></span>
                                                </td>
                                                <td>
                                                    <div class="genre-actions">
                                                        <a class="admin-secondary-btn genre-action-btn" href="<?php echo admin_escape(admin_genre_list_url(array("keyword" => $keyword, "drawer" => "edit", "id" => (int) $genre["theloai_id"]))); ?>">Sửa</a>
                                                        <a class="admin-secondary-btn genre-action-btn genre-action-btn-danger" href="xuly_xoatheloai.php?id=<?php echo (int) $genre["theloai_id"]; ?>" data-genre-name="<?php echo admin_escape($genre["ten_theloai"]); ?>">Xóa</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="movie-table-footer genre-table-footer">
                                <p>
                                    <?php
                                    if ($filteredTotal > 0) {
                                        echo admin_escape("Hiển thị 1 - " . $filteredTotal . " trong tổng số " . $totalGenres . " thể loại");
                                    } else {
                                        echo "Hiển thị 0 thể loại";
                                    }
                                    ?>
                                </p>
                            </div>
                        <?php } ?>
                    </section>
                </div>

                <?php if ($drawerOpen) { ?>
                    <aside class="genre-drawer">
                        <div class="genre-drawer-header">
                            <div>
                                <h2><?php echo $drawerMode === "edit" ? "Sửa thể loại" : "Thêm thể loại"; ?></h2>
                                <p><?php echo $drawerMode === "edit" ? "Cập nhật tên thể loại mà không thay đổi cấu trúc dữ liệu." : "Tạo một thể loại mới cho hệ thống phân loại phim."; ?></p>
                            </div>
                            <a class="genre-drawer-close" href="<?php echo admin_escape(admin_genre_list_url(array("keyword" => $keyword))); ?>" aria-label="Đóng drawer">&#10005;</a>
                        </div>

                        <form class="genre-drawer-form" action="<?php echo $drawerMode === "edit" ? "xulycapnhattheloai.php?id=" . (int) $editingGenre["theloai_id"] : "xuly_themtheloai.php"; ?>" method="post">
                            <label class="admin-field">
                                <span>TÊN THỂ LOẠI</span>
                                <?php if ($drawerMode === "edit") { ?>
                                    <input type="text" name="txthoten" id="genre-name-input" value="<?php echo admin_escape($editingGenre["ten_theloai"]); ?>" placeholder="Nhập tên thể loại..." required>
                                <?php } else { ?>
                                    <input type="text" name="txtTenQuocGia" id="genre-name-input" value="" placeholder="Nhập tên thể loại..." required>
                                <?php } ?>
                            </label>

                            <label class="admin-field">
                                <span>ĐƯỜNG DẪN THÂN THIỆN (SLUG)</span>
                                <div class="genre-slug-preview">
                                    <span>/genre/</span>
                                    <input type="text" id="genre-slug-preview" value="<?php echo admin_escape(admin_slugify_preview($slugSeed)); ?>" readonly>
                                </div>
                                <small class="genre-helper-text">Slug hiện chỉ là bản xem trước trên UI, chưa lưu vào database.</small>
                            </label>

                            <div class="admin-field">
                                <span>MÀU SẮC ĐỊNH DANH</span>
                                <div class="color-picker-row" id="color-picker-row" data-selected-color="<?php echo admin_escape($selectedColor); ?>">
                                    <?php foreach ($colorOptions as $colorValue) { ?>
                                        <button class="color-swatch<?php echo strtolower($selectedColor) === strtolower($colorValue) ? " is-selected" : ""; ?>" type="button" data-color="<?php echo admin_escape($colorValue); ?>" style="--swatch-color: <?php echo admin_escape($colorValue); ?>;" aria-label="Chọn màu <?php echo admin_escape($colorValue); ?>" aria-pressed="<?php echo strtolower($selectedColor) === strtolower($colorValue) ? "true" : "false"; ?>"></button>
                                    <?php } ?>
                                </div>
                                <small class="genre-helper-text">Màu chỉ dùng để render giao diện, không lưu vào bảng `genres`.</small>
                            </div>

                            <label class="admin-field">
                                <span>MÔ TẢ CHI TIẾT</span>
                                <textarea placeholder="Nhập mô tả cho thể loại này..." rows="5" disabled></textarea>
                                <small class="genre-helper-text">Trường mô tả đang ở chế độ preview vì schema hiện tại chưa có cột description.</small>
                            </label>

                            <div class="genre-info-box">
                                <span class="genre-info-icon">i</span>
                                <p>Thể loại mới sẽ hiển thị ngay lập tức trên trang chủ và trang lọc phim sau khi nhấn lưu.</p>
                            </div>

                            <div class="drawer-footer">
                                <a class="admin-secondary-btn genre-drawer-cancel" href="<?php echo admin_escape(admin_genre_list_url(array("keyword" => $keyword))); ?>">Hủy</a>
                                <?php if ($drawerMode === "edit") { ?>
                                    <button class="admin-primary-btn" type="submit" name="btncapnhat">Lưu thay đổi</button>
                                <?php } else { ?>
                                    <button class="admin-primary-btn" type="submit" name="btnThem">Lưu thay đổi</button>
                                <?php } ?>
                            </div>
                        </form>
                    </aside>
                <?php } ?>
            </div>
        </main>
    </div>

    <script>
    (function () {
        var nameInput = document.getElementById('genre-name-input');
        var slugPreview = document.getElementById('genre-slug-preview');
        var deleteLinks = document.querySelectorAll('.genre-action-btn-danger');
        var swatches = document.querySelectorAll('.color-swatch');

        function slugify(value) {
            return value
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/đ/g, 'd')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '') || 'the-loai-phim';
        }

        if (nameInput && slugPreview) {
            var syncSlug = function () {
                slugPreview.value = slugify(nameInput.value);
            };

            syncSlug();
            nameInput.addEventListener('input', syncSlug);
        }

        if (swatches.length) {
            swatches.forEach(function (button) {
                button.addEventListener('click', function () {
                    swatches.forEach(function (item) {
                        item.classList.remove('is-selected');
                        item.setAttribute('aria-pressed', 'false');
                    });

                    button.classList.add('is-selected');
                    button.setAttribute('aria-pressed', 'true');
                });
            });
        }

        if (deleteLinks.length) {
            deleteLinks.forEach(function (link) {
                link.addEventListener('click', function (event) {
                    var genreName = link.getAttribute('data-genre-name') || 'thể loại này';
                    if (!window.confirm('Bạn có chắc chắn muốn xóa "' + genreName + '" không?')) {
                        event.preventDefault();
                    }
                });
            });
        }
    })();
    </script>
</body>
</html>
