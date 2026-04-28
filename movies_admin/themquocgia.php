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

if (!function_exists("admin_country_url")) {
    function admin_country_url($params = array())
    {
        $query = array_merge(array("page_layout" => "themquocgia"), $params);

        foreach ($query as $key => $value) {
            if ($value === "" || $value === null) {
                unset($query[$key]);
            }
        }

        return "index.php?" . http_build_query($query);
    }
}

$adminName = !empty($_SESSION["fullname"]) ? $_SESSION["fullname"] : "Admin Director";
$keyword = isset($_GET["keyword"]) ? trim((string) $_GET["keyword"]) : "";
$page = isset($_GET["page"]) ? max(1, (int) $_GET["page"]) : 1;
$modal = isset($_GET["modal"]) ? trim((string) $_GET["modal"]) : "";
$countryId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$perPage = 10;

if ($modal !== "add" && $modal !== "edit") {
    $modal = "";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = isset($_POST["country_action"]) ? trim((string) $_POST["country_action"]) : "";
    $returnKeyword = isset($_POST["return_keyword"]) ? trim((string) $_POST["return_keyword"]) : "";
    $returnPage = isset($_POST["return_page"]) ? max(1, (int) $_POST["return_page"]) : 1;

    if ($action === "create" || $action === "update") {
        $name = isset($_POST["txtTenQuocGia"]) ? trim((string) $_POST["txtTenQuocGia"]) : "";
        $targetId = isset($_POST["country_id"]) ? (int) $_POST["country_id"] : 0;
        $redirectParams = array(
            "keyword" => $returnKeyword,
            "page" => $returnPage,
            "modal" => $action === "create" ? "add" : "edit",
            "id" => $targetId,
        );

        if ($name === "") {
            header("Location: " . admin_country_url(array_merge($redirectParams, array("status" => "error"))));
            exit();
        }

        if ($action === "create") {
            $statement = $connect->prepare("INSERT INTO country (country_name) VALUES (?)");
            $ok = $statement && $statement->bind_param("s", $name) && $statement->execute();

            if ($statement) {
                $statement->close();
            }

            if ($ok) {
                header("Location: " . admin_country_url(array(
                    "keyword" => $returnKeyword,
                    "page" => 1,
                    "status" => "created",
                )));
                exit();
            }
        } else {
            if ($targetId <= 0) {
                header("Location: " . admin_country_url(array_merge($redirectParams, array("status" => "error"))));
                exit();
            }

            $exists = (int) admin_fetch_scalar_prepared(
                $connect,
                "SELECT COUNT(*) AS total FROM country WHERE country_id = ?",
                "total",
                "i",
                array($targetId),
                0
            );

            if ($exists <= 0) {
                header("Location: " . admin_country_url(array_merge($redirectParams, array("status" => "error"))));
                exit();
            }

            $statement = $connect->prepare("UPDATE country SET country_name = ? WHERE country_id = ?");
            $ok = $statement && $statement->bind_param("si", $name, $targetId) && $statement->execute();

            if ($statement) {
                $statement->close();
            }

            if ($ok) {
                header("Location: " . admin_country_url(array(
                    "keyword" => $returnKeyword,
                    "page" => $returnPage,
                    "status" => "updated",
                )));
                exit();
            }
        }

        header("Location: " . admin_country_url(array_merge($redirectParams, array("status" => "error"))));
        exit();
    }

    if ($action === "delete") {
        $targetId = isset($_POST["country_id"]) ? (int) $_POST["country_id"] : 0;
        if ($targetId <= 0) {
            header("Location: " . admin_country_url(array(
                "keyword" => $returnKeyword,
                "page" => $returnPage,
                "status" => "error",
            )));
            exit();
        }

        $movieCount = (int) admin_fetch_scalar_prepared(
            $connect,
            "SELECT COUNT(*) AS total FROM movies WHERE country_id = ?",
            "total",
            "i",
            array($targetId),
            0
        );

        if ($movieCount > 0) {
            header("Location: " . admin_country_url(array(
                "keyword" => $returnKeyword,
                "page" => $returnPage,
                "status" => "blocked",
            )));
            exit();
        }

        $statement = $connect->prepare("DELETE FROM country WHERE country_id = ?");
        $ok = $statement && $statement->bind_param("i", $targetId) && $statement->execute();

        if ($statement) {
            $statement->close();
        }

        header("Location: " . admin_country_url(array(
            "keyword" => $returnKeyword,
            "page" => $returnPage,
            "status" => $ok ? "deleted" : "error",
        )));
        exit();
    }
}

