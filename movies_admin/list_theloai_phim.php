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

        $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . "hinhanhphim" . DIRECTORY_SEPARATOR . $safeName;
        if (!is_file($absolutePath)) {
            return "";
        }

        return "hinhanhphim/" . $safeName;
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

if (!function_exists("admin_mapping_url")) {
    function admin_mapping_url($params = array())
    {
        $query = array_merge(array("page_layout" => "list_theloai_phim"), $params);

        foreach ($query as $key => $value) {
            if ($value === "" || $value === null) {
                unset($query[$key]);
            }
        }

        return "index.php?" . http_build_query($query);
    }
}

if (!function_exists("admin_excerpt")) {
    function admin_excerpt($text, $limit = 180)
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
        if ($text === "") {
            return "Phim hiện chưa có mô tả ngắn trong cơ sở dữ liệu.";
        }

        if (function_exists("mb_strlen") && function_exists("mb_substr")) {
            if (mb_strlen($text, "UTF-8") <= $limit) {
                return $text;
            }

            return rtrim(mb_substr($text, 0, $limit, "UTF-8")) . "...";
        }

        if (strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(substr($text, 0, $limit)) . "...";
    }
}

if (!function_exists("admin_movie_has_video")) {
    function admin_movie_has_video($movie)
    {
        $links = array(
            isset($movie["link1"]) ? $movie["link1"] : "",
            isset($movie["link2"]) ? $movie["link2"] : "",
        );

        foreach ($links as $link) {
            $normalized = strtolower(trim((string) $link));
            if ($normalized !== "" && $normalized !== "link1" && $normalized !== "link2" && $normalized !== "lnk1" && $normalized !== "chưa có" && $normalized !== "chua co") {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists("admin_mock_rating")) {
    function admin_mock_rating($movieId, $viewTotal)
    {
        $seed = ((int) $movieId % 9) * 0.1;
        $viewBoost = min(0.8, ((int) $viewTotal % 20) * 0.03);
        $value = 7.4 + $seed + $viewBoost;

        return number_format(min(9.6, $value), 1, ".", "");
    }
}

if (!function_exists("admin_mock_duration")) {
    function admin_mock_duration($movieId)
    {
        return 96 + (((int) $movieId % 6) * 7);
    }
}

if (!function_exists("admin_normalize_id_list")) {
    function admin_normalize_id_list($values)
    {
        $normalized = array();

        if (!is_array($values)) {
            return $normalized;
        }

        foreach ($values as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }

        return array_values($normalized);
    }
}

if (!function_exists("admin_build_in_clause")) {
    function admin_build_in_clause($ids)
    {
        if (empty($ids)) {
            return "";
        }

        return implode(", ", array_fill(0, count($ids), "?"));
    }
}

$adminName = !empty($_SESSION["fullname"]) ? $_SESSION["fullname"] : (!empty($_SESSION["username"]) ? $_SESSION["username"] : "Admin");
$keyword = isset($_GET["keyword"]) ? trim((string) $_GET["keyword"]) : "";
$countryFilter = isset($_GET["country_id"]) ? (int) $_GET["country_id"] : 0;
$page = isset($_GET["page"]) ? max(1, (int) $_GET["page"]) : 1;
$selectedMovieId = isset($_GET["movie_id"]) ? (int) $_GET["movie_id"] : 0;
$perPage = 20;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["mapping_action"]) && $_POST["mapping_action"] === "save_movie_genres") {
    $postedMovieId = isset($_POST["movie_id"]) ? (int) $_POST["movie_id"] : 0;
    $returnKeyword = isset($_POST["return_keyword"]) ? trim((string) $_POST["return_keyword"]) : "";
    $returnCountry = isset($_POST["return_country_id"]) ? (int) $_POST["return_country_id"] : 0;
    $returnPage = isset($_POST["return_page"]) ? max(1, (int) $_POST["return_page"]) : 1;
    $genreIds = admin_normalize_id_list(isset($_POST["genre_ids"]) ? $_POST["genre_ids"] : array());

    $redirectParams = array(
        "movie_id" => $postedMovieId,
        "keyword" => $returnKeyword,
        "country_id" => $returnCountry,
        "page" => $returnPage,
    );

    if ($postedMovieId <= 0) {
        header("Location: " . admin_mapping_url(array_merge($redirectParams, array("status" => "error"))));
        exit();
    }

    $movieExists = (int) admin_fetch_scalar_prepared(
        $connect,
        "SELECT COUNT(*) AS total FROM movies WHERE movie_id = ?",
        "total",
        "i",
        array($postedMovieId),
        0
    );

    if ($movieExists <= 0) {
        header("Location: " . admin_mapping_url(array_merge($redirectParams, array("status" => "error"))));
        exit();
    }

    $validGenreIds = array();
    if (!empty($genreIds)) {
        $placeholders = admin_build_in_clause($genreIds);
        $types = str_repeat("i", count($genreIds));
        $rows = admin_fetch_rows_prepared(
            $connect,
            "SELECT theloai_id FROM genres WHERE theloai_id IN (" . $placeholders . ")",
            $types,
            $genreIds
        );

        foreach ($rows as $row) {
            $validGenreIds[(int) $row["theloai_id"]] = (int) $row["theloai_id"];
        }
    }

    if (count($validGenreIds) !== count($genreIds)) {
        header("Location: " . admin_mapping_url(array_merge($redirectParams, array("status" => "error"))));
        exit();
    }

    $deleteStatement = $connect->prepare("DELETE FROM movie_genre WHERE movie_id = ?");
    $deleteOk = false;

    if ($deleteStatement && $deleteStatement->bind_param("i", $postedMovieId) && $deleteStatement->execute()) {
        $deleteOk = true;
    }

    if ($deleteStatement) {
        $deleteStatement->close();
    }

    if (!$deleteOk) {
        header("Location: " . admin_mapping_url(array_merge($redirectParams, array("status" => "error"))));
        exit();
    }

    $insertOk = true;
    if (!empty($genreIds)) {
        $insertStatement = $connect->prepare("INSERT INTO movie_genre (movie_id, theloai_id) VALUES (?, ?)");
        if (!$insertStatement) {
            $insertOk = false;
        } else {
            foreach ($genreIds as $genreId) {
                if (!$insertStatement->bind_param("ii", $postedMovieId, $genreId) || !$insertStatement->execute()) {
                    $insertOk = false;
                    break;
                }
            }
            $insertStatement->close();
        }
    }

    if (!$insertOk) {
        header("Location: " . admin_mapping_url(array_merge($redirectParams, array("status" => "error"))));
        exit();
    }

    header("Location: " . admin_mapping_url(array_merge($redirectParams, array("status" => "success"))));
    exit();
}

$status = isset($_GET["status"]) ? trim((string) $_GET["status"]) : "";
$statusMessage = "";
$statusClass = "";

if ($status === "success") {
    $statusMessage = "Đã cập nhật thể loại cho phim.";
    $statusClass = "mapping-toast-success";
} elseif ($status === "error") {
    $statusMessage = "Không thể cập nhật thể loại. Vui lòng thử lại.";
    $statusClass = "mapping-toast-error";
}

$countries = admin_fetch_rows_prepared(
    $connect,
    "SELECT country_id, country_name FROM country ORDER BY country_name ASC"
);

$movieWhere = array();
$movieTypes = "";
$movieParams = array();

if ($keyword !== "") {
    $movieWhere[] = "m.title LIKE ?";
    $movieTypes .= "s";
    $movieParams[] = "%" . $keyword . "%";
}

if ($countryFilter > 0) {
    $movieWhere[] = "m.country_id = ?";
    $movieTypes .= "i";
    $movieParams[] = $countryFilter;
}

$whereClause = !empty($movieWhere) ? " WHERE " . implode(" AND ", $movieWhere) : "";

$totalMovies = (int) admin_fetch_scalar_prepared(
    $connect,
    "SELECT COUNT(*) AS total FROM movies m" . $whereClause,
    "total",
    $movieTypes,
    $movieParams,
    0
);

$totalPages = max(1, (int) ceil($totalMovies / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;
$movieListTypes = $movieTypes . "ii";
$movieListParams = $movieParams;
$movieListParams[] = $perPage;
$movieListParams[] = $offset;

$movies = admin_fetch_rows_prepared(
    $connect,
    "SELECT
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
        COALESCE(c.country_name, 'Chưa rõ') AS country_name,
        (
            SELECT GROUP_CONCAT(g.ten_theloai ORDER BY g.ten_theloai SEPARATOR ', ')
            FROM movie_genre mg
            INNER JOIN genres g ON g.theloai_id = mg.theloai_id
            WHERE mg.movie_id = m.movie_id
        ) AS genre_names
     FROM movies m
     LEFT JOIN country c ON c.country_id = m.country_id" . $whereClause . "
     ORDER BY COALESCE(m.date_add, '1000-01-01') DESC, m.movie_id DESC
     LIMIT ? OFFSET ?",
    $movieListTypes,
    $movieListParams
);

if ($selectedMovieId <= 0 && !empty($movies)) {
    $selectedMovieId = (int) $movies[0]["movie_id"];
}

$selectedMovie = null;
if ($selectedMovieId > 0) {
    $selectedMovie = admin_fetch_one_prepared(
        $connect,
        "SELECT
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
            COALESCE(c.country_name, 'Chưa rõ') AS country_name,
            (
                SELECT GROUP_CONCAT(g.ten_theloai ORDER BY g.ten_theloai SEPARATOR ', ')
                FROM movie_genre mg
                INNER JOIN genres g ON g.theloai_id = mg.theloai_id
                WHERE mg.movie_id = m.movie_id
            ) AS genre_names
         FROM movies m
         LEFT JOIN country c ON c.country_id = m.country_id
         WHERE m.movie_id = ?
         LIMIT 1",
        "i",
        array($selectedMovieId)
    );
}

$selectedGenreIds = array();
if ($selectedMovie) {
    $selectedGenreRows = admin_fetch_rows_prepared(
        $connect,
        "SELECT theloai_id FROM movie_genre WHERE movie_id = ? ORDER BY theloai_id ASC",
        "i",
        array((int) $selectedMovie["movie_id"])
    );

    foreach ($selectedGenreRows as $selectedGenreRow) {
        $selectedGenreIds[] = (int) $selectedGenreRow["theloai_id"];
    }
}

$selectedGenreMap = array();
foreach ($selectedGenreIds as $selectedGenreId) {
    $selectedGenreMap[$selectedGenreId] = true;
}

$allGenres = admin_fetch_rows_prepared(
    $connect,
    "SELECT theloai_id, ten_theloai FROM genres ORDER BY ten_theloai ASC"
);

$selectedGenres = array();
foreach ($allGenres as $genre) {
    if (isset($selectedGenreMap[(int) $genre["theloai_id"]])) {
        $selectedGenres[] = $genre;
    }
}

$selectedGenreCount = count($selectedGenres);
$selectedPoster = $selectedMovie ? admin_movie_poster(isset($selectedMovie["img"]) ? $selectedMovie["img"] : "") : "";
$currentStart = $totalMovies > 0 ? ($offset + 1) : 0;
$currentEnd = $totalMovies > 0 ? min($totalMovies, $offset + count($movies)) : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gán thể loại cho phim | ITMOVIES Admin</title>
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
                <a class="admin-nav-item is-active has-indicator" href="index.php?page_layout=list_theloai_phim">
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
                <a class="admin-nav-item" href="index.php?page_layout=list_review">
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

        <main class="admin-main mapping-page">
            <?php if ($statusMessage !== "") { ?>
                <div class="mapping-toast <?php echo admin_escape($statusClass); ?>" data-mapping-toast>
                    <?php echo admin_escape($statusMessage); ?>
                </div>
            <?php } ?>

            <header class="admin-topbar">
                <form class="admin-search admin-search-wide" action="index.php" method="get">
                    <input type="hidden" name="page_layout" value="list_theloai_phim">
                    <?php if ($countryFilter > 0) { ?>
                        <input type="hidden" name="country_id" value="<?php echo (int) $countryFilter; ?>">
                    <?php } ?>
                    <span class="admin-search-icon">&#9906;</span>
                    <input type="search" name="keyword" value="<?php echo admin_escape($keyword); ?>" placeholder="Tìm kiếm phim...">
                </form>

                <div class="admin-topbar-actions">
                    <a class="admin-primary-btn" href="index.php?page_layout=themmoi_film">Thêm mới</a>
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

            <section class="mapping-heading">
                <div>
                    <h1>Gán thể loại cho phim</h1>
                    <p>Hệ thống phân loại nội dung điện ảnh tập trung.</p>
                </div>
            </section>

            <section class="mapping-layout">
                <aside class="movie-picker-card admin-card">
                    <div class="mapping-card-head">
                        <div>
                            <h2>Chọn phim</h2>
                            <p><?php echo admin_escape($totalMovies); ?> phim phù hợp với bộ lọc hiện tại.</p>
                        </div>
                    </div>

                    <form class="mapping-filter-form" action="index.php" method="get">
                        <input type="hidden" name="page_layout" value="list_theloai_phim">
                        <?php if ($keyword !== "") { ?>
                            <input type="hidden" name="keyword" value="<?php echo admin_escape($keyword); ?>">
                        <?php } ?>
                        <label class="admin-field">
                            <span>Bộ lọc kho phim</span>
                            <select name="country_id" onchange="this.form.submit()">
                                <option value="0">Tất cả phim</option>
                                <?php foreach ($countries as $country) { ?>
                                    <option value="<?php echo (int) $country["country_id"]; ?>" <?php echo $countryFilter === (int) $country["country_id"] ? "selected" : ""; ?>>
                                        <?php echo admin_escape($country["country_name"]); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </label>
                    </form>

                    <div class="movie-picker-meta">
                        <span>Hiển thị <?php echo admin_escape($currentStart); ?> - <?php echo admin_escape($currentEnd); ?></span>
                        <span>Trang <?php echo admin_escape($page); ?>/<?php echo admin_escape($totalPages); ?></span>
                    </div>

                    <div class="movie-picker-list">
                        <?php if (empty($movies)) { ?>
                            <div class="admin-empty-state">Không tìm thấy phim phù hợp. Thử đổi từ khóa hoặc bộ lọc quốc gia.</div>
                        <?php } else { ?>
                            <?php foreach ($movies as $movie) { ?>
                                <?php
                                $moviePoster = admin_movie_poster(isset($movie["img"]) ? $movie["img"] : "");
                                $isActive = $selectedMovie && (int) $selectedMovie["movie_id"] === (int) $movie["movie_id"];
                                $metaLabel = !empty($movie["genre_names"]) ? explode(",", $movie["genre_names"])[0] : $movie["country_name"];
                                ?>
                                <a class="movie-picker-item<?php echo $isActive ? " active" : ""; ?>" href="<?php echo admin_escape(admin_mapping_url(array(
                                    "keyword" => $keyword,
                                    "country_id" => $countryFilter,
                                    "page" => $page,
                                    "movie_id" => (int) $movie["movie_id"],
                                ))); ?>">
                                    <div class="movie-picker-poster">
                                        <?php if ($moviePoster !== "") { ?>
                                            <img src="<?php echo admin_escape($moviePoster); ?>" alt="<?php echo admin_escape($movie["title"]); ?>">
                                        <?php } else { ?>
                                            <div class="movie-picker-fallback"><?php echo admin_escape(admin_initials($movie["title"])); ?></div>
                                        <?php } ?>
                                    </div>
                                    <div class="movie-picker-copy">
                                        <h3><?php echo admin_escape($movie["title"]); ?></h3>
                                        <p>
                                            <?php echo admin_escape($movie["release_year"] ? $movie["release_year"] : "Chưa rõ năm"); ?>
                                            <span>&bull;</span>
                                            <?php echo admin_escape(trim((string) $metaLabel) !== "" ? trim((string) $metaLabel) : "Chưa gán thể loại"); ?>
                                        </p>
                                    </div>
                                    <span class="movie-picker-check"><?php echo $isActive ? "&#10003;" : ""; ?></span>
                                </a>
                            <?php } ?>
                        <?php } ?>
                    </div>

                    <?php if ($totalPages > 1) { ?>
                        <div class="mapping-pagination">
                            <?php if ($page > 1) { ?>
                                <a class="admin-secondary-btn" href="<?php echo admin_escape(admin_mapping_url(array(
                                    "keyword" => $keyword,
                                    "country_id" => $countryFilter,
                                    "page" => $page - 1,
                                    "movie_id" => $selectedMovie ? (int) $selectedMovie["movie_id"] : 0,
                                ))); ?>">Trang trước</a>
                            <?php } ?>
                            <?php if ($page < $totalPages) { ?>
                                <a class="admin-secondary-btn" href="<?php echo admin_escape(admin_mapping_url(array(
                                    "keyword" => $keyword,
                                    "country_id" => $countryFilter,
                                    "page" => $page + 1,
                                    "movie_id" => $selectedMovie ? (int) $selectedMovie["movie_id"] : 0,
                                ))); ?>">Trang sau</a>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </aside>

                <div class="mapping-main">
                    <?php if (!$selectedMovie) { ?>
                        <section class="selected-movie-card admin-card">
                            <div class="admin-empty-state">Chưa có phim nào để gán thể loại. Hãy thêm phim hoặc nới rộng bộ lọc tìm kiếm.</div>
                        </section>
                    <?php } else { ?>
                        <form action="<?php echo admin_escape(admin_mapping_url(array(
                            "movie_id" => (int) $selectedMovie["movie_id"],
                            "keyword" => $keyword,
                            "country_id" => $countryFilter,
                            "page" => $page,
                        ))); ?>" method="post" class="mapping-form" data-mapping-form>
                            <input type="hidden" name="mapping_action" value="save_movie_genres">
                            <input type="hidden" name="movie_id" value="<?php echo (int) $selectedMovie["movie_id"]; ?>">
                            <input type="hidden" name="return_keyword" value="<?php echo admin_escape($keyword); ?>">
                            <input type="hidden" name="return_country_id" value="<?php echo (int) $countryFilter; ?>">
                            <input type="hidden" name="return_page" value="<?php echo (int) $page; ?>">

                            <section class="selected-movie-card admin-card">
                                <div class="selected-movie-card-inner">
                                    <div class="movie-preview-poster">
                                        <?php if ($selectedPoster !== "") { ?>
                                            <img src="<?php echo admin_escape($selectedPoster); ?>" alt="<?php echo admin_escape($selectedMovie["title"]); ?>">
                                        <?php } else { ?>
                                            <div class="movie-preview-fallback"><?php echo admin_escape(admin_initials($selectedMovie["title"])); ?></div>
                                        <?php } ?>
                                    </div>

                                    <div class="selected-movie-copy">
                                        <div class="selected-movie-meta">
                                            <?php if (admin_movie_has_video($selectedMovie)) { ?>
                                                <span class="admin-badge admin-badge-red">ĐANG CÔNG CHIẾU</span>
                                            <?php } ?>
                                            <span class="selected-movie-id">ID: MV-<?php echo (int) $selectedMovie["movie_id"]; ?></span>
                                        </div>

                                        <h2><?php echo admin_escape($selectedMovie["title"]); ?></h2>
                                        <p><?php echo admin_escape(admin_excerpt(isset($selectedMovie["description"]) ? $selectedMovie["description"] : "")); ?></p>

                                        <div class="selected-movie-stats">
                                            <span class="selected-movie-stat selected-movie-stat-rating">&#11088; <?php echo admin_escape(admin_mock_rating((int) $selectedMovie["movie_id"], (int) $selectedMovie["total_views"])); ?></span>
                                            <span class="selected-movie-stat"><?php echo admin_escape(admin_mock_duration((int) $selectedMovie["movie_id"])); ?> phút</span>
                                            <span class="selected-movie-stat"><?php echo admin_escape($selectedMovie["release_year"] ? $selectedMovie["release_year"] : "Chưa rõ năm"); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="genre-assignment-card admin-card">
                                <div class="mapping-card-head mapping-card-head-tight">
                                    <div>
                                        <h2>Chọn thể loại</h2>
                                        <p>Gỡ toàn bộ thể loại nếu bạn muốn phim này tạm thời không thuộc nhóm nội dung nào.</p>
                                    </div>
                                    <span class="selected-count-pill"><span data-selected-count><?php echo admin_escape($selectedGenreCount); ?></span> đã chọn</span>
                                </div>

                                <div class="selected-genre-panel">
                                    <div class="selected-genre-list" data-selected-genre-list>
                                        <?php if (empty($selectedGenres)) { ?>
                                            <div class="selected-genre-empty" data-selected-empty-state>Chưa có thể loại nào được chọn</div>
                                        <?php } else { ?>
                                            <?php foreach ($selectedGenres as $genre) { ?>
                                                <button type="button" class="selected-genre-chip" data-remove-genre="<?php echo (int) $genre["theloai_id"]; ?>">
                                                    <span><?php echo admin_escape($genre["ten_theloai"]); ?></span>
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>
                                </div>

                                <?php if (empty($allGenres)) { ?>
                                    <div class="admin-empty-state">Chưa có thể loại nào trong bảng `genres`. Hãy thêm thể loại trước khi gán.</div>
                                <?php } else { ?>
                                    <div class="genre-grid">
                                        <?php foreach ($allGenres as $genre) { ?>
                                            <?php $genreId = (int) $genre["theloai_id"]; ?>
                                            <label class="genre-option<?php echo isset($selectedGenreMap[$genreId]) ? " active" : ""; ?>" data-genre-option>
                                                <input class="mapping-genre-checkbox" type="checkbox" name="genre_ids[]" value="<?php echo $genreId; ?>" data-genre-id="<?php echo $genreId; ?>" data-genre-name="<?php echo admin_escape($genre["ten_theloai"]); ?>" <?php echo isset($selectedGenreMap[$genreId]) ? "checked" : ""; ?>>
                                                <span><?php echo admin_escape($genre["ten_theloai"]); ?></span>
                                            </label>
                                        <?php } ?>
                                    </div>
                                <?php } ?>

                                <div class="mapping-save-bar">
                                    <p>Không chọn thể loại nào sẽ xóa toàn bộ mapping hiện tại của phim này.</p>
                                    <button class="admin-primary-btn mapping-save-button" type="submit">
                                        <span>&#128190;</span>
                                        <span>Lưu gán thể loại</span>
                                    </button>
                                </div>
                            </section>
                        </form>
                    <?php } ?>
                </div>
            </section>
        </main>
    </div>

    <script>
        (function () {
            var toast = document.querySelector("[data-mapping-toast]");
            if (toast) {
                window.setTimeout(function () {
                    toast.classList.add("is-hidden");
                }, 2600);
            }

            var form = document.querySelector("[data-mapping-form]");
            if (!form) {
                return;
            }

            var selectedList = form.querySelector("[data-selected-genre-list]");
            var countNode = form.querySelector("[data-selected-count]");
            var checkboxes = Array.prototype.slice.call(form.querySelectorAll(".mapping-genre-checkbox"));

            function syncGenreState() {
                var selected = [];

                checkboxes.forEach(function (checkbox) {
                    var option = checkbox.closest("[data-genre-option]");
                    if (option) {
                        option.classList.toggle("active", checkbox.checked);
                    }

                    if (checkbox.checked) {
                        selected.push({
                            id: checkbox.getAttribute("data-genre-id"),
                            name: checkbox.getAttribute("data-genre-name")
                        });
                    }
                });

                if (countNode) {
                    countNode.textContent = String(selected.length);
                }

                if (!selectedList) {
                    return;
                }

                selectedList.innerHTML = "";

                if (selected.length === 0) {
                    var emptyState = document.createElement("div");
                    emptyState.className = "selected-genre-empty";
                    emptyState.textContent = "Chưa có thể loại nào được chọn";
                    selectedList.appendChild(emptyState);
                    return;
                }

                selected.forEach(function (item) {
                    var chip = document.createElement("button");
                    chip.type = "button";
                    chip.className = "selected-genre-chip";
                    chip.setAttribute("data-remove-genre", item.id);
                    var chipLabel = document.createElement("span");
                    chipLabel.textContent = item.name;
                    var chipClose = document.createElement("span");
                    chipClose.setAttribute("aria-hidden", "true");
                    chipClose.textContent = "\u00D7";
                    chip.appendChild(chipLabel);
                    chip.appendChild(chipClose);
                    selectedList.appendChild(chip);
                });
            }

            checkboxes.forEach(function (checkbox) {
                checkbox.addEventListener("change", syncGenreState);
            });

            if (selectedList) {
                selectedList.addEventListener("click", function (event) {
                    var trigger = event.target.closest("[data-remove-genre]");
                    if (!trigger) {
                        return;
                    }

                    var genreId = trigger.getAttribute("data-remove-genre");
                    var checkbox = form.querySelector(".mapping-genre-checkbox[data-genre-id=\"" + genreId + "\"]");
                    if (checkbox) {
                        checkbox.checked = false;
                        syncGenreState();
                    }
                });
            }

            form.addEventListener("submit", function (event) {
                var selectedTotal = checkboxes.filter(function (checkbox) {
                    return checkbox.checked;
                }).length;

                if (selectedTotal === 0 && !window.confirm("Phim này sẽ không thuộc thể loại nào. Bạn vẫn muốn lưu thay đổi?")) {
                    event.preventDefault();
                }
            });

            syncGenreState();
        })();
    </script>
</body>
</html>
