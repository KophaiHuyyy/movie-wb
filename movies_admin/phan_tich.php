<?php
include_once "checkpermission.php";
include_once "cauhinh.php";

function analytics_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function analytics_format_number($value)
{
    return number_format((float) $value, 0, ",", ".");
}

function analytics_format_compact($value)
{
    $number = (float) $value;

    if ($number >= 1000000) {
        return rtrim(rtrim(number_format($number / 1000000, 1, ".", ""), "0"), ".") . "M";
    }

    if ($number >= 1000) {
        return rtrim(rtrim(number_format($number / 1000, 1, ".", ""), "0"), ".") . "K";
    }

    return analytics_format_number($number);
}

function analytics_initials($text)
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

function analytics_movie_poster($filename)
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

function analytics_bind_and_execute($stmt, $types, $params)
{
    if ($types !== "") {
        $bindParams = array($types);
        foreach ($params as $index => $value) {
            $bindParams[] = &$params[$index];
        }
        call_user_func_array(array($stmt, "bind_param"), $bindParams);
    }

    $stmt->execute();
}

function analytics_fetch_rows($connect, $sql, $types = "", $params = array())
{
    $rows = array();
    $stmt = $connect->prepare($sql);

    if (!$stmt) {
        return $rows;
    }

    analytics_bind_and_execute($stmt, $types, $params);
    $result = $stmt->get_result();

    while ($result && $row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    if ($result) {
        $result->free();
    }

    $stmt->close();

    return $rows;
}

function analytics_fetch_one($connect, $sql, $types = "", $params = array())
{
    $stmt = $connect->prepare($sql);

    if (!$stmt) {
        return null;
    }

    analytics_bind_and_execute($stmt, $types, $params);
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    if ($result) {
        $result->free();
    }

    $stmt->close();

    return $row;
}

function analytics_percent_change($current, $previous)
{
    $current = (float) $current;
    $previous = (float) $previous;

    if ($previous <= 0) {
        return null;
    }

    return (($current - $previous) / $previous) * 100;
}

function analytics_change_label($change)
{
    if ($change === null) {
        return "Mốc đầu";
    }

    $prefix = $change >= 0 ? "+" : "";
    return $prefix . number_format($change, 1, ",", ".") . "%";
}

function analytics_status_meta($saveTotal)
{
    $saveTotal = (int) $saveTotal;

    if ($saveTotal >= 5) {
        return array("label" => "Rất tích cực", "class" => "is-hot");
    }

    if ($saveTotal >= 3) {
        return array("label" => "Ổn định", "class" => "is-steady");
    }

    if ($saveTotal >= 2) {
        return array("label" => "Đang theo dõi", "class" => "is-warm");
    }

    return array("label" => "Mới hoạt động", "class" => "is-new");
}

function analytics_build_path($values)
{
    $count = count($values);

    if ($count === 0) {
        return array("line" => "", "area" => "", "points" => array(), "max" => 0);
    }

    $maxValue = max($values);
    if ($maxValue <= 0) {
        $maxValue = 1;
    }

    $points = array();
    for ($index = 0; $index < $count; $index++) {
        $x = $count > 1 ? ($index * (100 / ($count - 1))) : 50;
        $y = 100 - (($values[$index] / $maxValue) * 100);
        $points[] = array(
            "x" => $x,
            "y" => $y,
            "value" => $values[$index],
        );
    }

    $segments = array();
    foreach ($points as $pointIndex => $point) {
        $segments[] = ($pointIndex === 0 ? "M" : "L")
            . " " . number_format($point["x"], 2, ".", "")
            . " " . number_format($point["y"], 2, ".", "");
    }

    $line = implode(" ", $segments);
    $area = $line . " L 100 100 L 0 100 Z";

    return array(
        "line" => $line,
        "area" => $area,
        "points" => $points,
        "max" => $maxValue,
    );
}

$rangeOptions = array(7, 30, 90);
$selectedRange = isset($_GET["range"]) ? (int) $_GET["range"] : 30;
$selectedRange = in_array($selectedRange, $rangeOptions, true) ? $selectedRange : 30;
$keyword = isset($_GET["keyword"]) ? trim((string) $_GET["keyword"]) : "";
$exportMode = isset($_GET["export"]) ? trim((string) $_GET["export"]) : "";

$adminName = !empty($_SESSION["fullname"]) ? $_SESSION["fullname"] : (!empty($_SESSION["username"]) ? $_SESSION["username"] : "Admin");
$adminRole = !empty($_SESSION["role"]) && (int) $_SESSION["role"] === 1 ? "Administrator" : "Moderator";

$anchorRow = analytics_fetch_one(
    $connect,
    "SELECT COALESCE(MAX(added_date), NOW()) AS latest_added_date FROM watchlist"
);

$anchorDate = !empty($anchorRow["latest_added_date"]) ? new DateTime($anchorRow["latest_added_date"]) : new DateTime();
$rangeEnd = clone $anchorDate;
$rangeEnd->setTime(23, 59, 59);
$rangeStart = clone $anchorDate;
$rangeStart->setTime(0, 0, 0);
$rangeStart->modify("-" . ($selectedRange - 1) . " days");

$previousRangeEnd = clone $rangeStart;
$previousRangeEnd->modify("-1 second");
$previousRangeStart = clone $previousRangeEnd;
$previousRangeStart->setTime(0, 0, 0);
$previousRangeStart->modify("-" . ($selectedRange - 1) . " days");

$currentStartSql = $rangeStart->format("Y-m-d H:i:s");
$currentEndSql = $rangeEnd->format("Y-m-d H:i:s");
$previousStartSql = $previousRangeStart->format("Y-m-d H:i:s");
$previousEndSql = $previousRangeEnd->format("Y-m-d H:i:s");

$keywordLike = "%" . $keyword . "%";
$keywordClause = "";
$keywordTypes = "";
$keywordParams = array();

if ($keyword !== "") {
    $keywordClause = " AND (m.title LIKE ? OR COALESCE(u.fullname, '') LIKE ? OR COALESCE(u.username, '') LIKE ?)";
    $keywordTypes = "sss";
    $keywordParams = array($keywordLike, $keywordLike, $keywordLike);
}

$summarySql = "
    SELECT
        COUNT(*) AS total_watchlist,
        COUNT(DISTINCT w.user_id) AS active_users,
        COUNT(DISTINCT w.movie_id) AS unique_movies
    FROM watchlist w
    INNER JOIN movies m ON w.movie_id = m.movie_id
    INNER JOIN users u ON w.user_id = u.user_id
    WHERE w.added_date BETWEEN ? AND ?
    " . $keywordClause;
$summaryParams = array_merge(array($currentStartSql, $currentEndSql), $keywordParams);
$summaryTypes = "ss" . $keywordTypes;
$summaryRow = analytics_fetch_one($connect, $summarySql, $summaryTypes, $summaryParams);

$previousSummaryParams = array_merge(array($previousStartSql, $previousEndSql), $keywordParams);
$previousSummaryRow = analytics_fetch_one($connect, $summarySql, $summaryTypes, $previousSummaryParams);

$chartSql = "
    SELECT
        DATE(w.added_date) AS chart_day,
        COUNT(*) AS save_total,
        COUNT(DISTINCT w.user_id) AS user_total
    FROM watchlist w
    INNER JOIN movies m ON w.movie_id = m.movie_id
    INNER JOIN users u ON w.user_id = u.user_id
    WHERE w.added_date BETWEEN ? AND ?
    " . $keywordClause . "
    GROUP BY DATE(w.added_date)
    ORDER BY DATE(w.added_date) ASC
";
$chartRows = analytics_fetch_rows($connect, $chartSql, $summaryTypes, $summaryParams);

$movieSql = "
    SELECT
        m.movie_id,
        m.title,
        m.img,
        COALESCE(m.language, 'Đang cập nhật') AS language,
        COALESCE(GROUP_CONCAT(DISTINCT g.ten_theloai ORDER BY g.ten_theloai SEPARATOR ', '), 'Chưa phân loại') AS genre_names,
        COUNT(*) AS save_total
    FROM watchlist w
    INNER JOIN movies m ON w.movie_id = m.movie_id
    INNER JOIN users u ON w.user_id = u.user_id
    LEFT JOIN movie_genre mg ON m.movie_id = mg.movie_id
    LEFT JOIN genres g ON mg.theloai_id = g.theloai_id
    WHERE w.added_date BETWEEN ? AND ?
    " . $keywordClause . "
    GROUP BY m.movie_id, m.title, m.img, m.language
    ORDER BY save_total DESC, m.movie_id DESC
    LIMIT 4
";
$topMovies = analytics_fetch_rows($connect, $movieSql, $summaryTypes, $summaryParams);

$userSql = "
    SELECT
        u.user_id,
        u.fullname,
        u.username,
        u.role,
        COUNT(*) AS save_total,
        COUNT(DISTINCT w.movie_id) AS unique_movies,
        MAX(w.added_date) AS last_added_at
    FROM watchlist w
    INNER JOIN movies m ON w.movie_id = m.movie_id
    INNER JOIN users u ON w.user_id = u.user_id
    WHERE w.added_date BETWEEN ? AND ?
    " . $keywordClause . "
    GROUP BY u.user_id, u.fullname, u.username, u.role
    ORDER BY save_total DESC, unique_movies DESC, COALESCE(last_added_at, '1000-01-01 00:00:00') DESC
    LIMIT 5
";
$topUsers = analytics_fetch_rows($connect, $userSql, $summaryTypes, $summaryParams);

$totalWatchlist = isset($summaryRow["total_watchlist"]) ? (int) $summaryRow["total_watchlist"] : 0;
$activeUsers = isset($summaryRow["active_users"]) ? (int) $summaryRow["active_users"] : 0;
$uniqueMovies = isset($summaryRow["unique_movies"]) ? (int) $summaryRow["unique_movies"] : 0;
$previousWatchlist = isset($previousSummaryRow["total_watchlist"]) ? (int) $previousSummaryRow["total_watchlist"] : 0;
$previousActiveUsers = isset($previousSummaryRow["active_users"]) ? (int) $previousSummaryRow["active_users"] : 0;
$watchlistChange = analytics_percent_change($totalWatchlist, $previousWatchlist);
$activeUsersChange = analytics_percent_change($activeUsers, $previousActiveUsers);

$chartMap = array();
foreach ($chartRows as $row) {
    $chartMap[$row["chart_day"]] = array(
        "save_total" => isset($row["save_total"]) ? (int) $row["save_total"] : 0,
        "user_total" => isset($row["user_total"]) ? (int) $row["user_total"] : 0,
    );
}

$chartLabels = array();
$saveSeries = array();
$userSeries = array();
$chartStart = clone $rangeStart;
$chartEnd = clone $rangeEnd;
$chartEnd->setTime(0, 0, 0);

while ($chartStart <= $chartEnd) {
    $mapKey = $chartStart->format("Y-m-d");
    $chartLabels[] = $chartStart->format($selectedRange <= 7 ? "D" : "d/m");
    $saveSeries[] = isset($chartMap[$mapKey]) ? (int) $chartMap[$mapKey]["save_total"] : 0;
    $userSeries[] = isset($chartMap[$mapKey]) ? (int) $chartMap[$mapKey]["user_total"] : 0;
    $chartStart->modify("+1 day");
}

$saveChart = analytics_build_path($saveSeries);
$userChart = analytics_build_path($userSeries);
$topMovieName = !empty($topMovies) ? $topMovies[0]["title"] : "";
$topMovieSaves = !empty($topMovies) ? (int) $topMovies[0]["save_total"] : 0;
$topUserName = !empty($topUsers)
    ? (!empty($topUsers[0]["fullname"]) ? $topUsers[0]["fullname"] : $topUsers[0]["username"])
    : "";
$topUserSaves = !empty($topUsers) ? (int) $topUsers[0]["save_total"] : 0;
$bestDayValue = !empty($saveSeries) ? max($saveSeries) : 0;
$bestDayIndex = array_search($bestDayValue, $saveSeries, true);
$bestDayLabel = $bestDayIndex !== false && isset($chartLabels[$bestDayIndex]) ? $chartLabels[$bestDayIndex] : "N/A";

$insightParts = array();
if ($bestDayValue > 0) {
    $insightParts[] = "Ngày cao điểm là " . $bestDayLabel . " với " . analytics_format_number($bestDayValue) . " lượt lưu.";
}
if ($topMovieName !== "") {
    $insightParts[] = "Phim nổi bật nhất là " . $topMovieName . ", đóng góp " . analytics_format_number($topMovieSaves) . " lượt.";
}
if ($topUserName !== "") {
    $insightParts[] = "Người dùng hoạt động mạnh nhất là " . $topUserName . " với " . analytics_format_number($topUserSaves) . " lượt thêm.";
}
if (empty($insightParts)) {
    $insightParts[] = "Khoảng thời gian này chưa có dữ liệu watchlist phù hợp để phân tích.";
}

if ($exportMode === "csv") {
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=phan-tich-watchlist-" . $selectedRange . "-ngay.csv");
    echo "\xEF\xBB\xBF";

    $output = fopen("php://output", "w");
    fputcsv($output, array("Phân tích Watchlist", $selectedRange . " ngày"));
    fputcsv($output, array("Mốc dữ liệu", $anchorDate->format("d/m/Y")));
    fputcsv($output, array("Từ khóa", $keyword !== "" ? $keyword : "Tất cả"));
    fputcsv($output, array());
    fputcsv($output, array("Tổng watchlist", $totalWatchlist));
    fputcsv($output, array("Người dùng hoạt động", $activeUsers));
    fputcsv($output, array("Phim được lưu", $uniqueMovies));
    fputcsv($output, array());
    fputcsv($output, array("Biến động theo ngày", "", ""));
    fputcsv($output, array("Ngày", "Lượt lưu", "Người dùng"));
    foreach ($chartLabels as $index => $label) {
        fputcsv($output, array($label, $saveSeries[$index], $userSeries[$index]));
    }
    fputcsv($output, array());
    fputcsv($output, array("Top phim", "", ""));
    fputcsv($output, array("Phim", "Thể loại", "Lượt lưu"));
    foreach ($topMovies as $movieRow) {
        fputcsv($output, array($movieRow["title"], $movieRow["genre_names"], $movieRow["save_total"]));
    }
    fputcsv($output, array());
    fputcsv($output, array("Top người dùng", "", ""));
    fputcsv($output, array("Người dùng", "Số lượt lưu", "Số phim"));
    foreach ($topUsers as $userRow) {
        $displayName = !empty($userRow["fullname"]) ? $userRow["fullname"] : $userRow["username"];
        fputcsv($output, array($displayName, $userRow["save_total"], $userRow["unique_movies"]));
    }
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITMOVIES Admin Analytics</title>
    <link rel="stylesheet" href="style_admin.css">
</head>
<body>
    <div class="admin-shell analytics-page-shell">
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
                <a class="admin-nav-item" href="index.php?page_layout=list_review">
                    <span class="admin-nav-icon">&#9733;</span>
                    <span>Đánh giá</span>
                </a>
                <a class="admin-nav-item is-active has-indicator" href="index.php?page_layout=phan_tich">
                    <span class="admin-nav-icon">&#128200;</span>
                    <span>Phân tích</span>
                </a>
            </nav>

            <div class="admin-sidebar-footer">
                <a class="admin-nav-item" href="index.php?page_layout=listfilm">
                    <span class="admin-nav-icon">&#9881;</span>
                    <span>Cài đặt</span>
                </a>
            </div>

            <a class="admin-logout" href="../users/logout.php">
                <span class="admin-nav-icon">&#8617;</span>
                <span>Đăng xuất</span>
            </a>
        </aside>

        <main class="admin-main analytics-main">
            <header class="admin-header analytics-header">
                <div class="admin-header-copy">
                    <p class="admin-kicker">Watchlist Analytics</p>
                    <h1>Phân tích Watchlist</h1>
                    <p class="admin-subtitle">Dữ liệu thật từ bảng watchlist, gom theo thời gian lưu gần nhất trong hệ thống để tránh trang trống khi dữ liệu seed đã cũ.</p>
                </div>

                <div class="analytics-header-actions">
                    <form class="admin-search analytics-search" method="get" action="index.php">
                        <input type="hidden" name="page_layout" value="phan_tich">
                        <input type="hidden" name="range" value="<?php echo (int) $selectedRange; ?>">
                        <span class="admin-search-icon">&#128269;</span>
                        <input type="search" name="keyword" value="<?php echo analytics_escape($keyword); ?>" placeholder="Tìm theo phim hoặc người dùng">
                    </form>

                    <div class="analytics-toolbar">
                        <div class="analytics-range-switch" role="tablist" aria-label="Bộ lọc thời gian">
                            <?php foreach ($rangeOptions as $rangeOption) { ?>
                                <a class="analytics-range-pill<?php echo $selectedRange === $rangeOption ? ' is-active' : ''; ?>" href="index.php?page_layout=phan_tich&range=<?php echo $rangeOption; ?><?php echo $keyword !== '' ? '&keyword=' . urlencode($keyword) : ''; ?>">
                                    <?php echo $rangeOption; ?> ngày
                                </a>
                            <?php } ?>
                        </div>
                        <a class="admin-secondary-btn" href="index.php?page_layout=phan_tich&range=<?php echo (int) $selectedRange; ?><?php echo $keyword !== '' ? '&keyword=' . urlencode($keyword) : ''; ?>&export=csv">Xuất báo cáo</a>
                        <a class="admin-primary-btn" href="index.php?page_layout=themmoi_film">Thêm mới</a>
                    </div>
                </div>
            </header>

            <section class="analytics-meta-row">
                <span class="analytics-meta-pill">Mốc dữ liệu: <?php echo analytics_escape($anchorDate->format("d/m/Y")); ?></span>
                <span class="analytics-meta-pill">Khoảng phân tích: <?php echo analytics_escape($rangeStart->format("d/m/Y")); ?> - <?php echo analytics_escape($rangeEnd->format("d/m/Y")); ?></span>
                <span class="analytics-meta-pill"><?php echo $keyword !== "" ? "Đang lọc: " . analytics_escape($keyword) : "Đang xem toàn bộ dữ liệu"; ?></span>
            </section>

            <section class="analytics-overview-grid">
                <article class="admin-card analytics-chart-card">
                    <div class="analytics-card-head">
                        <div>
                            <span class="analytics-icon-trend">&#128200;</span>
                            <h2>Thống kê hành vi</h2>
                        </div>
                        <div class="analytics-legend">
                            <span><i class="is-red"></i>Lượt lưu mới</span>
                            <span><i class="is-blue"></i>Người dùng hoạt động</span>
                        </div>
                    </div>

                    <div class="analytics-metric-strip">
                        <div>
                            <strong><?php echo analytics_format_number($totalWatchlist); ?></strong>
                            <span>Tổng lượt lưu trong kỳ</span>
                        </div>
                        <div>
                            <strong><?php echo analytics_format_number($activeUsers); ?></strong>
                            <span>Người dùng có hành vi lưu</span>
                        </div>
                        <div>
                            <strong><?php echo analytics_format_number($uniqueMovies); ?></strong>
                            <span>Đầu phim được thêm</span>
                        </div>
                    </div>

                    <div class="analytics-chart-frame">
                        <?php if (array_sum($saveSeries) > 0 || array_sum($userSeries) > 0) { ?>
                            <div class="analytics-chart-grid">
                                <span>Đỉnh</span>
                                <span>75%</span>
                                <span>50%</span>
                                <span>25%</span>
                            </div>
                            <svg viewBox="0 0 100 100" class="analytics-chart-svg" preserveAspectRatio="none" aria-label="Biểu đồ watchlist">
                                <defs>
                                    <linearGradient id="analytics-red-fill" x1="0" x2="0" y1="0" y2="1">
                                        <stop offset="0%" stop-color="rgba(229, 9, 20, 0.30)"></stop>
                                        <stop offset="100%" stop-color="rgba(229, 9, 20, 0.03)"></stop>
                                    </linearGradient>
                                    <linearGradient id="analytics-blue-fill" x1="0" x2="0" y1="0" y2="1">
                                        <stop offset="0%" stop-color="rgba(89, 142, 255, 0.24)"></stop>
                                        <stop offset="100%" stop-color="rgba(89, 142, 255, 0.02)"></stop>
                                    </linearGradient>
                                </defs>
                                <path class="analytics-area analytics-area-red" d="<?php echo analytics_escape($saveChart["area"]); ?>"></path>
                                <path class="analytics-area analytics-area-blue" d="<?php echo analytics_escape($userChart["area"]); ?>"></path>
                                <path class="analytics-line analytics-line-red" d="<?php echo analytics_escape($saveChart["line"]); ?>"></path>
                                <path class="analytics-line analytics-line-blue" d="<?php echo analytics_escape($userChart["line"]); ?>"></path>
                                <?php foreach ($saveChart["points"] as $point) { ?>
                                    <circle class="analytics-point analytics-point-red" cx="<?php echo analytics_escape(number_format($point["x"], 2, ".", "")); ?>" cy="<?php echo analytics_escape(number_format($point["y"], 2, ".", "")); ?>" r="1.3"></circle>
                                <?php } ?>
                                <?php foreach ($userChart["points"] as $point) { ?>
                                    <circle class="analytics-point analytics-point-blue" cx="<?php echo analytics_escape(number_format($point["x"], 2, ".", "")); ?>" cy="<?php echo analytics_escape(number_format($point["y"], 2, ".", "")); ?>" r="1.1"></circle>
                                <?php } ?>
                            </svg>
                        <?php } else { ?>
                            <div class="analytics-empty-chart">
                                <strong>Chưa có dữ liệu watchlist trong kỳ này</strong>
                                <p>Thử đổi khoảng thời gian hoặc bỏ bộ lọc để xem thêm tín hiệu hành vi.</p>
                            </div>
                        <?php } ?>
                    </div>

                    <div class="analytics-chart-labels">
                        <?php foreach ($chartLabels as $chartLabel) { ?>
                            <span><?php echo analytics_escape($chartLabel); ?></span>
                        <?php } ?>
                    </div>
                </article>

                <div class="analytics-side-column">
                    <article class="admin-card analytics-kpi-card analytics-kpi-card-primary">
                        <div class="analytics-kpi-icon">&#10003;</div>
                        <span class="analytics-kpi-change<?php echo $watchlistChange !== null && $watchlistChange < 0 ? ' is-down' : ''; ?>">
                            <?php echo analytics_escape(analytics_change_label($watchlistChange)); ?>
                        </span>
                        <p class="analytics-kpi-label">Tổng cộng Watchlist</p>
                        <strong><?php echo analytics_format_number($totalWatchlist); ?></strong>
                        <small>So với <?php echo $selectedRange; ?> ngày liền trước</small>
                    </article>

                    <article class="admin-card analytics-kpi-card">
                        <div class="analytics-kpi-icon analytics-kpi-icon-secondary">&#128101;</div>
                        <span class="analytics-kpi-change analytics-kpi-change-green<?php echo $activeUsersChange !== null && $activeUsersChange < 0 ? ' is-down' : ''; ?>">
                            <?php echo analytics_escape(analytics_change_label($activeUsersChange)); ?>
                        </span>
                        <p class="analytics-kpi-label">Người dùng hoạt động</p>
                        <strong><?php echo analytics_format_number($activeUsers); ?></strong>
                        <small><?php echo analytics_format_number($uniqueMovies); ?> phim được thêm vào danh sách</small>
                    </article>
                </div>
            </section>

            <section class="analytics-detail-grid">
                <div class="analytics-top-movies">
                    <div class="analytics-section-head">
                        <div>
                            <h2>Phim được thêm nhiều nhất</h2>
                            <p>Ưu tiên theo lượt lưu trong khoảng đã chọn.</p>
                        </div>
                        <a href="index.php?page_layout=listfilm">Xem tất cả</a>
                    </div>

                    <div class="analytics-movie-grid">
                        <?php if (empty($topMovies)) { ?>
                            <div class="admin-card analytics-empty-card">
                                <strong>Không có phim nào khớp bộ lọc hiện tại.</strong>
                                <p>Hãy đổi từ khóa hoặc khoảng thời gian để xem bảng xếp hạng.</p>
                            </div>
                        <?php } else { ?>
                            <?php foreach ($topMovies as $index => $movieRow) { ?>
                                <?php
                                $posterPath = analytics_movie_poster($movieRow["img"]);
                                $movieGenres = array_slice(array_map("trim", explode(",", (string) $movieRow["genre_names"])), 0, 2);
                                ?>
                                <article class="admin-card analytics-movie-card">
                                    <span class="analytics-rank-badge"><?php echo $index + 1; ?></span>
                                    <div class="analytics-movie-poster">
                                        <?php if ($posterPath !== "") { ?>
                                            <img src="<?php echo analytics_escape($posterPath); ?>" alt="<?php echo analytics_escape($movieRow["title"]); ?>">
                                        <?php } else { ?>
                                            <span><?php echo analytics_escape(analytics_initials($movieRow["title"])); ?></span>
                                        <?php } ?>
                                    </div>
                                    <div class="analytics-movie-copy">
                                        <h3><?php echo analytics_escape($movieRow["title"]); ?></h3>
                                        <p><?php echo analytics_escape(implode(" · ", $movieGenres)); ?></p>
                                        <strong><?php echo analytics_format_compact($movieRow["save_total"]); ?></strong>
                                        <small>Lượt thêm vào danh sách</small>
                                    </div>
                                </article>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>

                <div class="analytics-top-users">
                    <div class="analytics-section-head">
                        <div>
                            <h2>Top người dùng</h2>
                            <p>Xếp theo số lượt lưu và số đầu phim khác nhau.</p>
                        </div>
                    </div>

                    <div class="admin-card analytics-user-table-card">
                        <?php if (empty($topUsers)) { ?>
                            <div class="analytics-empty-card analytics-empty-card-compact">
                                <strong>Chưa có người dùng nào phát sinh lưu phim.</strong>
                            </div>
                        <?php } else { ?>
                            <div class="analytics-user-table-head">
                                <span>Người dùng</span>
                                <span>Số lượng phim</span>
                                <span>Trạng thái</span>
                            </div>
                            <div class="analytics-user-table-body">
                                <?php foreach ($topUsers as $userRow) { ?>
                                    <?php
                                    $displayName = !empty($userRow["fullname"]) ? $userRow["fullname"] : $userRow["username"];
                                    $statusMeta = analytics_status_meta($userRow["save_total"]);
                                    ?>
                                    <article class="analytics-user-row">
                                        <div class="analytics-user-identity">
                                            <span class="analytics-user-avatar"><?php echo analytics_escape(analytics_initials($displayName)); ?></span>
                                            <div>
                                                <strong><?php echo analytics_escape($displayName); ?></strong>
                                                <small><?php echo (int) $userRow["role"] === 1 ? "Administrator" : "Member"; ?></small>
                                            </div>
                                        </div>
                                        <div class="analytics-user-stats">
                                            <strong><?php echo analytics_format_number($userRow["save_total"]); ?></strong>
                                            <small><?php echo analytics_format_number($userRow["unique_movies"]); ?> đầu phim</small>
                                        </div>
                                        <div class="analytics-user-status <?php echo analytics_escape($statusMeta["class"]); ?>">
                                            <span class="analytics-status-dot"></span>
                                            <small><?php echo analytics_escape($statusMeta["label"]); ?></small>
                                        </div>
                                    </article>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </section>

            <section class="admin-card analytics-insight-card">
                <div class="analytics-insight-mark">&#128161;</div>
                <div class="analytics-insight-copy">
                    <h2>Gợi ý phân tích hành vi</h2>
                    <p><?php echo analytics_escape(implode(" ", $insightParts)); ?></p>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
