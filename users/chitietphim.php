<?php
include_once "cauhinh.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ID = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$movie = null;
$genres = array();
$relatedMovies = array();
$rankingMovies = array();
$reviews = array();
$reviewCount = 0;
$averageRating = 0;

if ($ID > 0) {
    $movieResult = $connect->query("
        SELECT movies.*, country.country_name
        FROM movies
        LEFT JOIN country ON movies.country_id = country.country_id
        WHERE movies.movie_id = $ID
        LIMIT 1
    ");

    if ($movieResult && $movieResult->num_rows > 0) {
        $movie = $movieResult->fetch_assoc();
    }

    $genreResult = $connect->query("
        SELECT genres.*
        FROM movie_genre
        INNER JOIN genres ON movie_genre.theloai_id = genres.theloai_id
        WHERE movie_genre.movie_id = $ID
        ORDER BY genres.ten_theloai ASC
    ");

    if ($genreResult) {
        while ($row = $genreResult->fetch_assoc()) {
            $genres[] = $row;
        }
    }

    $reviewStats = $connect->query("
        SELECT COUNT(*) AS total_reviews, AVG(rating) AS average_rating
        FROM reviews
        WHERE movie_id = $ID
    ");

    if ($reviewStats) {
        $stats = $reviewStats->fetch_assoc();
        $reviewCount = isset($stats['total_reviews']) ? (int) $stats['total_reviews'] : 0;
        $averageRating = !empty($stats['average_rating']) ? round((float) $stats['average_rating'], 1) : 0;
    }

    $reviewResult = $connect->query("
        SELECT reviews.*, users.fullname
        FROM reviews
        LEFT JOIN users ON reviews.user_id = users.user_id
        WHERE reviews.movie_id = $ID
        ORDER BY reviews.review_date DESC
        LIMIT 3
    ");

    if ($reviewResult) {
        while ($row = $reviewResult->fetch_assoc()) {
            $reviews[] = $row;
        }
    }

    $firstGenreId = !empty($genres) ? (int) $genres[0]['theloai_id'] : 0;
    if ($firstGenreId > 0) {
        $relatedResult = $connect->query("
            SELECT DISTINCT movies.*
            FROM movies
            INNER JOIN movie_genre ON movies.movie_id = movie_genre.movie_id
            WHERE movie_genre.theloai_id = $firstGenreId
              AND movies.movie_id <> $ID
            ORDER BY movies.view DESC, movies.date_add DESC
            LIMIT 8
        ");
    } else {
        $relatedResult = $connect->query("
            SELECT *
            FROM movies
            WHERE movie_id <> $ID
            ORDER BY view DESC, date_add DESC
            LIMIT 8
        ");
    }

    if (isset($relatedResult) && $relatedResult) {
        while ($row = $relatedResult->fetch_assoc()) {
            $relatedMovies[] = $row;
        }
    }
}

$rankingResult = $connect->query("
    SELECT *
    FROM movies
    ORDER BY view DESC, date_add DESC
    LIMIT 10
");

if ($rankingResult) {
    while ($row = $rankingResult->fetch_assoc()) {
        $rankingMovies[] = $row;
    }
}

if ($movie === null) {
    ?>
    <div class="movie-detail-empty">
      <h1>Khong tim thay phim</h1>
      <p>Phim co the da bi xoa hoac duong dan khong hop le.</p>
      <a href="index.php?page_layout=home">Quay ve trang chu</a>
    </div>
    <?php
    return;
}

$movieId = (int) $movie['movie_id'];
$title = htmlspecialchars($movie['title']);
$description = !empty($movie['description']) ? htmlspecialchars($movie['description']) : 'Noi dung dang duoc cap nhat.';
$shortDescription = htmlspecialchars(mb_substr(strip_tags($movie['description'] ?? ''), 0, 260));
$image = htmlspecialchars($movie['img']);
$year = !empty($movie['release_year']) ? htmlspecialchars($movie['release_year']) : 'N/A';
$language = !empty($movie['language']) ? htmlspecialchars($movie['language']) : 'Dang cap nhat';
$country = !empty($movie['country_name']) ? htmlspecialchars($movie['country_name']) : 'Dang cap nhat';
$views = isset($movie['view']) ? (int) $movie['view'] : 0;
$dateAdd = !empty($movie['date_add']) ? htmlspecialchars($movie['date_add']) : 'Dang cap nhat';
$watchLaterUrl = isset($_SESSION['user_id'])
    ? 'index.php?page_layout=xemsau&id=' . $movieId . '&iduser=' . (int) $_SESSION['user_id']
    : 'login.php';
$displayRating = $averageRating > 0 ? $averageRating : ($views > 0 ? min(9, max(5, $views + 4)) : 6);
?>

<section class="movie-detail-page">
  <div class="detail-hero">
    <div class="detail-hero-bg">
      <img src="../movies_admin/hinhanhphim/<?php echo $image; ?>" alt="<?php echo $title; ?>">
    </div>
    <div class="detail-hero-shade"></div>
  </div>

  <div class="detail-layout">
    <aside class="detail-sidebar">
      <div class="detail-poster-card">
        <img src="../movies_admin/hinhanhphim/<?php echo $image; ?>" alt="<?php echo $title; ?>">
      </div>

      <h1><?php echo $title; ?></h1>
      <p class="detail-original-title"><?php echo $language; ?></p>

      <div class="detail-badges">
        <span>IMDb <?php echo $displayRating; ?></span>
        <span><?php echo $year; ?></span>
        <span>FHD</span>
        <span>Full</span>
      </div>

      <a class="detail-season-pill" href="index.php?page_layout=xemphim&id=<?php echo $movieId; ?>">Full / 2 tap</a>

      <div class="detail-info-block">
        <h2>Gioi thieu</h2>
        <p><?php echo $description; ?></p>
      </div>

      <dl class="detail-meta-list">
        <div>
          <dt>Thoi luong</dt>
          <dd>24 phut/tap</dd>
        </div>
        <div>
          <dt>Nam</dt>
          <dd><?php echo $year; ?></dd>
        </div>
        <div>
          <dt>Quoc gia</dt>
          <dd><?php echo $country; ?></dd>
        </div>
        <div>
          <dt>Luot xem</dt>
          <dd><?php echo $views; ?></dd>
        </div>
        <div>
          <dt>Ngay cap nhat</dt>
          <dd><?php echo $dateAdd; ?></dd>
        </div>
      </dl>

      <div class="weekly-ranking">
        <h2>Top phim tuan nay</h2>
        <?php foreach ($rankingMovies as $index => $rankingMovie) { ?>
          <a class="weekly-item" href="index.php?page_layout=chitietphim&id=<?php echo (int) $rankingMovie['movie_id']; ?>">
            <span class="weekly-number"><?php echo $index + 1; ?></span>
            <img src="../movies_admin/hinhanhphim/<?php echo htmlspecialchars($rankingMovie['img']); ?>" alt="<?php echo htmlspecialchars($rankingMovie['title']); ?>">
            <span>
              <strong><?php echo htmlspecialchars($rankingMovie['title']); ?></strong>
              <small>HD • <?php echo !empty($rankingMovie['release_year']) ? htmlspecialchars($rankingMovie['release_year']) : 'Full'; ?></small>
            </span>
          </a>
        <?php } ?>
      </div>
    </aside>

    <main class="detail-main">
      <div class="detail-action-bar">
        <a class="detail-play-btn" href="index.php?page_layout=xemphim&id=<?php echo $movieId; ?>">▶ Xem ngay</a>
        <a class="detail-action" href="<?php echo $watchLaterUrl; ?>">♥ Yeu thich</a>
        <a class="detail-action" href="<?php echo $watchLaterUrl; ?>">+ Them vao</a>
        <a class="detail-action" href="index.php?page_layout=reviewphim&id=<?php echo $movieId; ?>">↗ Chia se</a>
        <a class="detail-action" href="#detail-comments">Binh luan</a>
        <span class="detail-score"><?php echo $reviewCount; ?> danh gia</span>
      </div>

      <div class="detail-domain-strip">Truy cap ro bang ten mien <strong>ITmovie.local</strong></div>

      <nav class="detail-tabs" aria-label="Thong tin phim">
        <a class="active" href="#episodes">Tap phim</a>
        <a href="#gallery">Gallery</a>
        <a href="#ost">OST</a>
        <a href="#casts">Dien vien</a>
        <a href="#related">De xuat</a>
      </nav>

      <section class="episode-panel" id="episodes">
        <div class="episode-head">
          <h2>☰ Phan 1</h2>
          <label><span>Rut gon</span><input type="checkbox" checked></label>
        </div>
        <div class="episode-tabs">
          <button type="button" class="active">Nguon 1</button>
          <button type="button">Nguon 2</button>
          <button type="button">Nguon 3</button>
        </div>
        <div class="episode-list">
          <a href="index.php?page_layout=xemphim&id=<?php echo $movieId; ?>">▶ Tap 1</a>
          <a href="index.php?page_layout=reviewphim&id=<?php echo $movieId; ?>">▶ Tap 2</a>
        </div>
      </section>

      <section class="detail-section" id="gallery">
        <div class="detail-section-head">
          <span>Gallery</span>
          <h2>Anh va thong tin nhanh</h2>
        </div>
        <div class="detail-gallery-grid">
          <div>
            <img src="../movies_admin/hinhanhphim/<?php echo $image; ?>" alt="<?php echo $title; ?>">
          </div>
          <div class="detail-summary-card">
            <h3><?php echo $title; ?></h3>
            <p><?php echo $shortDescription; ?>...</p>
            <div class="detail-genre-list">
              <?php foreach ($genres as $genre) { ?>
                <a href="index.php?page_layout=theloai&id=<?php echo (int) $genre['theloai_id']; ?>">
                  <?php echo htmlspecialchars($genre['ten_theloai']); ?>
                </a>
              <?php } ?>
            </div>
          </div>
        </div>
      </section>

      <section class="detail-comments" id="detail-comments">
        <div class="comment-title">
          <h2>💬 Binh luan (<?php echo $reviewCount; ?>)</h2>
          <div>
            <button type="button" class="active">Binh luan</button>
            <button type="button">Danh gia</button>
          </div>
        </div>
        <p class="comment-login-note">Vui long <a href="login.php">dang nhap</a> de tham gia binh luan.</p>
        <div class="comment-box">
          <textarea maxlength="1000" placeholder="Viet binh luan"></textarea>
          <div>
            <span>0 / 1000</span>
            <button type="button">Gui ➜</button>
          </div>
        </div>

        <div class="comment-list">
          <?php if (empty($reviews)) { ?>
            <div class="empty-comments">
              <span>▱</span>
              <p>Chua co binh luan nao</p>
            </div>
          <?php } else { ?>
            <?php foreach ($reviews as $review) { ?>
              <article class="review-item">
                <strong><?php echo htmlspecialchars($review['fullname'] ?? 'Nguoi dung'); ?></strong>
                <span><?php echo (int) $review['rating']; ?>/10</span>
                <p><?php echo htmlspecialchars($review['comment']); ?></p>
              </article>
            <?php } ?>
          <?php } ?>
        </div>
      </section>

      <section class="detail-section" id="related">
        <div class="detail-section-head">
          <span>De xuat</span>
          <h2>Co the ban cung thich</h2>
        </div>
        <div class="detail-related-grid">
          <?php foreach ($relatedMovies as $relatedMovie) { ?>
            <a class="related-card" href="index.php?page_layout=chitietphim&id=<?php echo (int) $relatedMovie['movie_id']; ?>">
              <img src="../movies_admin/hinhanhphim/<?php echo htmlspecialchars($relatedMovie['img']); ?>" alt="<?php echo htmlspecialchars($relatedMovie['title']); ?>">
              <strong><?php echo htmlspecialchars($relatedMovie['title']); ?></strong>
              <span><?php echo (int) $relatedMovie['view']; ?> luot xem</span>
            </a>
          <?php } ?>
        </div>
      </section>
    </main>
  </div>
</section>
