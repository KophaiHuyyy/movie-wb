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

if (!function_exists("admin_fetch_one_prepared")) {
    function admin_fetch_one_prepared($connect, $sql, $types = "", $params = array())
    {
        $statement = $connect->prepare($sql);
        if (!$statement) {
            return null;
        }

        if ($types !== "" && !empty($params)) {
            $bindValues = array($types);
            foreach ($params as $index => $value) {
                $bindValues[] = &$params[$index];
            }
            call_user_func_array(array($statement, "bind_param"), $bindValues);
        }

        if (!$statement->execute()) {
            $statement->close();
            return null;
        }

        $result = $statement->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        if ($result) {
            $result->free();
        }

        $statement->close();
        return $row;
    }
}

$adminName = !empty($_SESSION["fullname"]) ? $_SESSION["fullname"] : "Admin Director";
$userId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$formStatus = isset($_GET["status"]) ? trim((string) $_GET["status"]) : "";
$formMessage = isset($_GET["message"]) ? trim((string) $_GET["message"]) : "";

$user = null;
if ($userId > 0) {
    $user = admin_fetch_one_prepared(
        $connect,
        "SELECT
            u.user_id,
            u.username,
            u.email,
            u.fullname,
            COALESCE(u.role, 0) AS role,
            (SELECT COUNT(*) FROM reviews r WHERE r.user_id = u.user_id) AS reviews_count,
            (SELECT COUNT(*) FROM watchlist w WHERE w.user_id = u.user_id) AS watchlist_count
         FROM users u
         WHERE u.user_id = ?
         LIMIT 1",
        "i",
        array($userId)
    );
}

$formData = array(
    "fullname" => isset($_GET["fullname"]) ? trim((string) $_GET["fullname"]) : ($user ? (string) $user["fullname"] : ""),
    "username" => isset($_GET["username"]) ? trim((string) $_GET["username"]) : ($user ? (string) $user["username"] : ""),
    "email" => isset($_GET["email"]) ? trim((string) $_GET["email"]) : ($user ? (string) $user["email"] : ""),
    "role" => isset($_GET["role"]) ? (($_GET["role"] === "1") ? "1" : "0") : ($user && (int) $user["role"] === 1 ? "1" : "0"),
);

