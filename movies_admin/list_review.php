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
            return true;
        }

        $bindValues = array($types);
        foreach ($params as $index => $value) {
            $bindValues[] = &$params[$index];
        }

        return call_user_func_array(array($statement, "bind_param"), $bindValues);
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

if (!function_exists("admin_fetch_one_prepared")) {
    function admin_fetch_one_prepared($connect, $sql, $types = "", $params = array())
    {
        $rows = admin_fetch_rows_prepared($connect, $sql, $types, $params);
        return !empty($rows) ? $rows[0] : null;
    }
}

if (!function_exists("admin_fetch_scalar_prepared")) {
    function admin_fetch_scalar_prepared($connect, $sql, $field, $types = "", $params = array(), $default = 0)
    {
        $row = admin_fetch_one_prepared($connect, $sql, $types, $params);
        if (!$row || !isset($row[$field]) || $row[$field] === null) {
            return $default;
        }

        return $row[$field];
    }
}

if (!function_exists("admin_format_number")) {
    function admin_format_number($value)
    {
        return number_format((float) $value, 0, ",", ".");
    }
}

if (!function_exists("admin_format_decimal")) {
    function admin_format_decimal($value)
    {
        return number_format((float) $value, 1, ".", "");
    }
}

if (!function_exists("admin_format_review_date")) {
    function admin_format_review_date($value)
    {
        if (empty($value)) {
            return "—";
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return "—";
        }

        return date("d/m/Y H:i", $timestamp);
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

if (!function_exists("admin_render_rating_stars")) {
    function admin_render_rating_stars($rating)
    {
        $rating = max(0, min(5, (int) $rating));
        $markup = "";

        for ($star = 1; $star <= 5; $star++) {
            $markup .= $star <= $rating ? "&#9733;" : "&#9734;";
        }

        return $markup;
    }
}

if (!function_exists("admin_review_url")) {
    function admin_review_url($params = array())
    {
        $query = array_merge(array("page_layout" => "list_review"), $params);

        foreach ($query as $key => $value) {
            if ($value === "" || $value === null || $value === "all") {
                unset($query[$key]);
            }
        }

        return "index.php?" . http_build_query($query);
    }
}

if (!function_exists("admin_review_comment_excerpt")) {
    function admin_review_comment_excerpt($comment, $limit = 110)
    {
        $comment = trim((string) $comment);
        if ($comment === "") {
            return "Danh gia chua co noi dung binh luan.";
        }

        if (function_exists("mb_strlen") && function_exists("mb_substr")) {
            if (mb_strlen($comment, "UTF-8") <= $limit) {
                return $comment;
            }

            return rtrim(mb_substr($comment, 0, $limit, "UTF-8")) . "...";
        }

        if (strlen($comment) <= $limit) {
            return $comment;
        }

        return rtrim(substr($comment, 0, $limit)) . "...";
    }
}

$adminName = !empty($_SESSION["fullname"]) ? $_SESSION["fullname"] : (!empty($_SESSION["username"]) ? $_SESSION["username"] : "Admin");
$keyword = isset($_GET["keyword"]) ? trim((string) $_GET["keyword"]) : "";
$ratingFilter = isset($_GET["rating"]) ? trim((string) $_GET["rating"]) : "all";
$statusFilter = isset($_GET["status"]) ? trim((string) $_GET["status"]) : "all";
$movieFilter = isset($_GET["movie_id"]) ? (int) $_GET["movie_id"] : 0;
$page = isset($_GET["page"]) ? max(1, (int) $_GET["page"]) : 1;
$perPage = 10;
$notice = isset($_GET["notice"]) ? trim((string) $_GET["notice"]) : "";

$allowedRatingFilters = array("all", "5", "4", "3");
if (!in_array($ratingFilter, $allowedRatingFilters, true)) {
    $ratingFilter = "all";
}

$allowedStatusFilters = array("all", "hien", "an");
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = "all";
}

$flashMessage = "";
if ($notice === "deleted") {
    $flashMessage = "Da xoa danh gia thanh cong.";
} elseif ($notice === "delete_failed") {
    $flashMessage = "Khong the xoa danh gia luc nay.";
} elseif ($notice === "hidden") {
    $flashMessage = "Da an binh luan khoi user site.";
} elseif ($notice === "shown") {
    $flashMessage = "Da hien lai binh luan tren user site.";
} elseif ($notice === "visibility_failed") {
    $flashMessage = "Khong the cap nhat trang thai hien/an luc nay.";
}

$movieOptions = admin_fetch_rows_prepared(
    $connect,
    "SELECT movie_id, title
     FROM movies
     ORDER BY title ASC"
);

$whereParts = array();
$types = "";
$params = array();

if ($keyword !== "") {
    $whereParts[] = "(m.title LIKE ? OR u.fullname LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR r.comment LIKE ?)";
    $searchValue = "%" . $keyword . "%";
    $types .= "sssss";
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($movieFilter > 0) {
    $whereParts[] = "r.movie_id = ?";
    $types .= "i";
    $params[] = $movieFilter;
}

if ($ratingFilter === "5") {
    $whereParts[] = "r.rating = ?";
    $types .= "i";
    $params[] = 5;
} elseif ($ratingFilter === "4") {
    $whereParts[] = "r.rating = ?";
    $types .= "i";
    $params[] = 4;
} elseif ($ratingFilter === "3") {
    $whereParts[] = "r.rating <= ?";
    $types .= "i";
    $params[] = 3;
}

if ($statusFilter === "hien") {
    $whereParts[] = "r.is_hidden = ?";
    $types .= "i";
    $params[] = 0;
} elseif ($statusFilter === "an") {
    $whereParts[] = "r.is_hidden = ?";
    $types .= "i";
    $params[] = 1;
}

$whereClause = !empty($whereParts) ? " WHERE " . implode(" AND ", $whereParts) : "";

$totalReviews = (int) admin_fetch_scalar_prepared(
    $connect,
    "SELECT COUNT(*) AS total FROM reviews",
    "total",
    "",
    array(),
    0
);

$visibleReviews = (int) admin_fetch_scalar_prepared(
    $connect,
    "SELECT COUNT(*) AS total FROM reviews WHERE is_hidden = 0",
    "total",
    "",
    array(),
    0
);

$hiddenReviews = (int) admin_fetch_scalar_prepared(
    $connect,
    "SELECT COUNT(*) AS total FROM reviews WHERE is_hidden = 1",
    "total",
    "",
    array(),
    0
);

$averageRating = admin_fetch_scalar_prepared(
    $connect,
    "SELECT AVG(rating) AS average_rating FROM reviews WHERE is_hidden = 0",
    "average_rating",
    "",
    array(),
    0
);
$averageRating = $averageRating !== 0 ? round((float) $averageRating, 1) : 0;

$filteredTotal = (int) admin_fetch_scalar_prepared(
    $connect,
    "SELECT COUNT(*) AS total
     FROM reviews r
     LEFT JOIN movies m ON r.movie_id = m.movie_id
     LEFT JOIN users u ON r.user_id = u.user_id" . $whereClause,
    "total",
    $types,
    $params,
    0
);

$totalPages = max(1, (int) ceil($filteredTotal / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;
$listTypes = $types . "ii";
$listParams = $params;
$listParams[] = $perPage;
$listParams[] = $offset;

$reviews = admin_fetch_rows_prepared(
    $connect,
    "SELECT
        r.review_id,
        r.rating,
        r.comment,
        r.review_date,
        r.is_hidden,
        r.movie_id,
        r.user_id,
        m.title AS movie_title,
        m.release_year,
        m.img,
        COALESCE(c.country_name, 'Dang cap nhat') AS country_name,
        COALESCE(u.fullname, u.username, 'Nguoi dung') AS reviewer_name,
        COALESCE(u.username, '') AS username,
        COALESCE(u.email, '') AS email,
        (
            SELECT GROUP_CONCAT(g.ten_theloai SEPARATOR ', ')
            FROM movie_genre mg
            INNER JOIN genres g ON mg.theloai_id = g.theloai_id
            WHERE mg.movie_id = m.movie_id
        ) AS genre_names
     FROM reviews r
     LEFT JOIN movies m ON r.movie_id = m.movie_id
     LEFT JOIN country c ON m.country_id = c.country_id
     LEFT JOIN users u ON r.user_id = u.user_id" . $whereClause . "
     ORDER BY COALESCE(r.review_date, '1000-01-01 00:00:00') DESC, r.review_id DESC
     LIMIT ? OFFSET ?",
    $listTypes,
    $listParams
);

$displayStart = $filteredTotal > 0 ? ($offset + 1) : 0;
$displayEnd = $filteredTotal > 0 ? min($filteredTotal, $offset + count($reviews)) : 0;
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý danh giá | ITMOVIES Admin</title>
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
                <a class="admin-nav-item is-active has-indicator" href="index.php?page_layout=list_review">
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
                    onclick="return confirm('Ban co chac chan muon dang xuat khong?')">
                    <span class="admin-nav-icon">&#10162;</span>
                    <span>Đăng xuất</span>
                </a>
            </div>
        </aside>

        <main class="admin-main review-page">
            <header class="admin-topbar review-topbar">
                <form class="admin-search admin-search-wide" action="index.php" method="get">
                    <input type="hidden" name="page_layout" value="list_review">
                    <?php if ($ratingFilter !== "all") { ?>
                    <input type="hidden" name="rating" value="<?php echo admin_escape($ratingFilter); ?>">
                    <?php } ?>
                    <?php if ($statusFilter !== "all") { ?>
                    <input type="hidden" name="status" value="<?php echo admin_escape($statusFilter); ?>">
                    <?php } ?>
                    <?php if ($movieFilter > 0) { ?>
                    <input type="hidden" name="movie_id" value="<?php echo (int) $movieFilter; ?>">
                    <?php } ?>
                    <span class="admin-search-icon">&#9906;</span>
                    <input type="search" name="keyword" value="<?php echo admin_escape($keyword); ?>"
                        placeholder="Tim kiem danh gia...">
                </form>

                <div class="admin-topbar-actions">
                    <button class="admin-icon-button" type="button" aria-label="Thong bao">&#128276;</button>
                    <button class="admin-icon-button" type="button" aria-label="Cai dat">&#9881;</button>
                    <div class="admin-profile">
                        <div class="admin-avatar"><?php echo admin_escape(admin_initials($adminName)); ?></div>
                        <div class="admin-profile-tooltip">
                            <p class="admin-profile-name"><?php echo admin_escape($adminName); ?></p>
                            <p class="admin-profile-role">Administrator</p>
                        </div>
                    </div>
                </div>
            </header>

            <section class="review-header">
                <div class="review-header-copy">
                    <p class="review-kicker">Review moderation</p>
                    <h1>Quản lý đánh giá</h1>
                    <p>Kiểm duyệt và quản lý các phản hồi từ người xem phim.</p>
                </div>

                <div class="review-header-actions">
                    <button class="admin-secondary-btn review-filter-toggle" type="button" data-review-filter-toggle>
                        <span>&#9776;</span>
                        <span>Bộ lọc</span>
                    </button>
                </div>
            </section>

            <section class="review-stats-grid">
                <article class="review-stat-card review-stat-card-rose admin-card">
                    <div class="review-stat-head">
                        <span class="review-stat-label">Tổng đánh giá</span>
                    </div>
                    <div class="review-stat-body">
                        <strong><?php echo admin_escape(admin_format_number($totalReviews)); ?></strong>
                        <small>Từ bảng reviews</small>
                    </div>
                </article>

                <article class="review-stat-card review-stat-card-amber admin-card">
                    <div class="review-stat-head">
                        <span class="review-stat-label">Đang hiển thị</span>
                    </div>
                    <div class="review-stat-body">
                        <strong><?php echo admin_escape(admin_format_number($visibleReviews)); ?></strong>
                        <small>Review công khai trên user site</small>
                    </div>
                </article>

                <article class="review-stat-card review-stat-card-gold admin-card">
                    <div class="review-stat-head">
                        <span class="review-stat-label">Đã ẩn</span>
                    </div>
                    <div class="review-stat-body">
                        <strong><?php echo admin_escape(admin_format_number($hiddenReviews)); ?></strong>
                        <small>Không xuất hiện trên user site</small>
                    </div>
                </article>

                <article class="review-stat-card review-stat-card-danger admin-card">
                    <div class="review-stat-head">
                        <span class="review-stat-label">Điểm trung bình</span>
                    </div>
                    <div class="review-stat-body">
                        <strong><?php echo $averageRating > 0 ? admin_escape(admin_format_decimal($averageRating)) : "0.0"; ?></strong>
                        <div class="rating-stars rating-stars-summary">
                            <?php echo admin_render_rating_stars((int) round($averageRating)); ?></div>
                    </div>
                </article>
            </section>

            <?php if ($flashMessage !== "") { ?>
            <div
                class="account-form-alert <?php echo in_array($notice, array("deleted", "hidden", "shown"), true) ? "account-form-alert-success" : "account-form-alert-error"; ?>">
                <?php echo admin_escape($flashMessage); ?>
            </div>
            <?php } ?>

            <section class="review-filter-bar admin-card" id="reviewFilterPanel">
                <form class="review-filter-form" action="index.php" method="get">
                    <input type="hidden" name="page_layout" value="list_review">
                    <?php if ($keyword !== "") { ?>
                    <input type="hidden" name="keyword" value="<?php echo admin_escape($keyword); ?>">
                    <?php } ?>

                    <label class="review-filter-select">
                        <span>Rating</span>
                        <select name="rating" onchange="this.form.submit()">
                            <option value="all" <?php echo $ratingFilter === "all" ? "selected" : ""; ?>>Tất cả</option>
                            <option value="5" <?php echo $ratingFilter === "5" ? "selected" : ""; ?>>5 sao</option>
                            <option value="4" <?php echo $ratingFilter === "4" ? "selected" : ""; ?>>4 sao</option>
                            <option value="3" <?php echo $ratingFilter === "3" ? "selected" : ""; ?>>3 sao tro xuong
                            </option>
                        </select>
                    </label>

                    <label class="review-filter-select">
                        <span>Trạng thái</span>
                        <select name="status" onchange="this.form.submit()">
                            <option value="all" <?php echo $statusFilter === "all" ? "selected" : ""; ?>>Tất cả</option>
                            <option value="hien" <?php echo $statusFilter === "hien" ? "selected" : ""; ?>>Hiện</option>
                            <option value="an" <?php echo $statusFilter === "an" ? "selected" : ""; ?>>Ẩn</option>
                        </select>
                    </label>

                    <label class="review-filter-select review-filter-select-wide">
                        <span>Phim</span>
                        <select name="movie_id" onchange="this.form.submit()">
                            <option value="0">Tất cả phim</option>
                            <?php foreach ($movieOptions as $movieOption) { ?>
                            <option value="<?php echo (int) $movieOption["movie_id"]; ?>"
                                <?php echo $movieFilter === (int) $movieOption["movie_id"] ? "selected" : ""; ?>>
                                <?php echo admin_escape($movieOption["title"]); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </label>

                </form>

                <div class="review-filter-meta">
                    <p>Trạng thái được lưu trữ trong cột <code>reviews.is_hidden</code>. Review bị ẩn vẫn tồn tại
                        trong DB nhưng không được tính vào danh sách và điểm trung bình công khai.</p>
                    <span>Hiển thị <?php echo admin_escape($displayStart); ?>-<?php echo admin_escape($displayEnd); ?>
                        trên <?php echo admin_escape(admin_format_number($filteredTotal)); ?> kết quả</span>
                </div>
            </section>

            <section class="review-table-card admin-card">
                <?php if (empty($reviews)) { ?>
                <div class="admin-empty-state">Không tìm thấy đánh giá phù hợp với bộ lọc hiện tại.</div>
                <?php } else { ?>
                <div class="review-table-scroll">
                    <table class="review-table">
                        <thead>
                            <tr>
                                <th>Phim</th>
                                <th>Người dùng</th>
                                <th>Đánh giá</th>
                                <th>Ý kiến</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $review) { ?>
                            <?php
                                    $isHidden = isset($review["is_hidden"]) && (int) $review["is_hidden"] === 1;
                                    $posterPath = admin_movie_poster(isset($review["img"]) ? $review["img"] : "");
                                    $reviewerName = !empty($review["reviewer_name"]) ? $review["reviewer_name"] : "Nguoi dung";
                                    $movieMeta = array();
                                    if (!empty($review["release_year"])) {
                                        $movieMeta[] = $review["release_year"];
                                    }
                                    if (!empty($review["country_name"])) {
                                        $movieMeta[] = $review["country_name"];
                                    }
                                    if (!empty($review["genre_names"])) {
                                        $movieMeta[] = $review["genre_names"];
                                    }
                                    $movieMetaText = !empty($movieMeta) ? implode(" • ", $movieMeta) : "Dang cap nhat";
                                    ?>
                            <tr>
                                <td>
                                    <div class="review-movie-cell">
                                        <div class="review-movie-poster">
                                            <?php if ($posterPath !== "") { ?>
                                            <img src="<?php echo admin_escape($posterPath); ?>"
                                                alt="<?php echo admin_escape($review["movie_title"]); ?>">
                                            <?php } else { ?>
                                            <div class="review-movie-fallback">
                                                <?php echo admin_escape(admin_initials($review["movie_title"])); ?>
                                            </div>
                                            <?php } ?>
                                        </div>
                                        <div class="review-movie-copy">
                                            <strong><?php echo admin_escape($review["movie_title"]); ?></strong>
                                            <small><?php echo admin_escape($movieMetaText); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="review-user-cell">
                                        <div class="review-user-avatar">
                                            <?php echo admin_escape(admin_initials($reviewerName)); ?></div>
                                        <div class="review-user-copy">
                                            <strong><?php echo admin_escape($reviewerName); ?></strong>
                                            <small><?php echo admin_escape($review["email"] !== "" ? $review["email"] : "@" . $review["username"]); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="review-rating-cell">
                                        <div class="rating-stars">
                                            <?php echo admin_render_rating_stars((int) $review["rating"]); ?></div>
                                        <strong><?php echo admin_escape((int) $review["rating"] . "/5"); ?></strong>
                                        <small><?php echo admin_escape(admin_format_review_date($review["review_date"])); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <p class="review-comment<?php echo $isHidden ? " is-hidden" : ""; ?>">
                                        <?php echo admin_escape(admin_review_comment_excerpt($review["comment"])); ?>
                                    </p>
                                </td>
                                <td>
                                    <span
                                        class="review-status-badge <?php echo $isHidden ? "is-hidden" : "is-visible"; ?>"><?php echo $isHidden ? "An" : "Hien"; ?></span>
                                </td>
                                <td>
                                    <div class="review-action-group">
                                        <a class="review-action-button <?php echo $isHidden ? "review-action-button-success" : "review-action-button-warning"; ?>"
                                            href="index.php?page_layout=xuly_anreview&id=<?php echo (int) $review["review_id"]; ?>&visibility=<?php echo $isHidden ? "hien" : "an"; ?>&keyword=<?php echo urlencode($keyword); ?>&rating=<?php echo urlencode($ratingFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&movie_id=<?php echo (int) $movieFilter; ?>&page=<?php echo (int) $page; ?>"
                                            onclick="return confirm('<?php echo $isHidden ? "Hien" : "An"; ?> binh luan nay?')"><?php echo $isHidden ? "&#10003;" : "&#128065;"; ?></a>
                                        <a class="review-action-button review-action-button-danger"
                                            href="index.php?page_layout=xuly_xoareview&id=<?php echo (int) $review["review_id"]; ?>&keyword=<?php echo urlencode($keyword); ?>&rating=<?php echo urlencode($ratingFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&movie_id=<?php echo (int) $movieFilter; ?>&page=<?php echo (int) $page; ?>"
                                            onclick="return confirm('Ban co chac chan muon xoa danh gia nay khong?')">&#128465;</a>
                                    </div>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>

                <div class="review-table-footer">
                    <p>Hiển thị <?php echo admin_escape($displayStart); ?>-<?php echo admin_escape($displayEnd); ?> trên
                        <?php echo admin_escape(admin_format_number($filteredTotal)); ?> kết quả</p>

                    <?php if ($totalPages > 1) { ?>
                    <nav class="review-pagination" aria-label="Điều hướng phân trang">
                        <?php if ($page > 1) { ?>
                        <a class="review-page-button" href="<?php echo admin_escape(admin_review_url(array(
                                                                        "keyword" => $keyword,
                                                                        "rating" => $ratingFilter,
                                                                        "status" => $statusFilter,
                                                                        "movie_id" => $movieFilter,
                                                                        "page" => $page - 1,
                                                                    ))); ?>">&lsaquo;</a>
                        <?php } ?>

                        <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            if ($startPage > 1) {
                            ?>
                        <a class="review-page-button" href="<?php echo admin_escape(admin_review_url(array(
                                                                        "keyword" => $keyword,
                                                                        "rating" => $ratingFilter,
                                                                        "status" => $statusFilter,
                                                                        "movie_id" => $movieFilter,
                                                                        "page" => 1,
                                                                    ))); ?>">1</a>
                        <?php if ($startPage > 2) { ?>
                        <span class="review-page-ellipsis">...</span>
                        <?php } ?>
                        <?php } ?>

                        <?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++) { ?>
                        <a class="review-page-button<?php echo $pageNumber === $page ? " is-active" : ""; ?>"
                            href="<?php echo admin_escape(admin_review_url(array(
                                                                                                                                "keyword" => $keyword,
                                                                                                                                "rating" => $ratingFilter,
                                                                                                                                "status" => $statusFilter,
                                                                                                                                "movie_id" => $movieFilter,
                                                                                                                                "page" => $pageNumber,
                                                                                                                            ))); ?>"><?php echo (int) $pageNumber; ?></a>
                        <?php } ?>

                        <?php if ($endPage < $totalPages) { ?>
                        <?php if ($endPage < $totalPages - 1) { ?>
                        <span class="review-page-ellipsis">...</span>
                        <?php } ?>
                        <a class="review-page-button" href="<?php echo admin_escape(admin_review_url(array(
                                                                        "keyword" => $keyword,
                                                                        "rating" => $ratingFilter,
                                                                        "status" => $statusFilter,
                                                                        "movie_id" => $movieFilter,
                                                                        "page" => $totalPages,
                                                                    ))); ?>"><?php echo (int) $totalPages; ?></a>
                        <?php } ?>

                        <?php if ($page < $totalPages) { ?>
                        <a class="review-page-button" href="<?php echo admin_escape(admin_review_url(array(
                                                                        "keyword" => $keyword,
                                                                        "rating" => $ratingFilter,
                                                                        "status" => $statusFilter,
                                                                        "movie_id" => $movieFilter,
                                                                        "page" => $page + 1,
                                                                    ))); ?>">&rsaquo;</a>
                        <?php } ?>
                    </nav>
                    <?php } ?>
                </div>
            </section>

            <a class="floating-add-button review-floating-button" href="#reviewFilterPanel" aria-label="Mo bo loc">+</a>
        </main>
    </div>

    <script>
    (function() {
        var toggleButton = document.querySelector('[data-review-filter-toggle]');
        var panel = document.getElementById('reviewFilterPanel');

        if (!toggleButton || !panel) {
            return;
        }

        toggleButton.addEventListener('click', function() {
            panel.classList.toggle('is-collapsed');
        });
    })();
    </script>
</body>

</html>