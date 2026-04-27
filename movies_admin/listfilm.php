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

if (!function_exists("admin_format_number")) {
    function admin_format_number($value)
    {
        return number_format((float) $value, 0, ",", ".");
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

if (!function_exists("admin_fetch_rows")) {
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
}

if (!function_exists("admin_build_page_url")) {
    function admin_build_page_url($pageNumber, $queryParams)
    {
        $params = $queryParams;
        $params["page_layout"] = "listfilm";
        $params["page"] = max(1, (int) $pageNumber);

        return "index.php?" . http_build_query($params);
    }
}

if (!function_exists("admin_movie_status_key")) {
    function admin_movie_status_key($movie)
    {
        $serverTwo = isset($movie["link2"]) ? strtolower(trim((string) $movie["link2"])) : "";
        $serverOne = isset($movie["link1"]) ? strtolower(trim((string) $movie["link1"])) : "";

        if ($serverTwo !== "" && $serverTwo !== "link2") {
            return "active";
        }

        if (($serverOne === "" || $serverOne === "link1" || $serverOne === "chua co" || $serverOne === "chưa có")
            && ($serverTwo === "" || $serverTwo === "link2")) {
            return "stopped";
        }

        return "pending";
    }
}

if (!function_exists("admin_movie_status_meta")) {
    function admin_movie_status_meta($movie)
    {
        $statusKey = admin_movie_status_key($movie);

        if ($statusKey === "active") {
            return array("label" => "Đang chiếu", "class" => "status-badge-active");
        }

        if ($statusKey === "stopped") {
            return array("label" => "Ngừng chiếu", "class" => "status-badge-stopped");
        }

        return array("label" => "Chờ duyệt", "class" => "status-badge-pending");
    }
}

$adminName = !empty($_SESSION["fullname"]) ? $_SESSION["fullname"] : (!empty($_SESSION["username"]) ? $_SESSION["username"] : "Admin");
$successMessage = isset($_SESSION["admin_add_movie_success"]) ? $_SESSION["admin_add_movie_success"] : "";
unset($_SESSION["admin_add_movie_success"]);

$countries = admin_fetch_rows($connect, "SELECT country_id, country_name FROM country ORDER BY country_name ASC");
$genres = admin_fetch_rows($connect, "SELECT theloai_id, ten_theloai FROM genres ORDER BY ten_theloai ASC");
$years = admin_fetch_rows($connect, "SELECT DISTINCT release_year FROM movies WHERE release_year IS NOT NULL ORDER BY release_year DESC");

$searchTerm = isset($_GET["search"]) ? trim((string) $_GET["search"]) : "";
$countryId = isset($_GET["country_id"]) ? (int) $_GET["country_id"] : 0;
$genreId = isset($_GET["genre_id"]) ? (int) $_GET["genre_id"] : 0;
$releaseYear = isset($_GET["release_year"]) ? (int) $_GET["release_year"] : 0;
$serverStatus = isset($_GET["server_status"]) ? trim((string) $_GET["server_status"]) : "all";
$pageNumber = isset($_GET["page"]) ? max(1, (int) $_GET["page"]) : 1;
$perPage = 10;

$allowedStatuses = array("all", "active", "pending", "stopped");
if (!in_array($serverStatus, $allowedStatuses, true)) {
    $serverStatus = "all";
}

$statusSql = "CASE
    WHEN TRIM(COALESCE(m.link2, '')) <> '' AND LOWER(TRIM(m.link2)) <> 'link2' THEN 'active'
    WHEN (TRIM(COALESCE(m.link1, '')) = '' OR LOWER(TRIM(m.link1)) IN ('link1', 'chua co'))
         AND (TRIM(COALESCE(m.link2, '')) = '' OR LOWER(TRIM(m.link2)) = 'link2') THEN 'stopped'
    ELSE 'pending'
END";

$whereParts = array("1=1");
$queryTypes = "";
$queryParams = array();

if ($searchTerm !== "") {
    $whereParts[] = "(m.title LIKE ? OR m.description LIKE ? OR m.language LIKE ?)";
    $likeSearch = "%" . $searchTerm . "%";
    $queryTypes .= "sss";
    $queryParams[] = $likeSearch;
    $queryParams[] = $likeSearch;
    $queryParams[] = $likeSearch;
}

if ($countryId > 0) {
    $whereParts[] = "m.country_id = ?";
    $queryTypes .= "i";
    $queryParams[] = $countryId;
}

if ($genreId > 0) {
    $whereParts[] = "EXISTS (
        SELECT 1
        FROM movie_genre mg_filter
        WHERE mg_filter.movie_id = m.movie_id AND mg_filter.theloai_id = ?
    )";
    $queryTypes .= "i";
    $queryParams[] = $genreId;
}

if ($releaseYear > 0) {
    $whereParts[] = "m.release_year = ?";
    $queryTypes .= "i";
    $queryParams[] = $releaseYear;
}

if ($serverStatus !== "all") {
    $whereParts[] = $statusSql . " = ?";
    $queryTypes .= "s";
    $queryParams[] = $serverStatus;
}

$whereSql = implode(" AND ", $whereParts);

$totalMovies = (int) admin_fetch_scalar_prepared(
    $connect,
    "SELECT COUNT(*) AS total
     FROM movies m
     WHERE " . $whereSql,
    "total",
    $queryTypes,
    $queryParams,
    0
);

$filteredViewTotal = (int) admin_fetch_scalar_prepared(
    $connect,
    "SELECT COALESCE(SUM(m.view), 0) AS total
     FROM movies m
     WHERE " . $whereSql,
    "total",
    $queryTypes,
    $queryParams,
    0
);

$activeMovieTotal = (int) admin_fetch_scalar_prepared(
    $connect,
    "SELECT COUNT(*) AS total
     FROM movies m
     WHERE " . $statusSql . " = 'active'",
    "total",
    "",
    array(),
    0
);

$totalPages = max(1, (int) ceil($totalMovies / $perPage));
$pageNumber = min($pageNumber, $totalPages);
$offset = ($pageNumber - 1) * $perPage;

$listQuery = "SELECT
        m.movie_id,
        m.title,
        m.description,
        m.release_year,
        m.language,
        m.country_id,
        m.link1,
        m.link2,
        m.img,
        COALESCE(m.view, 0) AS total_views,
        m.date_add,
        COALESCE(c.country_name, 'Chưa rõ') AS country_name,
        (
            SELECT GROUP_CONCAT(g.ten_theloai SEPARATOR '||')
            FROM movie_genre mg
            INNER JOIN genres g ON mg.theloai_id = g.theloai_id
            WHERE mg.movie_id = m.movie_id
        ) AS genre_names,
        " . $statusSql . " AS server_status
    FROM movies m
    LEFT JOIN country c ON m.country_id = c.country_id
    WHERE " . $whereSql . "
    ORDER BY m.movie_id DESC";

if (isset($_GET["export"]) && $_GET["export"] === "1") {
    $exportMovies = admin_fetch_rows_prepared($connect, $listQuery, $queryTypes, $queryParams);

    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=movie-report.csv");

    $output = fopen("php://output", "w");
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, array("Movie ID", "Title", "Release Year", "Language", "Country", "Status", "Views", "Genres"));

    foreach ($exportMovies as $movie) {
        $statusMeta = admin_movie_status_meta($movie);
        $genreText = !empty($movie["genre_names"]) ? str_replace("||", ", ", $movie["genre_names"]) : "Chua gan";

        fputcsv($output, array(
            $movie["movie_id"],
            $movie["title"],
            $movie["release_year"],
            $movie["language"],
            $movie["country_name"],
            $statusMeta["label"],
            $movie["total_views"],
            $genreText,
        ));
    }

    fclose($output);
    $connect->close();
    exit();
}