$noticeClass = $formStatus === "error" ? "account-form-alert-error" : "account-form-alert-success";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa tài khoản | ITMOVIES Admin</title>
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

        <main class="admin-main account-form-page">
            <header class="admin-topbar account-topbar">
                <div class="account-topbar-title">
                    <p>Người dùng / Sửa tài khoản</p>
                </div>

                <form class="admin-search admin-search-wide" action="index.php" method="get">
                    <input type="hidden" name="page_layout" value="list_user">
                    <span class="admin-search-icon">&#9906;</span>
                    <input type="search" name="keyword" placeholder="Tìm kiếm tài khoản...">
                </form>

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

            <?php if ($userId <= 0 || !$user) { ?>
                <section class="account-form-card admin-card account-form-empty-state">
                    <div class="account-form-empty-icon">&#9888;</div>
                    <h1>Không tìm thấy tài khoản</h1>
                    <p>Tài khoản bạn cần chỉnh sửa không tồn tại hoặc đã bị xóa khỏi hệ thống.</p>
                    <a class="admin-primary-btn" href="index.php?page_layout=list_user">Quay lại danh sách người dùng</a>
                </section>
            <?php } else { ?>
                <section class="account-form-hero admin-card">
                    <div class="account-form-hero-copy">
                        <div class="account-form-breadcrumb">Người dùng / Sửa tài khoản</div>
                        <div class="account-form-title-row">
                            <div>
                                <h1>Sửa tài khoản</h1>
                                <p>Cập nhật thông tin tài khoản và quyền truy cập hệ thống.</p>
                            </div>
                            <div class="account-form-badge-stack">
                                <span class="account-form-badge account-form-badge-soft">ID: #ITM-<?php echo (int) $user["user_id"]; ?></span>
                                <span class="account-form-badge">Đang chỉnh sửa</span>
                            </div>
                        </div>
                    </div>
                </section>

                <?php if ($formMessage !== "") { ?>
                    <div class="account-form-alert <?php echo admin_escape($noticeClass); ?>">
                        <?php echo admin_escape($formMessage); ?>
                    </div>
                <?php } ?>

                <form action="xuly_capnhatuser.php?id=<?php echo (int) $userId; ?>" method="post" class="account-form-shell" id="account-edit-form">
                    <div class="account-form-layout">
                        <div class="account-main-column">
                            <section class="account-form-card admin-card">
                                <div class="account-form-card-head">
                                    <div>
                                        <h2>Thông tin tài khoản</h2>
                                        <p>Cập nhật hồ sơ hiển thị, email liên hệ và quyền truy cập.</p>
                                    </div>
                                    <span class="role-badge role-badge-<?php echo $formData["role"] === "1" ? "admin" : "user"; ?>">
                                        <?php echo $formData["role"] === "1" ? "ADMIN" : "USER"; ?>
                                    </span>
                                </div>

                                <div class="account-form-grid">
                                    <label class="account-form-field">
                                        <span>Họ tên</span>
                                        <input type="text" name="fullname" value="<?php echo admin_escape($formData["fullname"]); ?>" placeholder="Nhập họ tên người dùng..." required>
                                    </label>

                                    <label class="account-form-field">
                                        <span>Username</span>
                                        <input type="text" name="username" value="<?php echo admin_escape($formData["username"]); ?>" placeholder="Nhập username..." required>
                                    </label>

                                    <label class="account-form-field">
                                        <span>Email</span>
                                        <input type="email" name="email" value="<?php echo admin_escape($formData["email"]); ?>" placeholder="tennguoidung@email.com" required>
                                    </label>

                                    <div class="account-form-field">
                                        <span>Trạng thái</span>
                                        <div class="form-inline-pill">
                                            <strong>Hoạt động</strong>
                                            <small>Hiển thị mặc định, không lưu trong bảng `users`.</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="password-section">
                                    <div class="account-form-section-copy">
                                        <h3>Đổi mật khẩu</h3>
                                        <p>Để trống nếu không muốn đổi mật khẩu. Mật khẩu cũ không được hiển thị trong giao diện.</p>
                                    </div>

                                    <div class="account-form-grid">
                                        <label class="account-form-field">
                                            <span>Mật khẩu mới</span>
                                            <input type="password" name="password" id="edit-password" placeholder="Nhập mật khẩu mới nếu muốn thay đổi">
                                        </label>

                                        <label class="account-form-field">
                                            <span>Xác nhận mật khẩu mới</span>
                                            <input type="password" name="passwordxacnhan" id="edit-password-confirm" placeholder="Nhập lại mật khẩu mới">
                                        </label>
                                    </div>

                                    <p class="account-form-helper">Khi để trống cả hai ô, handler cập nhật sẽ giữ nguyên mật khẩu legacy hiện tại.</p>
                                </div>
                            </section>
                        </div>

                        <div class="account-side-column">
                            <section class="account-form-card admin-card">
                                <div class="account-form-card-head">
                                    <div>
                                        <h2>Trạng thái & quyền truy cập</h2>
                                        <p>Phân quyền tài khoản và theo dõi tín hiệu bảo mật cơ bản.</p>
                                    </div>
                                </div>

                                <div class="role-selector-grid" data-role-selector>
                                    <label class="role-option-card <?php echo $formData["role"] === "0" ? "active" : ""; ?>">
                                        <input type="radio" name="myRadio" value="0" <?php echo $formData["role"] === "0" ? "checked" : ""; ?>>
                                        <span class="role-option-badge role-option-badge-user">USER</span>
                                        <strong>Người dùng</strong>
                                        <p>Xem phim, gửi đánh giá và xây dựng watchlist cá nhân.</p>
                                    </label>

                                    <label class="role-option-card <?php echo $formData["role"] === "1" ? "active" : ""; ?>">
                                        <input type="radio" name="myRadio" value="1" <?php echo $formData["role"] === "1" ? "checked" : ""; ?>>
                                        <span class="role-option-badge role-option-badge-admin">ADMIN</span>
                                        <strong>Quản trị viên</strong>
                                        <p>Truy cập đầy đủ khu quản trị, nội dung và cấu hình vận hành.</p>
                                    </label>
                                </div>

                                <div class="security-note">
                                    <strong>Protected</strong>
                                    <p>Password hiện tại không được render ra HTML. Chỉ khi admin nhập mật khẩu mới thì handler mới ghi đè giá trị đang lưu.</p>
                                </div>

                                <dl class="account-info-list">
                                    <div>
                                        <dt>User ID</dt>
                                        <dd>#ITM-<?php echo (int) $user["user_id"]; ?></dd>
                                    </div>
                                    <div>
                                        <dt>Email hiện tại</dt>
                                        <dd><?php echo admin_escape($user["email"]); ?></dd>
                                    </div>
                                    <div>
                                        <dt>Vai trò</dt>
                                        <dd><?php echo (int) $user["role"] === 1 ? "ADMIN" : "USER"; ?></dd>
                                    </div>
                                    <div>
                                        <dt>Đánh giá</dt>
                                        <dd><?php echo (int) $user["reviews_count"]; ?></dd>
                                    </div>
                                    <div>
                                        <dt>Watchlist</dt>
                                        <dd><?php echo (int) $user["watchlist_count"]; ?></dd>
                                    </div>
                                </dl>
                            </section>
                        </div>
                    </div>

                    <div class="sticky-action-bar">
                        <div class="sticky-action-copy">
                            <span class="account-action-status"></span>
                            <p>Cập nhật sẽ áp dụng lên bảng `users`. Nếu không nhập mật khẩu mới, hệ thống giữ nguyên giá trị hiện tại.</p>
                        </div>

                        <div class="sticky-action-actions">
                            <a class="admin-secondary-btn" href="index.php?page_layout=list_user">Hủy</a>
                            <button class="admin-primary-btn" type="submit" name="btndangky" value="1">Cập nhật tài khoản</button>
                        </div>
                    </div>
                </form>
            <?php } ?>
        </main>
    </div>

    <script>
        (function () {
            var form = document.getElementById('account-edit-form');
            var roleCards = document.querySelectorAll('[data-role-selector] .role-option-card');

            roleCards.forEach(function (card) {
                var input = card.querySelector('input[type="radio"]');
                if (!input) {
                    return;
                }

                card.addEventListener('click', function () {
                    input.checked = true;
                    roleCards.forEach(function (item) {
                        item.classList.toggle('active', item === card);
                    });
                });
            });

            if (!form) {
                return;
            }

            form.addEventListener('submit', function (event) {
                var password = document.getElementById('edit-password');
                var confirmPassword = document.getElementById('edit-password-confirm');

                if (!password || !confirmPassword) {
                    return;
                }

                if ((password.value === '' && confirmPassword.value !== '') || (password.value !== '' && confirmPassword.value === '')) {
                    event.preventDefault();
                    alert('Vui lòng nhập đầy đủ cả hai ô mật khẩu nếu muốn cập nhật mật khẩu.');
                    return;
                }

                if (password.value !== confirmPassword.value) {
                    event.preventDefault();
                    alert('Mật khẩu xác nhận không khớp.');
                }
            });
        })();
    </script>
</body>
</html>
