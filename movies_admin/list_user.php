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

if (!function_exists("admin_user_role_label")) {
    function admin_user_role_label($role)
    {
        return (int) $role === 1 ? "ADMIN" : "USER";
    }
}

if (!function_exists("admin_account_url")) {
    function admin_account_url($params = array())
    {
        $query = array_merge(array("page_layout" => "list_user"), $params);

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
$roleFilter = isset($_GET["role"]) ? trim((string) $_GET["role"]) : "all";
$statusFilter = isset($_GET["status"]) ? trim((string) $_GET["status"]) : "active";
$noticeStatus = isset($_GET["notice"]) ? trim((string) $_GET["notice"]) : "";
$page = isset($_GET["page"]) ? max(1, (int) $_GET["page"]) : 1;
$perPage = 10;

if ($roleFilter !== "all" && $roleFilter !== "0" && $roleFilter !== "1") {
    $roleFilter = "all";
}

$allowedStatuses = array("active", "all", "offline", "locked");
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = "active";
}

$flashMessage = "";
if ($noticeStatus === "created") {
    $flashMessage = "Đã tạo tài khoản mới.";
} elseif ($noticeStatus === "updated") {
    $flashMessage = "Đã cập nhật tài khoản.";
} elseif ($noticeStatus === "deleted") {
    $flashMessage = "Đã xóa tài khoản.";
}

$totalUsers = (int) admin_fetch_scalar_prepared(
    $connect,
    "SELECT COUNT(*) AS total FROM users",
    "total",
    "",
    array(),
    0
);
$adminUsers = (int) admin_fetch_scalar_prepared(
    $connect,
    "SELECT COUNT(*) AS total FROM users WHERE role = 1",
    "total",
    "",
    array(),
    0
);

$whereParts = array();
$types = "";
$params = array();

if ($keyword !== "") {
    $whereParts[] = "(u.fullname LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $searchValue = "%" . $keyword . "%";
    $types .= "sss";
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($roleFilter === "0" || $roleFilter === "1") {
    $whereParts[] = "u.role = ?";
    $types .= "i";
    $params[] = (int) $roleFilter;
}

$whereClause = !empty($whereParts) ? " WHERE " . implode(" AND ", $whereParts) : "";

$filteredTotal = (int) admin_fetch_scalar_prepared(
    $connect,
    "SELECT COUNT(*) AS total FROM users u" . $whereClause,
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

$users = admin_fetch_rows_prepared(
    $connect,
    "SELECT
        u.user_id,
        u.username,
        u.email,
        u.fullname,
        COALESCE(u.role, 0) AS role,
        (
            SELECT COUNT(*)
            FROM reviews r
            WHERE r.user_id = u.user_id
        ) AS reviews_count,
        (
            SELECT COUNT(*)
            FROM watchlist w
            WHERE w.user_id = u.user_id
        ) AS watchlist_count
     FROM users u" . $whereClause . "
     ORDER BY COALESCE(u.role, 0) DESC, u.user_id ASC
     LIMIT ? OFFSET ?",
    $listTypes,
    $listParams
);

$displayStart = $filteredTotal > 0 ? ($offset + 1) : 0;
$displayEnd = $filteredTotal > 0 ? min($filteredTotal, $offset + count($users)) : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Người dùng | ITMOVIES Admin</title>
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
                <a class="admin-nav-item is-active has-indicator" href="index.php?page_layout=list_user">
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

        <main class="admin-main account-page">
            <header class="admin-topbar account-topbar">
                <div class="account-topbar-title">
                    <p>Người dùng</p>
                </div>

                <form class="admin-search admin-search-wide" action="index.php" method="get">
                    <input type="hidden" name="page_layout" value="list_user">
                    <?php if ($roleFilter !== "all") { ?>
                        <input type="hidden" name="role" value="<?php echo admin_escape($roleFilter); ?>">
                    <?php } ?>
                    <?php if ($statusFilter !== "active") { ?>
                        <input type="hidden" name="status" value="<?php echo admin_escape($statusFilter); ?>">
                    <?php } ?>
                    <span class="admin-search-icon">&#9906;</span>
                    <input type="search" name="keyword" value="<?php echo admin_escape($keyword); ?>" placeholder="Tìm kiếm tài khoản...">
                </form>

                <div class="admin-topbar-actions">
                    <a class="admin-primary-btn" href="index.php?page_layout=them_user">
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

            <section class="account-overview-grid">
                <article class="account-summary-card admin-card">
                    <div class="account-summary-copy">
                        <h1>Quản lý tài khoản</h1>
                        <p>Theo dõi và phân quyền truy cập cho hệ thống ITMOVIES.</p>
                    </div>

                    <div class="account-metrics">
                        <div class="account-metric">
                            <span class="account-metric-icon account-metric-icon-rose">&#128101;</span>
                            <div>
                                <p class="account-metric-label">Tổng người dùng</p>
                                <strong><?php echo admin_escape(number_format($totalUsers, 0, ",", ".")); ?></strong>
                            </div>
                        </div>
                        <div class="account-metric">
                            <span class="account-metric-icon account-metric-icon-blue">&#128737;</span>
                            <div>
                                <p class="account-metric-label">Quản trị viên</p>
                                <strong><?php echo admin_escape(number_format($adminUsers, 0, ",", ".")); ?></strong>
                            </div>
                        </div>
                        <div class="account-metric">
                            <span class="account-metric-icon account-metric-icon-green">&#9679;</span>
                            <div>
                                <p class="account-metric-label">Trực tuyến</p>
                                <strong>&mdash;</strong>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="permission-card admin-card">
                    <div>
                        <h2>Phân quyền</h2>
                        <p>Cài đặt bảo mật hệ thống</p>
                    </div>
                    <button class="security-button" type="button">Cấu hình bảo mật</button>
                </article>
            </section>

            <?php if ($flashMessage !== "") { ?>
                <div class="account-form-alert account-form-alert-success">
                    <?php echo admin_escape($flashMessage); ?>
                </div>
            <?php } ?>

            <section class="account-filter-row">
                <form class="account-filter-bar" action="index.php" method="get">
                    <input type="hidden" name="page_layout" value="list_user">
                    <?php if ($keyword !== "") { ?>
                        <input type="hidden" name="keyword" value="<?php echo admin_escape($keyword); ?>">
                    <?php } ?>
                    <?php if ($statusFilter !== "active") { ?>
                        <input type="hidden" name="status" value="<?php echo admin_escape($statusFilter); ?>">
                    <?php } ?>

                    <label class="account-filter-select">
                        <span>Lọc theo Quyền</span>
                        <select name="role" onchange="this.form.submit()">
                            <option value="all" <?php echo $roleFilter === "all" ? "selected" : ""; ?>>Tất cả</option>
                            <option value="1" <?php echo $roleFilter === "1" ? "selected" : ""; ?>>Admin</option>
                            <option value="0" <?php echo $roleFilter === "0" ? "selected" : ""; ?>>User</option>
                        </select>
                    </label>
                </form>

                <form class="account-filter-bar" action="index.php" method="get">
                    <input type="hidden" name="page_layout" value="list_user">
                    <?php if ($keyword !== "") { ?>
                        <input type="hidden" name="keyword" value="<?php echo admin_escape($keyword); ?>">
                    <?php } ?>
                    <?php if ($roleFilter !== "all") { ?>
                        <input type="hidden" name="role" value="<?php echo admin_escape($roleFilter); ?>">
                    <?php } ?>

                    <label class="account-filter-select">
                        <span>Trạng thái</span>
                        <select name="status" onchange="this.form.submit()">
                            <option value="active" <?php echo $statusFilter === "active" ? "selected" : ""; ?>>Đang hoạt động</option>
                            <option value="all" <?php echo $statusFilter === "all" ? "selected" : ""; ?>>Tất cả</option>
                            <option value="offline" <?php echo $statusFilter === "offline" ? "selected" : ""; ?>>Ngoại tuyến</option>
                            <option value="locked" <?php echo $statusFilter === "locked" ? "selected" : ""; ?>>Khóa tạm thời</option>
                        </select>
                    </label>
                </form>

                <div class="account-result-meta">
                    Hiển thị <?php echo admin_escape($displayStart); ?> - <?php echo admin_escape($displayEnd); ?> trong số <?php echo admin_escape(number_format($filteredTotal, 0, ",", ".")); ?> người dùng
                </div>
            </section>

            <section class="account-table-card admin-card">
                <?php if (empty($users)) { ?>
                    <div class="admin-empty-state">Không tìm thấy tài khoản phù hợp với bộ lọc hiện tại.</div>
                <?php } else { ?>
                    <div class="account-table-scroll">
                        <table class="account-table">
                            <thead>
                                <tr>
                                    <th>Avatar</th>
                                    <th>ID</th>
                                    <th>Họ tên</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Quyền</th>
                                    <th>Trạng thái</th>
                                    <th>Đánh giá</th>
                                    <th>Watchlist</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user) { ?>
                                    <?php
                                    $displayName = trim((string) $user["fullname"]) !== "" ? $user["fullname"] : $user["username"];
                                    $roleValue = isset($user["role"]) ? (int) $user["role"] : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="user-avatar user-avatar-<?php echo $roleValue === 1 ? "admin" : "user"; ?>">
                                                <?php echo admin_escape(admin_initials($displayName)); ?>
                                            </div>
                                        </td>
                                        <td class="account-id-cell">#ITM-<?php echo (int) $user["user_id"]; ?></td>
                                        <td class="account-name-cell"><?php echo admin_escape($displayName); ?></td>
                                        <td class="account-username-cell">@<?php echo admin_escape($user["username"]); ?></td>
                                        <td class="account-email-cell"><?php echo admin_escape($user["email"]); ?></td>
                                        <td>
                                            <span class="role-badge role-badge-<?php echo $roleValue === 1 ? "admin" : "user"; ?>">
                                                <?php echo admin_escape(admin_user_role_label($roleValue)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-label status-label-active">
                                                <span class="status-dot"></span>
                                                <span>Hoạt động</span>
                                            </span>
                                        </td>
                                        <td class="account-count-cell"><?php echo (int) $user["reviews_count"]; ?></td>
                                        <td class="account-count-cell"><?php echo (int) $user["watchlist_count"]; ?></td>
                                        <td>
                                            <div class="account-actions">
                                                <a class="account-action-btn" href="index.php?page_layout=capnhattaikhoan&id=<?php echo (int) $user["user_id"]; ?>">Sửa</a>
                                                <a class="account-action-btn account-action-btn-danger" href="index.php?page_layout=xuly_xoauser&id=<?php echo (int) $user["user_id"]; ?>" onclick="return confirm('Bạn có chắc chắn muốn xóa người dùng này không?')">Xóa</a>
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
                <nav class="account-pagination" aria-label="Điều hướng phân trang">
                    <?php if ($page > 1) { ?>
                        <a class="account-page-button" href="<?php echo admin_escape(admin_account_url(array(
                            "keyword" => $keyword,
                            "role" => $roleFilter,
                            "status" => $statusFilter,
                            "page" => $page - 1,
                        ))); ?>">&lsaquo;</a>
                    <?php } ?>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++) {
                    ?>
                        <a class="account-page-button<?php echo $pageNumber === $page ? " is-active" : ""; ?>" href="<?php echo admin_escape(admin_account_url(array(
                            "keyword" => $keyword,
                            "role" => $roleFilter,
                            "status" => $statusFilter,
                            "page" => $pageNumber,
                        ))); ?>">
                            <?php echo (int) $pageNumber; ?>
                        </a>
                    <?php } ?>

                    <?php if ($page < $totalPages) { ?>
                        <a class="account-page-button" href="<?php echo admin_escape(admin_account_url(array(
                            "keyword" => $keyword,
                            "role" => $roleFilter,
                            "status" => $statusFilter,
                            "page" => $page + 1,
                        ))); ?>">&rsaquo;</a>
                    <?php } ?>
                </nav>
            <?php } ?>

            <a class="floating-add-button" href="index.php?page_layout=them_user" aria-label="Thêm tài khoản mới">+</a>
        </main>
    </div>
</body>
</html>