$listTypes = $queryTypes . "ii";
$listParams = $queryParams;
$listParams[] = $perPage;
$listParams[] = $offset;

$movies = admin_fetch_rows_prepared(
    $connect,
    $listQuery . " LIMIT ? OFFSET ?",
    $listTypes,
    $listParams
);

$pageQuery = $_GET;
unset($pageQuery["export"]);

$displayFrom = $totalMovies > 0 ? $offset + 1 : 0;
$displayTo = $totalMovies > 0 ? min($offset + $perPage, $totalMovies) : 0;

$serverSummaryLabel = "Đang chiếu (" . admin_format_number($activeMovieTotal) . ")";
if ($serverStatus === "pending") {
    $serverSummaryLabel = "Chờ duyệt";
} elseif ($serverStatus === "stopped") {
    $serverSummaryLabel = "Ngừng chiếu";
} elseif ($serverStatus === "all") {
    $serverSummaryLabel = "Tất cả trạng thái";
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý phim | ITMOVIES Admin</title>
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
                <a class="admin-nav-item" href="#movie-footer-cards">
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

        <main class="admin-main 
        -page">
            <header class="admin-topbar">
                <form class="admin-search admin-search-wide" action="index.php" method="get">
                    <input type="hidden" name="page_layout" value="listfilm">
                    <?php if ($countryId > 0) { ?><input type="hidden" name="country_id"
                        value="<?php echo (int) $countryId; ?>"><?php } ?>
                    <?php if ($genreId > 0) { ?><input type="hidden" name="genre_id"
                        value="<?php echo (int) $genreId; ?>"><?php } ?>
                    <?php if ($releaseYear > 0) { ?><input type="hidden" name="release_year"
                        value="<?php echo (int) $releaseYear; ?>"><?php } ?>
                    <?php if ($serverStatus !== "all") { ?><input type="hidden" name="server_status"
                        value="<?php echo admin_escape($serverStatus); ?>"><?php } ?>
                    <span class="admin-search-icon">&#9906;</span>
                    <input type="search" name="search" value="<?php echo admin_escape($searchTerm); ?>"
                        placeholder="Tìm kiếm phim, diễn viên, đạo diễn...">
                </form>

                <div class="admin-topbar-actions">
                    <a class="admin-primary-btn" href="index.php?page_layout=themmoi_film">
                        <span>+</span>
                        <span>Thêm mới</span>
                    </a>
                    <button class="admin-icon-button" type="button" aria-label="Thông báo">&#128276;</button>
                    <button class="admin-icon-button" type="button" aria-label="Cài đặt">&#9881;</button>
                    <div class="admin-profile">
                        <div class="admin-avatar"><?php echo admin_escape(admin_initials($adminName)); ?></div>
                        <div class="admin-profile-tooltip">
                            <p class="admin-profile-name"><?php echo admin_escape($adminName); ?></p>
                            <p class="admin-profile-role">Admin session</p>
                        </div>
                    </div>
                </div>
            </header>

            <section class="movie-page-heading">
                <div>
                    <h1>Quản lý phim</h1>
                    <p>Danh sách kho phim hệ thống và trạng thái phát trực tuyến.</p>
                </div>

                <div class="movie-heading-actions">
                    <a class="admin-secondary-btn"
                        href="index.php?<?php echo admin_escape(http_build_query(array_merge($pageQuery, array("page_layout" => "listfilm", "export" => 1)))); ?>">Xuất
                        báo cáo</a>
                    <a class="admin-primary-btn" href="index.php?page_layout=themmoi_film">Thêm phim mới</a>
                </div>
            </section>

            <?php if ($successMessage !== "") { ?>
                <div class="admin-alert admin-alert-success">
                    <strong>Thành công:</strong>
                    <span><?php echo admin_escape($successMessage); ?></span>
                </div>
            <?php } ?>

            <form class="movie-filter-form" action="index.php" method="get">
                <input type="hidden" name="page_layout" value="listfilm">
                <input type="hidden" name="search" value="<?php echo admin_escape($searchTerm); ?>">

                <div class="movie-filter-grid">
                    <label class="movie-filter-card">
                        <span class="movie-filter-label">QUỐC GIA</span>
                        <select name="country_id">
                            <option value="0">Tất cả quốc gia</option>
                            <?php foreach ($countries as $country) { ?>
                            <option value="<?php echo (int) $country["country_id"]; ?>"
                                <?php echo $countryId === (int) $country["country_id"] ? "selected" : ""; ?>>
                                <?php echo admin_escape($country["country_name"]); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </label>

                    <label class="movie-filter-card">
                        <span class="movie-filter-label">THỂ LOẠI</span>
                        <select name="genre_id">
                            <option value="0">Tất cả thể loại</option>
                            <?php foreach ($genres as $genre) { ?>
                            <option value="<?php echo (int) $genre["theloai_id"]; ?>"
                                <?php echo $genreId === (int) $genre["theloai_id"] ? "selected" : ""; ?>>
                                <?php echo admin_escape($genre["ten_theloai"]); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </label>

                    <label class="movie-filter-card">
                        <span class="movie-filter-label">NĂM PHÁT HÀNH</span>
                        <select name="release_year">
                            <option value="0">Tất cả các năm</option>
                            <?php foreach ($years as $year) { ?>
                            <?php $yearValue = (int) $year["release_year"]; ?>
                            <option value="<?php echo $yearValue; ?>"
                                <?php echo $releaseYear === $yearValue ? "selected" : ""; ?>>
                                <?php echo admin_escape($yearValue); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </label>

                    <label class="movie-filter-card">
                        <span class="movie-filter-label">TRẠNG THÁI SERVER</span>
                        <select name="server_status">
                            <option value="all" <?php echo $serverStatus === "all" ? "selected" : ""; ?>>Tất cả trạng
                                thái</option>
                            <option value="active" <?php echo $serverStatus === "active" ? "selected" : ""; ?>>Đang
                                chiếu (<?php echo admin_escape(admin_format_number($activeMovieTotal)); ?>)</option>
                            <option value="pending" <?php echo $serverStatus === "pending" ? "selected" : ""; ?>>Chờ
                                duyệt</option>
                            <option value="stopped" <?php echo $serverStatus === "stopped" ? "selected" : ""; ?>>Ngừng
                                chiếu</option>
                        </select>
                        <!-- <span class="movie-filter-status-dot"></span>
                        <span class="movie-filter-status-text"><?php echo admin_escape($serverSummaryLabel); ?></span> -->
                    </label>
                </div>

                <div class="movie-filter-actions">
                    <button class="admin-secondary-btn" type="submit">Áp dụng bộ lọc</button>
                    <a class="movie-reset-link" href="index.php?page_layout=listfilm">Đặt lại</a>
                </div>
            </form>

            <section class="movie-table-card">
                <div class="movie-table-scroll">
                    <table class="movie-table">
                        <thead>
                            <tr>
                                <th class="movie-table-checkbox">
                                    <input type="checkbox" aria-label="Chọn tất cả" disabled>
                                </th>
                                <th>Phim</th>
                                <th>Năm</th>
                                <th>Ngôn ngữ</th>
                                <th>Quốc gia</th>
                                <th>Trạng thái</th>
                                <th>Lượt xem</th>
                                <th>Thể loại</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($movies)) { ?>
                            <tr>
                                <td colspan="9">
                                    <div class="admin-empty-state">Không tìm thấy phim phù hợp với bộ lọc hiện tại.
                                    </div>
                                </td>
                            </tr>
                            <?php } else { ?>
                            <?php foreach ($movies as $movie) { ?>
                            <?php
                                    $posterPath = admin_movie_poster(isset($movie["img"]) ? $movie["img"] : "");
                                    $statusMeta = admin_movie_status_meta($movie);
                                    $genreList = !empty($movie["genre_names"]) ? explode("||", $movie["genre_names"]) : array();
                                    ?>
                            <tr>
                                <td class="movie-table-checkbox">
                                    <input type="checkbox"
                                        aria-label="Chọn phim <?php echo admin_escape($movie["title"]); ?>">
                                </td>
                                <td>
                                    <div class="movie-cell-title">
                                        <div class="movie-poster">
                                            <?php if ($posterPath !== "") { ?>
                                            <img src="<?php echo admin_escape($posterPath); ?>"
                                                alt="<?php echo admin_escape($movie["title"]); ?>">
                                            <?php } else { ?>
                                            <div class="movie-poster-fallback">
                                                <?php echo admin_escape(admin_initials($movie["title"])); ?></div>
                                            <?php } ?>
                                        </div>
                                        <div class="movie-title-copy">
                                            <h3><?php echo admin_escape($movie["title"]); ?></h3>
                                            <span>ID: MOV-<?php echo (int) $movie["movie_id"]; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo admin_escape($movie["release_year"] !== null ? $movie["release_year"] : "N/A"); ?>
                                </td>
                                <td><?php echo admin_escape(!empty($movie["language"]) ? $movie["language"] : "Đang cập nhật"); ?>
                                </td>
                                <td><?php echo admin_escape($movie["country_name"]); ?></td>
                                <td>
                                    <span class="status-badge <?php echo admin_escape($statusMeta["class"]); ?>">
                                        <span class="status-badge-dot"></span>
                                        <?php echo admin_escape($statusMeta["label"]); ?>
                                    </span>
                                </td>
                                <td><?php echo admin_escape(admin_format_number($movie["total_views"])); ?></td>
                                <td>
                                    <div class="genre-chip-list">
                                        <?php if (empty($genreList)) { ?>
                                        <span class="genre-chip genre-chip-muted">Chưa gán</span>
                                        <?php } else { ?>
                                        <?php foreach ($genreList as $genreName) { ?>
                                        <span class="genre-chip"><?php echo admin_escape($genreName); ?></span>
                                        <?php } ?>
                                        <?php } ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="movie-actions">
                                        <a class="action-button"
                                            href="../users/index.php?page_layout=chitietphim&id=<?php echo (int) $movie["movie_id"]; ?>"
                                            target="_blank" title="Xem chi tiết">&#128065;</a>
                                        <a class="action-button"
                                            href="index.php?page_layout=capnhatfilm&id=<?php echo (int) $movie["movie_id"]; ?>"
                                            title="Sửa">&#9998;</a>
                                        <a class="action-button action-button-danger"
                                            href="index.php?page_layout=xuly_xoafilm&id=<?php echo (int) $movie["movie_id"]; ?>"
                                            onclick="return confirm('Bạn có chắc chắn muốn xóa phim không?')"
                                            title="Xóa">&#128465;</a>
                                    </div>
                                </td>
                            </tr>
                            <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="movie-table-footer">
                    <p>Hiển thị
                        <strong><?php echo admin_escape($displayFrom); ?>-<?php echo admin_escape($displayTo); ?></strong>
                        trên tổng số <strong><?php echo admin_escape(admin_format_number($totalMovies)); ?></strong>
                        phim
                    </p>

                    <nav class="pagination" aria-label="Phân trang phim">
                        <a class="pagination-button <?php echo $pageNumber <= 1 ? "is-disabled" : ""; ?>"
                            href="<?php echo $pageNumber <= 1 ? "#" : admin_escape(admin_build_page_url($pageNumber - 1, $pageQuery)); ?>">&lsaquo;</a>
                        <?php
                        $pageStart = max(1, $pageNumber - 1);
                        $pageEnd = min($totalPages, $pageNumber + 1);

                        if ($pageStart > 1) {
                            echo '<a class="pagination-button" href="' . admin_escape(admin_build_page_url(1, $pageQuery)) . '">1</a>';
                            if ($pageStart > 2) {
                                echo '<span class="pagination-ellipsis">...</span>';
                            }
                        }

                        for ($page = $pageStart; $page <= $pageEnd; $page++) {
                            $activeClass = $page === $pageNumber ? "is-active" : "";
                            echo '<a class="pagination-button ' . $activeClass . '" href="' . admin_escape(admin_build_page_url($page, $pageQuery)) . '">' . admin_escape($page) . '</a>';
                        }

                        if ($pageEnd < $totalPages) {
                            if ($pageEnd < $totalPages - 1) {
                                echo '<span class="pagination-ellipsis">...</span>';
                            }
                            echo '<a class="pagination-button" href="' . admin_escape(admin_build_page_url($totalPages, $pageQuery)) . '">' . admin_escape($totalPages) . '</a>';
                        }
                        ?>
                        <a class="pagination-button <?php echo $pageNumber >= $totalPages ? "is-disabled" : ""; ?>"
                            href="<?php echo $pageNumber >= $totalPages ? "#" : admin_escape(admin_build_page_url($pageNumber + 1, $pageQuery)); ?>">&rsaquo;</a>
                    </nav>
                </div>
            </section>

            <section class="movie-footer-cards" id="movie-footer-cards">
                <article class="server-card">
                    <div class="server-card-icon">&#128423;</div>
                    <div class="server-card-copy">
                        <p class="server-card-label">Dung lượng Server</p>
                        <h2>24%</h2>
                        <p>Hệ thống đang sử dụng 1.2TB trên 5TB khả dụng.</p>
                        <div class="server-progress">
                            <span style="width: 24%;"></span>
                        </div>
                    </div>
                </article>

                <article class="views-card">
                    <div class="views-card-copy">
                        <p class="views-card-label">Lượt xem hôm nay</p>
                        <h2>+12,500</h2>
                        <p>Tổng lượt xem đang được đẩy mạnh từ các phim có server hoạt động ổn định.</p>
                    </div>
                    <div class="views-card-graphic">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </article>
            </section>
        </main>
    </div>
</body>

</html>
