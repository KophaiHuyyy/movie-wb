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
$currentUserReview = null;
$reviewFlash = isset($_SESSION['review_flash']) && is_array($_SESSION['review_flash']) ? $_SESSION['review_flash'] : null;

if ($reviewFlash !== null) {
    unset($_SESSION['review_flash']);
}

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

    $reviewStatsStmt = $connect->prepare("
        SELECT COUNT(*) AS total_reviews, AVG(rating) AS average_rating
        FROM reviews
        WHERE movie_id = ? AND is_hidden = 0
    ");

    if ($reviewStatsStmt) {
        $reviewStatsStmt->bind_param("i", $ID);
        $reviewStatsStmt->execute();
        $reviewStatsResult = $reviewStatsStmt->get_result();
        if ($reviewStatsResult && $stats = $reviewStatsResult->fetch_assoc()) {
            $reviewCount = isset($stats['total_reviews']) ? (int) $stats['total_reviews'] : 0;
            $averageRating = !empty($stats['average_rating']) ? round((float) $stats['average_rating'], 1) : 0;
        }
        $reviewStatsStmt->close();
    }

    $reviewStmt = $connect->prepare("
        SELECT reviews.*, users.fullname
        FROM reviews
        LEFT JOIN users ON reviews.user_id = users.user_id
        WHERE reviews.movie_id = ? AND reviews.is_hidden = 0
        ORDER BY reviews.review_date DESC
        LIMIT 3
    ");

    if ($reviewStmt) {
        $reviewStmt->bind_param("i", $ID);
        $reviewStmt->execute();
        $reviewResult = $reviewStmt->get_result();
        while ($reviewResult && $row = $reviewResult->fetch_assoc()) {
            $reviews[] = $row;
        }
        $reviewStmt->close();
    }

    if (isset($_SESSION['user_id'])) {
        $currentUserId = (int) $_SESSION['user_id'];
        $currentReviewStmt = $connect->prepare("
            SELECT review_id, rating, comment, review_date, is_hidden
            FROM reviews
            WHERE movie_id = ? AND user_id = ?
            ORDER BY review_id DESC
            LIMIT 1
        ");

        if ($currentReviewStmt) {
            $currentReviewStmt->bind_param("ii", $ID, $currentUserId);
            $currentReviewStmt->execute();
            $currentReviewResult = $currentReviewStmt->get_result();
            if ($currentReviewResult && $currentReviewResult->num_rows > 0) {
                $currentUserReview = $currentReviewResult->fetch_assoc();
            }
            $currentReviewStmt->close();
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
$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['role']);
$existingCommentValue = $currentUserReview !== null && !empty($currentUserReview['comment']) ? $currentUserReview['comment'] : '';
$existingRatingValue = $currentUserReview !== null ? (int) $currentUserReview['rating'] : 0;

if (!function_exists('detail_render_rating_stars')) {
    function detail_render_rating_stars($rating)
    {
        $rating = (int) $rating;
        $markup = '';

        for ($star = 1; $star <= 5; $star++) {
            $markup .= $star <= $rating ? '&#9733;' : '&#9734;';
        }

        return $markup;
    }
}
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
              <small>HD â€¢ <?php echo !empty($rankingMovie['release_year']) ? htmlspecialchars($rankingMovie['release_year']) : 'Full'; ?></small>
            </span>
          </a>
        <?php } ?>
      </div>
    </aside>

    <main class="detail-main">
      <div class="detail-action-bar">
        <a class="detail-play-btn" href="index.php?page_layout=xemphim&id=<?php echo $movieId; ?>">â–¶ Xem ngay</a>
        <a class="detail-action" href="<?php echo $watchLaterUrl; ?>">â™¥ Yeu thich</a>
        <a class="detail-action" href="<?php echo $watchLaterUrl; ?>">+ Them vao</a>
        <a class="detail-action" href="index.php?page_layout=reviewphim&id=<?php echo $movieId; ?>">â†— Chia se</a>
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
          <h2>â˜° Phan 1</h2>
          <label><span>Rut gon</span><input type="checkbox" checked></label>
        </div>
        <div class="episode-tabs">
          <button type="button" class="active">Nguon 1</button>
          <button type="button">Nguon 2</button>
          <button type="button">Nguon 3</button>
        </div>
        <div class="episode-list">
          <a href="index.php?page_layout=xemphim&id=<?php echo $movieId; ?>">â–¶ Tap 1</a>
          <a href="index.php?page_layout=reviewphim&id=<?php echo $movieId; ?>">â–¶ Tap 2</a>
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
          <h2>Binh luan (<?php echo $reviewCount; ?>)</h2>
          <div>
            <button type="button" class="active">Binh luan</button>
            <button type="button">Danh gia</button>
          </div>
        </div>

        <?php if (!$isLoggedIn) { ?>
          <p class="comment-login-note">Vui long <a href="login.php">dang nhap</a> de tham gia binh luan.</p>
        <?php } else { ?>
          <p class="comment-login-note"><?php echo $currentUserReview !== null ? 'Ban da danh gia phim nay. Gui lai de cap nhat noi dung va so sao.' : 'Moi tai khoan duoc luu 1 danh gia cho moi phim.'; ?></p>
        <?php } ?>

        <?php if ($reviewFlash !== null && !empty($reviewFlash['message'])) { ?>
          <div class="detail-review-flash <?php echo $reviewFlash['type'] === 'success' ? 'is-success' : 'is-error'; ?>">
            <?php echo htmlspecialchars($reviewFlash['message']); ?>
          </div>
        <?php } ?>

        <form class="comment-box" action="xuly_review.php" method="post">
          <input type="hidden" name="movie_id" value="<?php echo $movieId; ?>">
          <input type="hidden" name="return_page" value="chitietphim">

          <fieldset class="detail-rating-fieldset" <?php echo !$isLoggedIn ? 'disabled' : ''; ?>>
            <legend>Danh gia phim</legend>
            <div class="detail-rating-star-group" role="radiogroup" aria-label="Danh gia phim bang sao">
              <?php for ($star = 5; $star >= 1; $star--) { ?>
                <input type="radio" id="detail-rating-star-<?php echo $star; ?>" name="rating" value="<?php echo $star; ?>" <?php echo $existingRatingValue === $star ? 'checked' : ''; ?>>
                <label for="detail-rating-star-<?php echo $star; ?>" title="<?php echo $star; ?> sao">
                  <span aria-hidden="true">&#9733;</span>
                  <span class="sr-only"><?php echo $star; ?> sao</span>
                </label>
              <?php } ?>
            </div>
            <p class="detail-rating-helper"><?php echo $averageRating > 0 ? 'Diem trung binh hien tai: ' . htmlspecialchars(number_format($averageRating, 1)) . ' / 5 sao.' : 'Phim nay chua co danh gia nao.'; ?></p>
          </fieldset>

          <textarea name="comment" maxlength="1000" placeholder="Viet binh luan" <?php echo !$isLoggedIn ? 'disabled' : ''; ?> data-detail-comment-input><?php echo htmlspecialchars($existingCommentValue); ?></textarea>
          <div>
            <span data-detail-comment-count>0 / 1000</span>
            <button type="submit" <?php echo !$isLoggedIn ? 'disabled' : ''; ?>><?php echo $currentUserReview !== null ? 'Cap nhat >' : 'Gui >'; ?></button>
          </div>
        </form>

        <div class="comment-list">
          <?php if (empty($reviews)) { ?>
            <div class="empty-comments">
              <span>&#128172;</span>
              <p>Chua co binh luan nao</p>
            </div>
          <?php } else { ?>
            <?php foreach ($reviews as $review) { ?>
              <article class="review-item">
                <strong><?php echo htmlspecialchars($review['fullname'] ?? 'Nguoi dung'); ?></strong>
                <span><?php echo detail_render_rating_stars(isset($review['rating']) ? (int) $review['rating'] : 0); ?> <?php echo (int) $review['rating']; ?>/5</span>
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

<script>
(function() {
  var commentInput = document.querySelector('[data-detail-comment-input]');
  var commentCount = document.querySelector('[data-detail-comment-count]');

  function updateCounter() {
    if (!commentInput || !commentCount) {
      return;
    }

    commentCount.textContent = commentInput.value.length + ' / 1000';
  }

  if (commentInput) {
    commentInput.addEventListener('input', updateCounter);
    updateCounter();
  }
})();
</script>
