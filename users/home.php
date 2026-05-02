<?php
include_once "cauhinh.php";

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

function renderMovieCard($movie, $variant = 'poster')
{
  $movieId = (int) $movie['movie_id'];
  $title = htmlspecialchars($movie['title']);
  $image = htmlspecialchars($movie['img']);
  $views = isset($movie['view']) ? (int) $movie['view'] : 0;
  $year = !empty($movie['release_year']) ? htmlspecialchars($movie['release_year']) : 'N/A';
  $language = !empty($movie['language']) ? htmlspecialchars($movie['language']) : 'Dang cap nhat';
  $description = !empty($movie['description']) ? htmlspecialchars(mb_substr($movie['description'], 0, 120)) . '...' : 'Noi dung dang duoc cap nhat.';
  $watchLaterUrl = isset($_SESSION['user_id'])
    ? 'index.php?page_layout=xemsau&id=' . $movieId . '&iduser=' . (int) $_SESSION['user_id']
    : 'login.php';
?>
<article class="movie-card movie-card-<?php echo $variant; ?>">
    <div class="movie-card-cover">
        <img src="../movies_admin/hinhanhphim/<?php echo $image; ?>" alt="<?php echo $title; ?>">
        <span class="movie-card-overlay"></span>
        <div class="movie-hover-details">
            <div class="movie-hover-actions">
                <a class="hover-play" href="index.php?page_layout=xemphim&id=<?php echo $movieId; ?>">&#9658; Xem
                    ngay</a>
                <a class="hover-like" href="<?php echo $watchLaterUrl; ?>">&#9829; Thích</a>
                <a class="hover-info" href="index.php?page_layout=chitietphim&id=<?php echo $movieId; ?>">Chi tiết</a>
            </div>
            <div class="movie-hover-badges">
                <span>IMDb <?php echo $views > 0 ? min(9, max(5, $views + 4)) : 6; ?></span>
                <span><?php echo $year; ?></span>
                <span>FHD</span>
            </div>
            <p><?php echo $description; ?></p>
        </div>
    </div>
    <div class="movie-card-body">
        <div class="movie-meta-top">
            <span><?php echo $year; ?></span>
            <span><?php echo $views; ?> lượt xem</span>
        </div>
        <a href="index.php?page_layout=chitietphim&id=<?php echo $movieId; ?>"
            class="movie-title"><?php echo $title; ?></a>
        <?php if ($variant === 'wide') { ?>
        <p class="movie-copy"><?php echo $description; ?></p>
        <?php } else { ?>
        <p class="movie-copy"><?php echo $language; ?></p>
        <?php } ?>
    </div>
</article>
<?php
}

$featuredMovie = null;
$hotMovies = array();
$newMovies = array();
$rankingMovies = array();
$watchlistMovies = array();
$genres = array();

$featuredResult = $connect->query("SELECT * FROM movies ORDER BY view DESC, date_add DESC LIMIT 1");
if ($featuredResult && $featuredResult->num_rows > 0) {
  $featuredMovie = $featuredResult->fetch_array(MYSQLI_ASSOC);
}

$hotResult = $connect->query("SELECT * FROM movies ORDER BY view DESC, date_add DESC LIMIT 8");
if ($hotResult) {
  while ($row = $hotResult->fetch_array(MYSQLI_ASSOC)) {
    $hotMovies[] = $row;
  }
}

$newResult = $connect->query("SELECT * FROM movies ORDER BY date_add DESC, movie_id DESC LIMIT 8");
if ($newResult) {
  while ($row = $newResult->fetch_array(MYSQLI_ASSOC)) {
    $newMovies[] = $row;
  }
}

$rankingResult = $connect->query("SELECT * FROM movies ORDER BY view DESC, movie_id DESC LIMIT 5");
if ($rankingResult) {
  while ($row = $rankingResult->fetch_array(MYSQLI_ASSOC)) {
    $rankingMovies[] = $row;
  }
}

$genreResult = $connect->query("SELECT * FROM genres ORDER BY ten_theloai ASC LIMIT 6");
if ($genreResult) {
  while ($row = $genreResult->fetch_array(MYSQLI_ASSOC)) {
    $genres[] = $row;
  }
}

