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

<body class="<?php echo $currentPage === 'home' ? 'is-home' : 'is-inner'; ?> page-<?php echo htmlspecialchars($currentPage); ?>">
    <div class="site-bg"></div>
    <header class="site-header">
        <div class="topbar">
            <a class="brand" href="index.php?page_layout=home">
                <img class="brand-logo" src="anhnen/IT (4).png" alt="ITmovie">
                <div class="brand-copy">
                    <span class="brand-title">ITmovie</span>
                    <span class="brand-subtitle">Review va xem phim moi ngay</span>
                </div>
            </a>

            <button class="menu-toggle" type="button" onclick="toggleMenu()" aria-label="Mo menu">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav class="main-nav" id="mainNav">
                <a href="index.php?page_layout=home"
                    class="<?php echo $currentPage === 'home' ? 'active' : ''; ?>">Trang chu</a>
                <div class="nav-dropdown">
                    <a href="#" class="nav-link">The loai</a>
                    <div class="dropdown-panel">
                        <?php foreach ($genreItems as $genreItem) { ?>
                        <a href="index.php?page_layout=theloai&id=<?php echo (int) $genreItem['theloai_id']; ?>">
                            <?php echo htmlspecialchars($genreItem['ten_theloai']); ?>
                        </a>
                        <?php } ?>
                    </div>
                </div>
                <a href="index.php?page_layout=timkiemphim">Tim kiem</a>
                <a href="index.php?page_layout=danhsachxemsau">Xem sau</a>
            </nav>

            <div class="topbar-actions">
                <form class="search-form" action="index.php" method="get">
                    <input type="hidden" name="page_layout" value="timkiemphim">
                    <input type="text" name="txttimkiem" class="txttimkiem"
                        placeholder="Tim ten phim, noi dung, the loai...">
                    <button type="submit">Tim</button>
                </form>

                <?php if ($isLoggedIn) { ?>
                <a class="user-pill"
                    href="index.php?page_layout=nguoidung&id=<?php echo (int) $_SESSION['user_id']; ?>">
                    Xin chao, <?php echo htmlspecialchars($fullname); ?>
                </a>
                <a class="logout-btn" href="logout.php"
                    onclick="return confirm('Ban co chac chan muon DANG XUAT khong?')">Dang xuat</a>
                <?php } else { ?>
                <a class="login-btn" href="login.php">Dang nhap</a>
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
                <h3>ITMOVIES</h3>
                <p>Phim hay moi ngay</p>
                <p>ITMOVIES cung cap trai nghiem xem phim truc tuyen voi giao dien hien dai, toc do nhanh va kho phim da dang.</p>
            </div>
            <div>
                <h3>Dieu huong</h3>
                <a href="index.php?page_layout=home">Hoi-Dap</a>
                <a href="index.php?page_layout=home">Chinh sach bao mat</a>
                <a href="index.php?page_layout=home">Dieu khoan su dung</a>
            </div>
            <div>
                <h3>Kham pha</h3>
                <a href="index.php?page_layout=home">Gioi thieu</a>
                <a href="index.php?page_layout=timkiemphim">Tim phim</a>
                <a href="index.php?page_layout=danhsachxemsau">Lien he</a>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; 2026 ITMOVIES</span>
            <span>Kho phim online cho trai nghiem xem nhanh va toi gian</span>
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
