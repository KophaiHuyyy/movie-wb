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

$adminName = !empty($_SESSION["fullname"]) ? $_SESSION["fullname"] : "Admin Director";
$formStatus = isset($_GET["status"]) ? trim((string) $_GET["status"]) : "";
$formMessage = isset($_GET["message"]) ? trim((string) $_GET["message"]) : "";

$formData = array(
    "fullname" => isset($_GET["fullname"]) ? trim((string) $_GET["fullname"]) : "",
    "username" => isset($_GET["username"]) ? trim((string) $_GET["username"]) : "",
    "email" => isset($_GET["email"]) ? trim((string) $_GET["email"]) : "",
    "role" => isset($_GET["role"]) && $_GET["role"] === "1" ? "1" : "0",
);

$noticeClass = $formStatus === "error" ? "account-form-alert-error" : "account-form-alert-success";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm tài khoản | ITMOVIES Admin</title>
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
                <a class="admin-nav-item" href="index.php?page_layout=phan_tich">
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
                    <p>Người dùng / Thêm tài khoản</p>
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

            <section class="account-form-hero admin-card">
                <div class="account-form-hero-copy">
                    <div class="account-form-breadcrumb">Người dùng / Thêm tài khoản</div>
                    <div class="account-form-title-row">
                        <div>
                            <h1>Thêm tài khoản</h1>
                            <p>Tạo tài khoản mới và phân quyền truy cập hệ thống ITMOVIES.</p>
                        </div>
                        <span class="account-form-badge">Tạo mới</span>
                    </div>
                </div>
            </section>

            <?php if ($formMessage !== "") { ?>
                <div class="account-form-alert <?php echo admin_escape($noticeClass); ?>">
                    <?php echo admin_escape($formMessage); ?>
                </div>
            <?php } ?>

            <form action="xuly_themuser.php" method="post" class="account-form-shell" id="account-create-form">
                <div class="account-form-layout">
                    <div class="account-main-column">
                        <section class="account-form-card admin-card">
                            <div class="account-form-card-head">
                                <div>
                                    <h2>Thông tin tài khoản</h2>
                                    <p>Thiết lập hồ sơ cơ bản và thông tin đăng nhập ban đầu.</p>
                                </div>
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
                                    <span>Vai trò</span>
                                    <div class="form-inline-pill">
                                        <strong><?php echo $formData["role"] === "1" ? "ADMIN" : "USER"; ?></strong>
                                        <small>Giá trị lưu qua trường `myRadio`.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="password-section">
                                <div class="account-form-section-copy">
                                    <h3>Mật khẩu đăng nhập</h3>
                                    <p>Mật khẩu này sẽ được dùng cho lần đăng nhập đầu tiên.</p>
                                </div>

                                <div class="account-form-grid">
                                    <label class="account-form-field">
                                        <span>Mật khẩu</span>
                                        <input type="password" name="password" id="create-password" placeholder="Nhập mật khẩu khởi tạo..." required>
                                    </label>

                                    <label class="account-form-field">
                                        <span>Xác nhận mật khẩu</span>
                                        <input type="password" name="passwordxacnhan" id="create-password-confirm" placeholder="Nhập lại mật khẩu..." required>
                                    </label>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="account-side-column">
                        <section class="account-form-card admin-card">
                            <div class="account-form-card-head">
                                <div>
                                    <h2>Phân quyền & bảo mật</h2>
                                    <p>Chọn nhóm quyền phù hợp cho tài khoản mới.</p>
                                </div>
                            </div>

                            <div class="role-selector-grid" data-role-selector>
                                <label class="role-option-card <?php echo $formData["role"] === "0" ? "active" : ""; ?>">
                                    <input type="radio" name="myRadio" value="0" <?php echo $formData["role"] === "0" ? "checked" : ""; ?>>
                                    <span class="role-option-badge role-option-badge-user">USER</span>
                                    <strong>Người dùng</strong>
                                    <p>Có quyền xem phim, đánh giá và thêm watchlist.</p>
                                </label>

                                <label class="role-option-card <?php echo $formData["role"] === "1" ? "active" : ""; ?>">
                                    <input type="radio" name="myRadio" value="1" <?php echo $formData["role"] === "1" ? "checked" : ""; ?>>
                                    <span class="role-option-badge role-option-badge-admin">ADMIN</span>
                                    <strong>Quản trị viên</strong>
                                    <p>Có quyền truy cập khu quản trị và cấu hình nội dung.</p>
                                </label>
                            </div>

                            <div class="security-note">
                                <strong>Ghi chú bảo mật</strong>
                                <p>Mật khẩu sẽ được dùng cho lần đăng nhập đầu tiên. Vui lòng yêu cầu người dùng đổi mật khẩu sau khi đăng nhập.</p>
                            </div>

                            <div class="account-side-meta">
                                <span class="account-side-pill">Role mapping: 1 = admin</span>
                                <span class="account-side-pill">Role mapping: 0 = user</span>
                            </div>
                        </section>
                    </div>
                </div>

                <div class="sticky-action-bar">
                    <div class="sticky-action-copy">
                        <span class="account-action-status"></span>
                        <p>Tài khoản mới sẽ được lưu vào bảng `users` với đúng flow đăng nhập legacy hiện tại.</p>
                    </div>

                    <div class="sticky-action-actions">
                        <a class="admin-secondary-btn" href="index.php?page_layout=list_user">Hủy</a>
                        <button class="admin-primary-btn" type="submit" name="btndangky" value="1">Tạo tài khoản</button>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
        (function () {
            var form = document.getElementById('account-create-form');
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
                var password = document.getElementById('create-password');
                var confirmPassword = document.getElementById('create-password-confirm');

                if (password && confirmPassword && password.value !== confirmPassword.value) {
                    event.preventDefault();
                    alert('Mật khẩu xác nhận không khớp.');
                }
            });
        })();
    </script>
</body>
</html>