if (isset($_SESSION['user_id'])) {
  $userId = (int) $_SESSION['user_id'];
  $watchlistResult = $connect->query("
        SELECT movies.*
        FROM watchlist
        INNER JOIN movies ON watchlist.movie_id = movies.movie_id
        WHERE watchlist.user_id = $userId
        ORDER BY movies.date_add DESC, movies.movie_id DESC
        LIMIT 4
    ");

  if ($watchlistResult) {
    while ($row = $watchlistResult->fetch_array(MYSQLI_ASSOC)) {
      $watchlistMovies[] = $row;
    }
  }
}

$heroTitle = $featuredMovie ? htmlspecialchars($featuredMovie['title']) : 'Kho phim dang duoc cap nhat';
$heroDescription = $featuredMovie && !empty($featuredMovie['description'])
  ? htmlspecialchars(mb_substr($featuredMovie['description'], 0, 260)) . '...'
  : 'Chon phim noi bat nhat hom nay va bat dau xem ngay tren giao dien moi.';
$heroImage = $featuredMovie ? htmlspecialchars($featuredMovie['img']) : 'hinh0.jpg';
$heroMovieId = $featuredMovie ? (int) $featuredMovie['movie_id'] : 0;
$heroViews = $featuredMovie && isset($featuredMovie['view']) ? (int) $featuredMovie['view'] : 0;
$heroYear = $featuredMovie && !empty($featuredMovie['release_year']) ? htmlspecialchars($featuredMovie['release_year']) : 'Moi';
$heroLanguage = $featuredMovie && !empty($featuredMovie['language']) ? htmlspecialchars($featuredMovie['language']) : 'Phu de';
$heroWelcome = isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : 'ban';
?>

<div class="home-landing">
    <section class="hero-banner">
        <div class="hero-backdrop">
            <img src="../movies_admin/hinhanhphim/<?php echo $heroImage; ?>" alt="<?php echo $heroTitle; ?>">
        </div>
        <div class="hero-content">
            <div class="hero-copy">
                <span class="hero-kicker">Điểm đến đầu tiên sau khi đăng nhập</span>
                <h1><?php echo $heroTitle; ?></h1>
                <p class="hero-description"><?php echo $heroDescription; ?></p>
                <div class="hero-stats">
                    <span><?php echo $heroYear; ?></span>
                    <span><?php echo $heroLanguage; ?></span>
                    <span><?php echo $heroViews; ?> lượt xem</span>
                    <span>Full HD</span>
                </div>
                <div class="hero-actions">
                    <?php if ($heroMovieId > 0) { ?>
                    <a class="btn-primary" href="index.php?page_layout=xemphim&id=<?php echo $heroMovieId; ?>">Xem
                        ngay</a>
                    <a class="btn-secondary" href="index.php?page_layout=chitietphim&id=<?php echo $heroMovieId; ?>">Chi
                        tiet</a>
                    <?php } ?>
                </div>
            </div>

            <aside class="hero-panel">
                <span class="hero-panel-label">Xin chào</span>
                <strong><?php echo $heroWelcome; ?></strong>
                <p>Trang chủ đã được bố trí lại để người dùng vừa đăng nhập có thể thấy ngay phim nổi bật, phim mới và
                    danh sách xem sau.</p>
                <div class="hero-mini-stats">
                    <div>
                        <strong><?php echo count($hotMovies); ?>+</strong>
                        <span>phim hot</span>
                    </div>
                    <div>
                        <strong><?php echo count($newMovies); ?>+</strong>
                        <span>mới cập nhật</span>
                    </div>
                    <div>
                        <strong><?php echo count($rankingMovies); ?></strong>
                        <span>bảng xếp hạng</span>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <section class="genre-strip">
        <?php foreach ($genres as $index => $genre) { ?>
        <a class="genre-chip genre-chip-<?php echo ($index % 6) + 1; ?>"
            href="index.php?page_layout=theloai&id=<?php echo (int) $genre['theloai_id']; ?>">
            <?php echo htmlspecialchars($genre['ten_theloai']); ?>
        </a>
        <?php } ?>
    </section>

    <section class="home-grid">
        <div class="home-main">
            <div class="section-head">
                <div>
                    <span class="section-tag">Xu hướng</span>
                    <h2>Phim thịnh hành hôm nay</h2>
                </div>
                <a href="index.php?page_layout=timkiemphim">Xem thêm</a>
            </div>
            <div class="movie-grid posters">
                <?php foreach ($hotMovies as $movie) {
          renderMovieCard($movie, 'poster');
        } ?>
            </div>

            <div class="section-head">
                <div>
                    <span class="section-tag">Mới cập nhật</span>
                    <h2>Bộ sưu tập vừa lên sóng</h2>
                </div>
            </div>
            <div class="movie-grid wides">
                <?php foreach ($newMovies as $movie) {
          renderMovieCard($movie, 'wide');
        } ?>
            </div>

            <?php if (!empty($watchlistMovies)) { ?>
            <div class="section-head">
                <div>
                    <span class="section-tag">Cá nhân hóa</span>
                    <h2>Danh sách xem sau của bạn</h2>
                </div>
                <a href="index.php?page_layout=danhsachxemsau">Mở danh sách</a>
            </div>
            <div class="movie-grid posters compact-grid">
                <?php foreach ($watchlistMovies as $movie) {
            renderMovieCard($movie, 'poster');
          } ?>
            </div>
            <?php } ?>
        </div>

        <aside class="home-sidebar">
            <div class="sidebar-card">
                <div class="section-head">
                    <div>
                        <span class="section-tag">Top view</span>
                        <h2>Bảng xếp hạng</h2>
                    </div>
                </div>
                <div class="ranking-list">
                    <?php foreach ($rankingMovies as $index => $movie) { ?>
                    <a class="ranking-item"
                        href="index.php?page_layout=chitietphim&id=<?php echo (int) $movie['movie_id']; ?>">
                        <span class="ranking-index"><?php echo $index + 1; ?></span>
                        <img src="../movies_admin/hinhanhphim/<?php echo htmlspecialchars($movie['img']); ?>"
                            alt="<?php echo htmlspecialchars($movie['title']); ?>">
                        <span class="ranking-copy">
                            <strong><?php echo htmlspecialchars($movie['title']); ?></strong>
                            <small><?php echo (int) $movie['view']; ?> lượt xem</small>
                        </span>
                    </a>
                    <?php } ?>
                </div>
            </div>

            <div class="sidebar-card callout-card">
                <span class="section-tag">Goi y</span>
                <h2>Trai nghiem moi</h2>
                <p>Trang chu moi uu tien phim noi bat o phan dau, sau do chia nhip xem bang grid poster, bang xep hang
                    va danh sach ca nhan de giong tinh than cua mau tham chieu.</p>
                <a class="btn-secondary full-width" href="index.php?page_layout=danhsachxemsau">Di den xem sau</a>
            </div>
        </aside>
    </section>
</div>