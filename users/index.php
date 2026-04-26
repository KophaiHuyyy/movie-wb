<?php
include_once "cauhinh.php";

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$currentPage = isset($_GET['page_layout']) ? $_GET['page_layout'] : "home";
$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['fullname']);
$fullname = $isLoggedIn ? $_SESSION['fullname'] : "Khach";

$genreItems = array();
$listtheloai = $connect->query("SELECT * FROM genres ORDER BY ten_theloai ASC");
if ($listtheloai) {
  while ($rowtl = $listtheloai->fetch_array(MYSQLI_ASSOC)) {
    $genreItems[] = $rowtl;
  }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITmovie</title>
    <link rel="stylesheet" href="stylehome.css">
    <?php if (in_array($currentPage, array('chitietphim', 'xemphim', 'reviewphim', 'danhsachxemsau'))) { ?>
    <link rel="stylesheet" href="style_detailphim.css">
    <?php } ?>
</head>

<body class="<?php echo $currentPage === 'home' ? 'is-home' : 'is-inner'; ?>">
    <div class="site-bg"></div>
    <header class="site-header">
        <div class="topbar">
            <a class="brand" href="index.php?page_layout=home">
                <img class="brand-logo" src="anhnen/IT (4).png" alt="ITmovie">
                <div class="brand-copy">
                    <span class="brand-title">ITmovie</span>
                    <span class="brand-subtitle">Review và xem phim mỗi ngày</span>
                </div>
            </a>

            <button class="menu-toggle" type="button" onclick="toggleMenu()" aria-label="Mo menu">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav class="main-nav" id="mainNav">
                <a href="index.php?page_layout=home"
                    class="<?php echo $currentPage === 'home' ? 'active' : ''; ?>">Trang chủ</a>
                <div class="nav-dropdown">
                    <a href="#" class="nav-link">Thể loại</a>
                    <div class="dropdown-panel">
                        <?php foreach ($genreItems as $genreItem) { ?>
                        <a href="index.php?page_layout=theloai&id=<?php echo (int) $genreItem['theloai_id']; ?>">
                            <?php echo htmlspecialchars($genreItem['ten_theloai']); ?>
                        </a>
                        <?php } ?>
                    </div>
                </div>
                <a href="index.php?page_layout=timkiemphim">Tìm kiếm</a>
                <a href="index.php?page_layout=danhsachxemsau">Xem sau</a>
            </nav>

            <div class="topbar-actions">
                <form class="search-form" action="index.php" method="get">
                    <input type="hidden" name="page_layout" value="timkiemphim">
                    <input type="text" name="txttimkiem" class="txttimkiem"
                        placeholder="Tìm tên phim, nội dung, thể loại...">
                    <button type="submit">Tìm</button>
                </form>

                <?php if ($isLoggedIn) { ?>
                <a class="user-pill"
                    href="index.php?page_layout=nguoidung&id=<?php echo (int) $_SESSION['user_id']; ?>">
                    Xin chao, <?php echo htmlspecialchars($fullname); ?>
                </a>
                <a class="logout-btn" href="logout.php"
                    onclick="return confirm('Bạn có chắc chắn muốn ĐĂNG XUẤT không?')">Đăng xuất</a>
                <?php } else { ?>
                <a class="login-btn" href="login.php">Đăng nhập</a>
                <?php } ?>
            </div>
        </div>
    </header>

    <main class="page-shell">
        <?php include_once "doPage.php"; ?>
    </main>

    <footer class="site-footer">
        <div class="footer-grid">
            <div>
                <h3>ITmovie</h3>
                <p>Trang xem phim và review theo phong cách đơn giản, tập trung vao nội dung va trải nghiệm duyệt phim
                    nhanh.</p>
            </div>
            <div>
                <h3>Kham pha</h3>
                <a href="index.php?page_layout=home">Trang chủ</a>
                <a href="index.php?page_layout=timkiemphim">Tìm kiếm phim</a>
                <a href="index.php?page_layout=danhsachxemsau">Danh sách xem sau</a>
            </div>
            <div>
                <h3>Danh mục</h3>
                <?php
        $genreLimit = 0;
        foreach ($genreItems as $genreItem) {
          if ($genreLimit >= 5) {
            break;
          }
        ?>
                <a href="index.php?page_layout=theloai&id=<?php echo (int) $genreItem['theloai_id']; ?>">
                    <?php echo htmlspecialchars($genreItem['ten_theloai']); ?>
                </a>
                <?php
          $genreLimit++;
        }
        ?>
            </div>
        </div>
        <div class="footer-bottom">
            <span>Movie review website</span>
            <span>Database: review</span>
        </div>
    </footer>

    <script>
    function toggleMenu() {
        var nav = document.getElementById('mainNav');
        nav.classList.toggle('open');
    }

    window.addEventListener('resize', function() {
        if (window.innerWidth > 900) {
            document.getElementById('mainNav').classList.remove('open');
        }
    });
    </script>
</body>

</html>