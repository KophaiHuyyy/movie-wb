<?php
include_once "checkpermission.php";

$currentPage = isset($_GET['page_layout']) ? trim((string) $_GET['page_layout']) : "";

if ($currentPage !== "" && $currentPage !== "dashboard") {
    include_once "doPage.php";
    exit();
}

include_once "cauhinh.php";

function admin_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function admin_fetch_scalar($connect, $sql, $field, $default = 0)
{
    $result = $connect->query($sql);
    if (!$result) {
        return $default;
    }

    $row = $result->fetch_assoc();
    $result->free();

    if (!$row || !isset($row[$field]) || $row[$field] === null) {
        return $default;
    }

    return $row[$field];
}

function admin_fetch_rows($connect, $sql)
{
    $rows = array();
    $result = $connect->query($sql);

    if (!$result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $result->free();

    return $rows;
}

function admin_format_number($value)
{
    return number_format((float) $value, 0, ",", ".");
}

function admin_format_compact($value)
{
    $number = (float) $value;

    if ($number >= 1000000) {
        return rtrim(rtrim(number_format($number / 1000000, 1, ".", ""), "0"), ".") . "M";
    }

    if ($number >= 1000) {
        return rtrim(rtrim(number_format($number / 1000, 1, ".", ""), "0"), ".") . "K";
    }

    return admin_format_number($number);
}

function admin_format_date($value)
{
    if (empty($value)) {
        return "Chua cap nhat";
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return "Chua cap nhat";
    }

    return date("d/m/Y", $timestamp);
}

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

$adminName = !empty($_SESSION["fullname"]) ? $_SESSION["fullname"] : (!empty($_SESSION["username"]) ? $_SESSION["username"] : "Admin");
$adminRole = !empty($_SESSION["role"]) && (int) $_SESSION["role"] === 1 ? "Administrator" : "Moderator";

$stats = array(
    "movies" => (int) admin_fetch_scalar($connect, "SELECT COUNT(*) AS total FROM movies", "total", 0),
    "users" => (int) admin_fetch_scalar($connect, "SELECT COUNT(*) AS total FROM users", "total", 0),
    "genres" => (int) admin_fetch_scalar($connect, "SELECT COUNT(*) AS total FROM genres", "total", 0),
    "countries" => (int) admin_fetch_scalar($connect, "SELECT COUNT(*) AS total FROM country", "total", 0),
    "views" => (int) admin_fetch_scalar($connect, "SELECT COALESCE(SUM(view), 0) AS total FROM movies", "total", 0),
    "reviews" => (int) admin_fetch_scalar($connect, "SELECT COUNT(*) AS total FROM reviews", "total", 0),
    "watchlist" => (int) admin_fetch_scalar($connect, "SELECT COUNT(*) AS total FROM watchlist", "total", 0),
);

$topMovies = admin_fetch_rows(
    $connect,
    "SELECT m.movie_id, m.title, m.release_year, m.language, m.img, COALESCE(m.view, 0) AS total_views,
            COALESCE(c.country_name, 'Chua ro') AS country_name
     FROM movies m
     LEFT JOIN country c ON m.country_id = c.country_id
     ORDER BY COALESCE(m.view, 0) DESC, m.movie_id DESC
     LIMIT 5"
);

$recentMovies = admin_fetch_rows(
    $connect,
    "SELECT m.movie_id, m.title, m.release_year, m.language, m.img, m.date_add,
            COALESCE(c.country_name, 'Chua ro') AS country_name,
            (
                SELECT GROUP_CONCAT(g.ten_theloai SEPARATOR ', ')
                FROM movie_genre mg
                INNER JOIN genres g ON mg.theloai_id = g.theloai_id
                WHERE mg.movie_id = m.movie_id
            ) AS genre_names
     FROM movies m
     LEFT JOIN country c ON m.country_id = c.country_id
     ORDER BY COALESCE(m.date_add, '1000-01-01') DESC, m.movie_id DESC
     LIMIT 4"
);

$recentReviews = admin_fetch_rows(
    $connect,
    "SELECT r.review_id, r.rating, r.comment, r.review_date,
            COALESCE(u.fullname, u.username, 'Nguoi dung') AS reviewer_name,
            COALESCE(m.title, 'Phim khong xac dinh') AS movie_title
     FROM reviews r
     LEFT JOIN users u ON r.user_id = u.user_id
     LEFT JOIN movies m ON r.movie_id = m.movie_id
     ORDER BY COALESCE(r.review_date, '1000-01-01 00:00:00') DESC, r.review_id DESC
     LIMIT 3"
);

$watchlistHighlights = admin_fetch_rows(
    $connect,
    "SELECT m.movie_id, m.title, m.img, COUNT(*) AS save_total
     FROM watchlist w
     INNER JOIN movies m ON w.movie_id = m.movie_id
     GROUP BY m.movie_id, m.title, m.img
     ORDER BY save_total DESC, m.movie_id DESC
     LIMIT 3"
);

$chartPoints = array(18, 30, 46, 38, 24, 63, 56);
$chartLabels = array("T2", "T3", "T4", "T5", "T6", "T7", "CN");
$chartPath = "";
$chartArea = "";
$pointSpots = array();
$pointCount = count($chartPoints);

if ($pointCount > 0) {
    $xStep = $pointCount > 1 ? 100 / ($pointCount - 1) : 100;
    $lineSegments = array();

    for ($i = 0; $i < $pointCount; $i++) {
        $x = $i * $xStep;
        $y = 100 - $chartPoints[$i];
        $lineSegments[] = ($i === 0 ? "M" : "L") . " " . number_format($x, 2, ".", "") . " " . number_format($y, 2, ".", "");
        $pointSpots[] = array("x" => $x, "y" => $y, "value" => $chartPoints[$i], "label" => $chartLabels[$i]);
    }

    $chartPath = implode(" ", $lineSegments);
    $chartArea = $chartPath . " L 100 100 L 0 100 Z";
}

$activityItems = array(
    array(
        "label" => "Kho phim",
        "text" => "He thong dang luu " . admin_format_number($stats["movies"]) . " bo phim, du lieu duoc dong bo tu bang movies.",
        "meta" => "Cap nhat hien tai",
    ),
    array(
        "label" => "Nguoi dung",
        "text" => "Tong cong " . admin_format_number($stats["users"]) . " tai khoan da duoc quan ly trong khu admin.",
        "meta" => "Theo bang users",
    ),
    array(
        "label" => "Danh gia",
        "text" => "Da ghi nhan " . admin_format_number($stats["reviews"]) . " danh gia, co the theo doi nhanh o panel ben phai.",
        "meta" => "Tong hop tu reviews",
    ),
    array(
        "label" => "Watchlist",
        "text" => "Nguoi dung da luu " . admin_format_number($stats["watchlist"]) . " muc vao danh sach xem sau.",
        "meta" => "Tong hop tu watchlist",
    ),
);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITMOVIES Admin Dashboard</title>
    <link rel="stylesheet" href="style_admin.css">
</head>
<body>
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <div class="admin-brand">
                <div class="admin-brand-mark">IT</div>
                <div>
                    <p class="admin-brand-title">ITMOVIES Admin</p>
                    <p class="admin-brand-subtitle">Cinematic control room</p>
                </div>
            </div>

            <nav class="admin-nav">
                <a class="admin-nav-item is-active" href="index.php">
                    <span class="admin-nav-icon">&#9638;</span>
                    <span>Tổng quan</span>
                </a>
                <a class="admin-nav-item" href="index.php?page_layout=listfilm">
                    <span class="admin-nav-icon">&#127909;</span>
                    <span>Quản lý phim</span>
                </a>
                <a class="admin-nav-item" href="index.php?page_layout=themmoi_film">
                    <span class="admin-nav-icon">+</span>
                    <span>Thêm phim</span>
                </a>
                <a class="admin-nav-item" href="index.php?page_layout=list_theloai">
                    <span class="admin-nav-icon">&#9673;</span>
                    <span>Quản lý thể loại</span>
                </a>
                <a class="admin-nav-item" href="index.php?page_layout=list_theloai_phim">
                    <span class="admin-nav-icon">&#8644;</span>
                    <span>Gán thể loại</span>
                </a>
                <a class="admin-nav-item" href="index.php?page_layout=themquocgia">
                    <span class="admin-nav-icon">&#127760;</span>
                    <span>Quản lý quốc gia</span>
                </a>
                <a class="admin-nav-item" href="index.php?page_layout=list_user">
                    <span class="admin-nav-icon">&#128101;</span>
                    <span>Quản lý tài khoản</span>
                </a>
                <a class="admin-nav-item" href="#latest-reviews">
                    <span class="admin-nav-icon">&#9733;</span>
                    <span>Quản lý đánh giá</span>
                </a>
                <a class="admin-nav-item" href="#watchlist-overview">
                    <span class="admin-nav-icon">&#9825;</span>
                    <span>Watchlist</span>
                </a>
            </nav>

            <a class="admin-logout" href="logout.php" onclick="return confirm('Bạn có chắc chắn muốn đăng xuất không?')">
                <span class="admin-nav-icon">&#10162;</span>
                <span>Đăng xuất</span>
            </a>
        </aside>

        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-header-copy">
                    <p class="admin-kicker">Admin dashboard overview</p>
                    <h1>Tổng quan hệ thống</h1>
                    <p class="admin-subtitle">Theo dõi nhanh hoạt động của website phim.</p>
                </div>

                <div class="admin-header-actions">
                    <form class="admin-search" action="index.php" method="get">
                        <input type="hidden" name="page_layout" value="listfilm">
                        <span class="admin-search-icon">&#9906;</span>
                        <input type="search" name="search" placeholder="Tìm kiếm phim, người dùng, thể loại...">
                    </form>

                    <a class="admin-primary-btn" href="index.php?page_layout=themmoi_film">Thêm phim mới</a>

                    <div class="admin-profile">
                        <div class="admin-avatar"><?php echo admin_escape(admin_initials($adminName)); ?></div>
                        <div>
                            <p class="admin-profile-name"><?php echo admin_escape($adminName); ?></p>
                            <p class="admin-profile-role"><?php echo admin_escape($adminRole); ?></p>
                        </div>
                    </div>
                </div>
            </header>

            <section class="admin-stats-grid">
                <article class="stat-card stat-card-accent-red">
                    <div class="stat-card-top">
                        <span class="stat-card-icon">&#127916;</span>
                        <span class="stat-card-trend">Kho phim</span>
                    </div>
                    <p class="stat-card-label">Tổng số phim</p>
                    <p class="stat-card-value"><?php echo admin_escape(admin_format_compact($stats["movies"])); ?></p>
                    <p class="stat-card-meta">Từ bảng movies</p>
                </article>

                <article class="stat-card stat-card-accent-blue">
                    <div class="stat-card-top">
                        <span class="stat-card-icon">&#128100;</span>
                        <span class="stat-card-trend">Tài khoản</span>
                    </div>
                    <p class="stat-card-label">Tổng người dùng</p>
                    <p class="stat-card-value"><?php echo admin_escape(admin_format_compact($stats["users"])); ?></p>
                    <p class="stat-card-meta">Bao gồm user và admin</p>
                </article>

                <article class="stat-card stat-card-accent-amber">
                    <div class="stat-card-top">
                        <span class="stat-card-icon">&#9635;</span>
                        <span class="stat-card-trend">Danh mục</span>
                    </div>
                    <p class="stat-card-label">Tổng thể loại</p>
                    <p class="stat-card-value"><?php echo admin_escape(admin_format_compact($stats["genres"])); ?></p>
                    <p class="stat-card-meta">Phục vụ mapping phim</p>
                </article>

                <article class="stat-card stat-card-accent-neutral">
                    <div class="stat-card-top">
                        <span class="stat-card-icon">&#127758;</span>
                        <span class="stat-card-trend">Khu vực</span>
                    </div>
                    <p class="stat-card-label">Tổng quốc gia</p>
                    <p class="stat-card-value"><?php echo admin_escape(admin_format_compact($stats["countries"])); ?></p>
                    <p class="stat-card-meta">Danh sách country</p>
                </article>

                <article class="stat-card stat-card-accent-green">
                    <div class="stat-card-top">
                        <span class="stat-card-icon">&#128065;</span>
                        <span class="stat-card-trend">Tương tác</span>
                    </div>
                    <p class="stat-card-label">Tổng lượt xem</p>
                    <p class="stat-card-value"><?php echo admin_escape(admin_format_compact($stats["views"])); ?></p>
                    <p class="stat-card-meta">Cộng từ cột view</p>
                </article>

                <article class="stat-card stat-card-accent-rose">
                    <div class="stat-card-top">
                        <span class="stat-card-icon">&#9733;</span>
                        <span class="stat-card-trend">Phản hồi</span>
                    </div>
                    <p class="stat-card-label">Tổng đánh giá</p>
                    <p class="stat-card-value"><?php echo admin_escape(admin_format_compact($stats["reviews"])); ?></p>
                    <p class="stat-card-meta">Từ bảng reviews</p>
                </article>
            </section>

            <section class="admin-dashboard-grid">
                <div class="admin-dashboard-left">
                    <section class="admin-card admin-chart-card">
                        <div class="admin-card-head">
                            <div>
                                <h2>Lượt xem theo thời gian</h2>
                                <p>Mô phỏng nhịp tăng trưởng lượt xem để theo dõi nhanh sức hút của kho phim.</p>
                            </div>

                            <div class="admin-filter-pills">
                                <button class="is-active" type="button">7 ngày</button>
                                <button type="button">30 ngày</button>
                                <button type="button">12 tháng</button>
                            </div>
                        </div>

                        <div class="admin-chart-wrap" aria-hidden="true">
                            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="admin-chart-svg">
                                <defs>
                                    <linearGradient id="adminChartArea" x1="0%" y1="0%" x2="0%" y2="100%">
                                        <stop offset="0%" stop-color="rgba(229, 9, 20, 0.45)"></stop>
                                        <stop offset="100%" stop-color="rgba(229, 9, 20, 0)"></stop>
                                    </linearGradient>
                                </defs>
                                <path class="admin-chart-grid-line" d="M 0 82 L 100 82"></path>
                                <path class="admin-chart-area" d="<?php echo admin_escape($chartArea); ?>"></path>
                                <path class="admin-chart-line" d="<?php echo admin_escape($chartPath); ?>"></path>
                                <?php foreach ($pointSpots as $spot) { ?>
                                    <circle class="admin-chart-point" cx="<?php echo admin_escape(number_format($spot["x"], 2, ".", "")); ?>" cy="<?php echo admin_escape(number_format($spot["y"], 2, ".", "")); ?>" r="1.5"></circle>
                                <?php } ?>
                            </svg>

                            <div class="admin-chart-labels">
                                <?php foreach ($chartLabels as $label) { ?>
                                    <span><?php echo admin_escape($label); ?></span>
                                <?php } ?>
                            </div>
                        </div>
                    </section>

                    <section class="admin-card" id="top-movies">
                        <div class="admin-card-head">
                            <div>
                                <h2>Phim xem nhiều nhất</h2>
                                <p>Top phim theo dữ liệu thật từ cột view trong bảng movies.</p>
                            </div>
                            <a class="admin-inline-link" href="index.php?page_layout=listfilm">Mở danh sách phim</a>
                        </div>

                        <div class="movie-list">
                            <?php if (empty($topMovies)) { ?>
                                <div class="admin-empty-state">Chưa có dữ liệu phim để hiển thị.</div>
                            <?php } else { ?>
                                <?php foreach ($topMovies as $movie) { ?>
                                    <?php
                                    $posterPath = admin_movie_poster(isset($movie["img"]) ? $movie["img"] : "");
                                    ?>
                                    <article class="movie-row">
                                        <div class="movie-row-poster">
                                            <?php if ($posterPath !== "") { ?>
                                                <img src="<?php echo admin_escape($posterPath); ?>" alt="<?php echo admin_escape($movie["title"]); ?>">
                                            <?php } else { ?>
                                                <div class="movie-row-fallback"><?php echo admin_escape(admin_initials($movie["title"])); ?></div>
                                            <?php } ?>
                                        </div>

                                        <div class="movie-row-copy">
                                            <div class="movie-row-main">
                                                <h3><?php echo admin_escape($movie["title"]); ?></h3>
                                                <p><?php echo admin_escape($movie["release_year"] !== null ? $movie["release_year"] : "Chua ro"); ?>, <?php echo admin_escape($movie["country_name"]); ?></p>
                                            </div>

                                            <div class="movie-row-meta">
                                                <span class="admin-badge admin-badge-red"><?php echo admin_escape(admin_format_number($movie["total_views"])); ?> lượt xem</span>
                                                <span class="admin-badge"><?php echo admin_escape(!empty($movie["language"]) ? $movie["language"] : "Dang cap nhat"); ?></span>
                                            </div>
                                        </div>

                                        <div class="movie-row-actions">
                                            <a class="admin-secondary-btn" href="index.php?page_layout=capnhatfilm&id=<?php echo (int) $movie["movie_id"]; ?>">Sửa</a>
                                        </div>
                                    </article>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </section>

                    <section class="admin-card" id="recent-movies-section">
                        <div class="admin-card-head">
                            <div>
                                <h2>Phim mới thêm gần đây</h2>
                                <p>Danh sách lấy theo `date_add`, ưu tiên poster và metadata chính.</p>
                            </div>
                            <a class="admin-inline-link" href="index.php?page_layout=themmoi_film">Thêm phim</a>
                        </div>

                        <div class="recent-movies-grid">
                            <?php if (empty($recentMovies)) { ?>
                                <div class="admin-empty-state">Chưa có phim mới để tổng hợp.</div>
                            <?php } else { ?>
                                <?php foreach ($recentMovies as $movie) { ?>
                                    <?php
                                    $posterPath = admin_movie_poster(isset($movie["img"]) ? $movie["img"] : "");
                                    $genreText = !empty($movie["genre_names"]) ? $movie["genre_names"] : "Chua gan the loai";
                                    ?>
                                    <article class="recent-movie-card">
                                        <div class="recent-movie-poster">
                                            <?php if ($posterPath !== "") { ?>
                                                <img src="<?php echo admin_escape($posterPath); ?>" alt="<?php echo admin_escape($movie["title"]); ?>">
                                            <?php } else { ?>
                                                <div class="movie-row-fallback"><?php echo admin_escape(admin_initials($movie["title"])); ?></div>
                                            <?php } ?>
                                        </div>
                                        <div class="recent-movie-copy">
                                            <div class="recent-movie-heading">
                                                <h3><?php echo admin_escape($movie["title"]); ?></h3>
                                                <span class="admin-badge admin-badge-soft"><?php echo admin_escape(admin_format_date($movie["date_add"])); ?></span>
                                            </div>
                                            <p><?php echo admin_escape($genreText); ?></p>
                                            <div class="recent-movie-meta">
                                                <span><?php echo admin_escape($movie["release_year"] !== null ? $movie["release_year"] : "Chua ro"); ?></span>
                                                <span><?php echo admin_escape(!empty($movie["language"]) ? $movie["language"] : "Dang cap nhat"); ?></span>
                                                <span><?php echo admin_escape($movie["country_name"]); ?></span>
                                            </div>
                                        </div>
                                    </article>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </section>
                </div>

                <aside class="admin-dashboard-right">
                    <section class="admin-card">
                        <div class="admin-card-head">
                            <div>
                                <h2>Hoạt động gần đây</h2>
                                <p>Snapshot nhanh dựa trên dữ liệu tổng quan hiện có.</p>
                            </div>
                        </div>

                        <div class="activity-list">
                            <?php foreach ($activityItems as $activity) { ?>
                                <article class="activity-item">
                                    <span class="activity-dot"></span>
                                    <div>
                                        <p class="activity-label"><?php echo admin_escape($activity["label"]); ?></p>
                                        <p class="activity-text"><?php echo admin_escape($activity["text"]); ?></p>
                                        <p class="activity-meta"><?php echo admin_escape($activity["meta"]); ?></p>
                                    </div>
                                </article>
                            <?php } ?>
                        </div>
                    </section>

                    <section class="admin-card" id="latest-reviews">
                        <div class="admin-card-head">
                            <div>
                                <h2>Đánh giá mới nhất</h2>
                                <p>Lấy từ bảng reviews, join với users và movies nếu có dữ liệu.</p>
                            </div>
                        </div>

                        <div class="review-list">
                            <?php if (empty($recentReviews)) { ?>
                                <div class="admin-empty-state">Chưa có đánh giá mới để hiển thị.</div>
                            <?php } else { ?>
                                <?php foreach ($recentReviews as $review) { ?>
                                    <article class="review-item">
                                        <div class="review-avatar"><?php echo admin_escape(admin_initials($review["reviewer_name"])); ?></div>
                                        <div class="review-copy">
                                            <div class="review-head">
                                                <h3><?php echo admin_escape($review["reviewer_name"]); ?></h3>
                                                <span><?php echo admin_escape(admin_format_date($review["review_date"])); ?></span>
                                            </div>
                                            <div class="review-stars">
                                                <?php
                                                $rating = isset($review["rating"]) ? (int) $review["rating"] : 0;
                                                for ($star = 1; $star <= 5; $star++) {
                                                    echo $star <= $rating ? "&#9733;" : "&#9734;";
                                                }
                                                ?>
                                            </div>
                                            <p><?php echo admin_escape(!empty($review["comment"]) ? $review["comment"] : "Đánh giá chưa có nội dung bình luận."); ?></p>
                                            <span class="review-movie"><?php echo admin_escape($review["movie_title"]); ?></span>
                                        </div>
                                    </article>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </section>

                    <section class="admin-card" id="watchlist-overview">
                        <div class="admin-card-head">
                            <div>
                                <h2>Thao tác nhanh</h2>
                                <p>Giữ nguyên các route admin đang có, không đổi flow xử lý cũ.</p>
                            </div>
                        </div>

                        <div class="quick-action-grid">
                            <a class="quick-action-btn" href="index.php?page_layout=themmoi_film">Thêm phim</a>
                            <a class="quick-action-btn" href="index.php?page_layout=themtheloai">Thêm thể loại</a>
                            <a class="quick-action-btn" href="index.php?page_layout=themquocgia">Thêm quốc gia</a>
                            <a class="quick-action-btn" href="index.php?page_layout=list_user">Quản lý người dùng</a>
                            <a class="quick-action-btn" href="#latest-reviews">Xem đánh giá</a>
                        </div>

                        <div class="watchlist-panel">
                            <div class="watchlist-summary">
                                <p class="watchlist-summary-label">Tổng mục xem sau</p>
                                <p class="watchlist-summary-value"><?php echo admin_escape(admin_format_compact($stats["watchlist"])); ?></p>
                            </div>

                            <div class="watchlist-list">
                                <?php if (empty($watchlistHighlights)) { ?>
                                    <div class="admin-empty-state admin-empty-state-compact">Watchlist hiện chưa có dữ liệu nổi bật.</div>
                                <?php } else { ?>
                                    <?php foreach ($watchlistHighlights as $movie) { ?>
                                        <div class="watchlist-item">
                                            <span class="watchlist-item-name"><?php echo admin_escape($movie["title"]); ?></span>
                                            <span class="watchlist-item-count"><?php echo admin_escape(admin_format_number($movie["save_total"])); ?></span>
                                        </div>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        </div>
                    </section>
                </aside>
            </section>
        </main>
    </div>
</body>
</html>