$status = isset($_GET["status"]) ? trim((string) $_GET["status"]) : "";
$statusMessage = "";
$statusClass = "";

if ($status === "created") {
    $statusMessage = "Đã thêm quốc gia mới vào hệ thống.";
    $statusClass = "mapping-toast-success";
} elseif ($status === "updated") {
    $statusMessage = "Đã cập nhật quốc gia.";
    $statusClass = "mapping-toast-success";
} elseif ($status === "deleted") {
    $statusMessage = "Đã xóa quốc gia khỏi hệ thống.";
    $statusClass = "mapping-toast-success";
} elseif ($status === "blocked") {
    $statusMessage = "Không thể xóa quốc gia đang được sử dụng bởi phim.";
    $statusClass = "mapping-toast-error";
} elseif ($status === "error") {
    $statusMessage = "Không thể xử lý thao tác quốc gia. Vui lòng thử lại.";
    $statusClass = "mapping-toast-error";
}

$whereClause = "";
$types = "";
$params = array();

if ($keyword !== "") {
    $whereClause = " WHERE c.country_name LIKE ?";
    $types = "s";
    $params[] = "%" . $keyword . "%";
}

$totalCountries = (int) admin_fetch_scalar_prepared(
    $connect,
    "SELECT COUNT(*) AS total FROM country c" . $whereClause,
    "total",
    $types,
    $params,
    0
);
$totalMovies = (int) admin_fetch_scalar_prepared(
    $connect,
    "SELECT COUNT(*) AS total FROM movies",
    "total",
    "",
    array(),
    0
);

$popularCountry = admin_fetch_one_prepared(
    $connect,
    "SELECT c.country_id, c.country_name, COUNT(m.movie_id) AS movie_total
     FROM country c
     LEFT JOIN movies m ON m.country_id = c.country_id
     GROUP BY c.country_id, c.country_name
     ORDER BY movie_total DESC, c.country_id ASC
     LIMIT 1"
);

$totalPages = max(1, (int) ceil($totalCountries / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;
$listTypes = $types . "ii";
$listParams = $params;
$listParams[] = $perPage;
$listParams[] = $offset;

$countries = admin_fetch_rows_prepared(
    $connect,
    "SELECT
        c.country_id,
        c.country_name,
        COUNT(m.movie_id) AS movie_total
     FROM country c
     LEFT JOIN movies m ON m.country_id = c.country_id" . $whereClause . "
     GROUP BY c.country_id, c.country_name
     ORDER BY c.country_id ASC
     LIMIT ? OFFSET ?",
    $listTypes,
    $listParams
);

$editingCountry = null;
if ($modal === "edit" && $countryId > 0) {
    $editingCountry = admin_fetch_one_prepared(
        $connect,
        "SELECT country_id, country_name FROM country WHERE country_id = ? LIMIT 1",
        "i",
        array($countryId)
    );

    if (!$editingCountry) {
        $modal = "";
    }
}

$displayStart = $totalCountries > 0 ? ($offset + 1) : 0;
$displayEnd = $totalCountries > 0 ? min($totalCountries, $offset + count($countries)) : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý quốc gia | ITMOVIES Admin</title>
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
                <a class="admin-nav-item is-active has-indicator" href="index.php?page_layout=themquocgia">
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

        <main class="admin-main country-page">
            <?php if ($statusMessage !== "") { ?>
                <div class="mapping-toast <?php echo admin_escape($statusClass); ?>" data-mapping-toast>
                    <?php echo admin_escape($statusMessage); ?>
                </div>
            <?php } ?>

            <header class="admin-topbar">
                <form class="admin-search admin-search-wide" action="index.php" method="get">
                    <input type="hidden" name="page_layout" value="themquocgia">
                    <span class="admin-search-icon">&#9906;</span>
                    <input type="search" name="keyword" value="<?php echo admin_escape($keyword); ?>" placeholder="Tìm kiếm quốc gia...">
                </form>

                <div class="admin-topbar-actions">
                    <a class="admin-primary-btn" href="<?php echo admin_escape(admin_country_url(array(
                        "keyword" => $keyword,
                        "page" => $page,
                        "modal" => "add",
                    ))); ?>">
                        <span>+</span>
                        <span>Thêm mới</span>
                    </a>
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

            <section class="country-header">
                <div>
                    <h1>Quản lý quốc gia</h1>
                    <p>Quản lý danh mục quốc gia sản xuất phim trong hệ thống.</p>
                </div>
            </section>

            <section class="country-stats">
                <article class="account-summary-card admin-card">
                    <div class="account-summary-copy">
                        <h1>Danh mục quốc gia</h1>
                        <p>Dữ liệu được dùng trực tiếp cho lựa chọn quốc gia khi thêm và sửa phim.</p>
                    </div>
                    <div class="account-metrics">
                        <div class="account-metric">
                            <span class="account-metric-icon account-metric-icon-rose">&#127760;</span>
                            <div>
                                <p class="account-metric-label">Tổng quốc gia</p>
                                <strong><?php echo admin_escape(number_format($totalCountries, 0, ",", ".")); ?></strong>
                            </div>
                        </div>
                        <div class="account-metric">
                            <span class="account-metric-icon account-metric-icon-blue">&#127916;</span>
                            <div>
                                <p class="account-metric-label">Phim đã gắn</p>
                                <strong><?php echo admin_escape(number_format($totalMovies, 0, ",", ".")); ?></strong>
                            </div>
                        </div>
                        <div class="account-metric">
                            <span class="account-metric-icon account-metric-icon-green">&#9733;</span>
                            <div>
                                <p class="account-metric-label">Nổi bật nhất</p>
                                <strong><?php echo $popularCountry ? admin_escape($popularCountry["country_name"]) : "&mdash;"; ?></strong>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            <section class="country-table-card admin-card">
                <div class="mapping-card-head">
                    <div>
                        <h2>Danh sách quốc gia</h2>
                        <p>Hiển thị <?php echo admin_escape($displayStart); ?> - <?php echo admin_escape($displayEnd); ?> trên tổng số <?php echo admin_escape(number_format($totalCountries, 0, ",", ".")); ?> quốc gia.</p>
                    </div>
                    <div class="account-result-meta">
                        <?php echo $popularCountry ? admin_escape($popularCountry["country_name"] . " đang có " . $popularCountry["movie_total"] . " phim.") : "Chưa có dữ liệu nổi bật."; ?>
                    </div>
                </div>

                <?php if (empty($countries)) { ?>
                    <div class="admin-empty-state">Không có quốc gia nào phù hợp với bộ lọc hiện tại.</div>
                <?php } else { ?>
                    <div class="country-table-scroll">
                        <table class="country-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Quốc gia</th>
                                    <th>Mã ISO</th>
                                    <th>Số lượng phim</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($countries as $country) { ?>
                                    <tr>
                                        <td class="account-id-cell">#C-<?php echo (int) $country["country_id"]; ?></td>
                                        <td>
                                            <div class="country-name-cell">
                                                <span class="country-avatar"><?php echo admin_escape(admin_initials($country["country_name"])); ?></span>
                                                <span><?php echo admin_escape($country["country_name"]); ?></span>
                                            </div>
                                        </td>
                                        <td class="account-email-cell">&mdash;</td>
                                        <td>
                                            <span class="country-count-pill"><?php echo (int) $country["movie_total"]; ?> phim</span>
                                        </td>
                                        <td>
                                            <div class="account-actions">
                                                <a class="country-action-button" href="<?php echo admin_escape(admin_country_url(array(
                                                    "keyword" => $keyword,
                                                    "page" => $page,
                                                    "modal" => "edit",
                                                    "id" => (int) $country["country_id"],
                                                ))); ?>">Sửa</a>
                                                <form action="<?php echo admin_escape(admin_country_url(array(
                                                    "keyword" => $keyword,
                                                    "page" => $page,
                                                ))); ?>" method="post" onsubmit="return confirm('Bạn có chắc chắn muốn xóa quốc gia này không?')">
                                                    <input type="hidden" name="country_action" value="delete">
                                                    <input type="hidden" name="country_id" value="<?php echo (int) $country["country_id"]; ?>">
                                                    <input type="hidden" name="return_keyword" value="<?php echo admin_escape($keyword); ?>">
                                                    <input type="hidden" name="return_page" value="<?php echo (int) $page; ?>">
                                                    <button class="country-action-button country-action-button-danger" type="submit">Xóa</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </section>

            <?php if ($totalPages > 1) { ?>
                <nav class="account-pagination" aria-label="Điều hướng phân trang quốc gia">
                    <?php if ($page > 1) { ?>
                        <a class="account-page-button" href="<?php echo admin_escape(admin_country_url(array(
                            "keyword" => $keyword,
                            "page" => $page - 1,
                        ))); ?>">&lsaquo;</a>
                    <?php } ?>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++) {
                    ?>
                        <a class="account-page-button<?php echo $pageNumber === $page ? " is-active" : ""; ?>" href="<?php echo admin_escape(admin_country_url(array(
                            "keyword" => $keyword,
                            "page" => $pageNumber,
                        ))); ?>">
                            <?php echo (int) $pageNumber; ?>
                        </a>
                    <?php } ?>

                    <?php if ($page < $totalPages) { ?>
                        <a class="account-page-button" href="<?php echo admin_escape(admin_country_url(array(
                            "keyword" => $keyword,
                            "page" => $page + 1,
                        ))); ?>">&rsaquo;</a>
                    <?php } ?>
                </nav>
            <?php } ?>
        </main>
    </div>

    <?php if ($modal === "add" || ($modal === "edit" && $editingCountry)) { ?>
        <div class="country-modal-overlay">
            <div class="country-modal">
                <div class="country-modal-header">
                    <h2><?php echo $modal === "edit" ? "Sửa quốc gia" : "Thêm quốc gia"; ?></h2>
                    <a href="<?php echo admin_escape(admin_country_url(array(
                        "keyword" => $keyword,
                        "page" => $page,
                    ))); ?>" aria-label="Đóng modal">&times;</a>
                </div>

                <form action="<?php echo admin_escape(admin_country_url(array(
                    "keyword" => $keyword,
                    "page" => $page,
                    "modal" => $modal,
                    "id" => $editingCountry ? (int) $editingCountry["country_id"] : 0,
                ))); ?>" method="post">
                    <div class="country-modal-body">
                        <input type="hidden" name="country_action" value="<?php echo $modal === "edit" ? "update" : "create"; ?>">
                        <input type="hidden" name="country_id" value="<?php echo $editingCountry ? (int) $editingCountry["country_id"] : 0; ?>">
                        <input type="hidden" name="return_keyword" value="<?php echo admin_escape($keyword); ?>">
                        <input type="hidden" name="return_page" value="<?php echo (int) $page; ?>">

                        <label class="admin-field">
                            <span>TÊN QUỐC GIA (TIẾNG VIỆT)</span>
                            <input type="text" name="txtTenQuocGia" placeholder="Nhập tên quốc gia..." value="<?php echo $editingCountry ? admin_escape($editingCountry["country_name"]) : ""; ?>" required>
                        </label>

                        <label class="admin-field">
                            <span>MÃ QUỐC GIA (ISO)</span>
                            <input type="text" value="" placeholder="Ví dụ: VN, US, KR..." disabled>
                        </label>

                        <div class="country-upload-field">
                            <span>CỜ QUỐC GIA</span>
                            <div class="country-upload-box">
                                <strong>Kéo thả ảnh hoặc <span>chọn tệp</span></strong>
                                <small>Hỗ trợ JPG, PNG (Tối đa 2MB)</small>
                            </div>
                        </div>
                    </div>

                    <div class="country-modal-footer">
                        <a class="admin-secondary-btn country-cancel-button" href="<?php echo admin_escape(admin_country_url(array(
                            "keyword" => $keyword,
                            "page" => $page,
                        ))); ?>">Hủy</a>
                        <button class="admin-primary-btn" type="submit"><?php echo $modal === "edit" ? "Lưu thay đổi" : "Lưu quốc gia"; ?></button>
                    </div>
                </form>
            </div>
        </div>
    <?php } ?>

    <script>
        (function () {
            var toast = document.querySelector("[data-mapping-toast]");
            if (toast) {
                window.setTimeout(function () {
                    toast.classList.add("is-hidden");
                }, 2600);
            }
        })();
    </script>
</body>
</html>
