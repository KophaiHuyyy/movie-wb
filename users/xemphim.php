<?php
include_once "cauhinh.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists("watch_escape")) {
    function watch_escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists("watch_is_placeholder_link")) {
    function watch_is_placeholder_link($value)
    {
        $normalized = mb_strtolower(trim((string) $value));
        $invalidValues = array("", "link1", "link2", "lnk1", "lnk2", "chua co", "chưa có", "n/a", "#");
        return in_array($normalized, $invalidValues, true);
    }
}

if (!function_exists("watch_extract_youtube_id")) {
    function watch_extract_youtube_id($value)
    {
        $value = trim((string) $value);
        if ($value === "" || watch_is_placeholder_link($value)) {
            return "";
        }

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $value)) {
            return $value;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return "";
        }

        $parts = parse_url($value);
        if (!$parts || empty($parts['host'])) {
            return "";
        }

        $host = mb_strtolower($parts['host']);
        $path = isset($parts['path']) ? trim($parts['path'], '/') : "";

        if ($host === 'youtu.be' && $path !== '') {
            return $path;
        }

        if (strpos($host, 'youtube.com') !== false) {
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $queryParams);
                if (!empty($queryParams['v'])) {
                    return (string) $queryParams['v'];
                }
            }

            if ($path !== '') {
                $pathParts = explode('/', $path);
                $embedIndex = array_search('embed', $pathParts, true);
                if ($embedIndex !== false && isset($pathParts[$embedIndex + 1])) {
                    return $pathParts[$embedIndex + 1];
                }
            }
        }

        return "";
    }
}

if (!function_exists("watch_resolve_video_source")) {
    function watch_resolve_video_source($linkOne, $linkTwo)
    {
        $candidates = array($linkOne, $linkTwo);

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === "" || watch_is_placeholder_link($candidate)) {
                continue;
            }

            $youtubeId = watch_extract_youtube_id($candidate);
            if ($youtubeId !== "") {
                return array(
                    "type" => "youtube",
                    "src" => "https://www.youtube.com/embed/" . rawurlencode($youtubeId) . "?rel=0&modestbranding=1&autoplay=0",
                    "label" => "YouTube"
                );
            }

            if (!filter_var($candidate, FILTER_VALIDATE_URL)) {
                continue;
            }

            if (preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $candidate)) {
                return array(
                    "type" => "html5",
                    "src" => $candidate,
                    "label" => "Direct"
                );
            }

            return array(
                "type" => "iframe",
                "src" => $candidate,
                "label" => "Embed"
            );
        }

        return null;
    }
}

if (!function_exists("watch_format_datetime")) {
    function watch_format_datetime($value)
    {
        if (empty($value)) {
            return "Dang cap nhat";
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return watch_escape($value);
        }

        return date('d/m/Y H:i', $timestamp);
    }
}

$movieId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$isLoggedIn = $currentUserId > 0 && isset($_SESSION['username']) && isset($_SESSION['role']);

$movie = null;
$genres = array();
$reviews = array();
$recommendations = array();
$reviewCount = 0;
$averageRating = 0;
$watchlistExists = false;

if ($movieId > 0) {
    $movieStmt = $connect->prepare("
        SELECT movies.*, country.country_name
        FROM movies
        LEFT JOIN country ON movies.country_id = country.country_id
        WHERE movies.movie_id = ?
        LIMIT 1
    ");

    if ($movieStmt) {
        $movieStmt->bind_param("i", $movieId);
        $movieStmt->execute();
        $movieResult = $movieStmt->get_result();
        if ($movieResult && $movieResult->num_rows > 0) {
            $movie = $movieResult->fetch_assoc();
        }
        $movieStmt->close();
    }
}

if ($movie === null) {
    ?>
    <section class="watch-page">
        <div class="watch-container">
            <div class="watch-empty-state">
                <span class="watch-empty-icon">&#9655;</span>
                <h1>Khong tim thay phim</h1>
                <p>Phim co the da bi xoa hoac duong dan hien tai khong hop le.</p>
                <a href="index.php?page_layout=home">Quay ve trang chu</a>
            </div>
        </div>
    </section>
    <?php
    return;
}

if (!$isLoggedIn) {
    $_SESSION['redirect'] = "index.php?page_layout=xemphim&id=" . $movieId;
}

$genreStmt = $connect->prepare("
    SELECT genres.theloai_id, genres.ten_theloai
    FROM movie_genre
    INNER JOIN genres ON movie_genre.theloai_id = genres.theloai_id
    WHERE movie_genre.movie_id = ?
    ORDER BY genres.ten_theloai ASC
");
if ($genreStmt) {
    $genreStmt->bind_param("i", $movieId);
    $genreStmt->execute();
    $genreResult = $genreStmt->get_result();
    while ($genreResult && $row = $genreResult->fetch_assoc()) {
        $genres[] = $row;
    }
    $genreStmt->close();
}

$statsStmt = $connect->prepare("
    SELECT COUNT(*) AS total_reviews, AVG(rating) AS average_rating
    FROM reviews
    WHERE movie_id = ?
");
if ($statsStmt) {
    $statsStmt->bind_param("i", $movieId);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
    if ($statsResult && $statsRow = $statsResult->fetch_assoc()) {
        $reviewCount = isset($statsRow['total_reviews']) ? (int) $statsRow['total_reviews'] : 0;
        $averageRating = !empty($statsRow['average_rating']) ? round((float) $statsRow['average_rating'], 1) : 0;
    }
    $statsStmt->close();
}

$reviewStmt = $connect->prepare("
    SELECT reviews.review_id, reviews.rating, reviews.comment, reviews.review_date, users.username, users.fullname
    FROM reviews
    LEFT JOIN users ON reviews.user_id = users.user_id
    WHERE reviews.movie_id = ?
    ORDER BY COALESCE(reviews.review_date, '1000-01-01 00:00:00') DESC, reviews.review_id DESC
    LIMIT 10
");
if ($reviewStmt) {
    $reviewStmt->bind_param("i", $movieId);
    $reviewStmt->execute();
    $reviewResult = $reviewStmt->get_result();
    while ($reviewResult && $row = $reviewResult->fetch_assoc()) {
        $reviews[] = $row;
    }
    $reviewStmt->close();
}

if ($currentUserId > 0) {
    $watchlistStmt = $connect->prepare("
        SELECT watchlist_id
        FROM watchlist
        WHERE movie_id = ? AND user_id = ?
        LIMIT 1
    ");
    if ($watchlistStmt) {
        $watchlistStmt->bind_param("ii", $movieId, $currentUserId);
        $watchlistStmt->execute();
        $watchlistResult = $watchlistStmt->get_result();
        $watchlistExists = $watchlistResult && $watchlistResult->num_rows > 0;
        $watchlistStmt->close();
    }
}

$primaryGenreId = !empty($genres) ? (int) $genres[0]['theloai_id'] : 0;
if ($primaryGenreId > 0) {
    $recommendStmt = $connect->prepare("
        SELECT DISTINCT movies.*, country.country_name
        FROM movies
        INNER JOIN movie_genre ON movies.movie_id = movie_genre.movie_id
        LEFT JOIN country ON movies.country_id = country.country_id
        WHERE movie_genre.theloai_id = ?
          AND movies.movie_id <> ?
        ORDER BY COALESCE(movies.view, 0) DESC, COALESCE(movies.date_add, '1000-01-01') DESC, movies.movie_id DESC
        LIMIT 10
    ");
    if ($recommendStmt) {
        $recommendStmt->bind_param("ii", $primaryGenreId, $movieId);
        $recommendStmt->execute();
        $recommendResult = $recommendStmt->get_result();
        while ($recommendResult && $row = $recommendResult->fetch_assoc()) {
            $recommendations[] = $row;
        }
        $recommendStmt->close();
    }
}

if (empty($recommendations)) {
    $fallbackStmt = $connect->prepare("
        SELECT movies.*, country.country_name
        FROM movies
        LEFT JOIN country ON movies.country_id = country.country_id
        WHERE movies.movie_id <> ?
        ORDER BY COALESCE(movies.view, 0) DESC, COALESCE(movies.date_add, '1000-01-01') DESC, movies.movie_id DESC
        LIMIT 10
    ");
    if ($fallbackStmt) {
        $fallbackStmt->bind_param("i", $movieId);
        $fallbackStmt->execute();
        $fallbackResult = $fallbackStmt->get_result();
        while ($fallbackResult && $row = $fallbackResult->fetch_assoc()) {
            $recommendations[] = $row;
        }
        $fallbackStmt->close();
    }
}

$videoSource = watch_resolve_video_source($movie['link1'] ?? "", $movie['link2'] ?? "");
$posterFile = !empty($movie['img']) ? $movie['img'] : 'hinh0.jpg';
$posterPath = "../movies_admin/hinhanhphim/" . $posterFile;
$movieTitle = watch_escape($movie['title']);
$movieDescription = !empty($movie['description']) ? watch_escape($movie['description']) : 'Noi dung phim dang duoc cap nhat.';
$movieLanguage = !empty($movie['language']) ? watch_escape($movie['language']) : 'Dang cap nhat';
$movieCountry = !empty($movie['country_name']) ? watch_escape($movie['country_name']) : 'Dang cap nhat';
$movieYear = !empty($movie['release_year']) ? watch_escape($movie['release_year']) : 'N/A';
$movieViews = isset($movie['view']) ? (int) $movie['view'] : 0;
$movieAddedDate = !empty($movie['date_add']) ? watch_escape($movie['date_add']) : 'Dang cap nhat';
$watchlistUrl = $isLoggedIn
    ? "index.php?page_layout=xemsau&id=" . $movieId . "&iduser=" . $currentUserId
    : "login.php";
$detailUrl = "index.php?page_layout=chitietphim&id=" . $movieId;
$shareUrl = "http://localhost/WebsiteReviewPhim/users/index.php?page_layout=xemphim&id=" . $movieId;
$statusLabel = $videoSource === null ? "Phim sap ra mat" : "Dang chieu";
$ratingBadge = $averageRating > 0 ? number_format($averageRating, 1) : "0";
$genreNames = array();
foreach ($genres as $genre) {
    $genreNames[] = $genre['ten_theloai'];
}
$genreSummary = !empty($genreNames) ? watch_escape(implode(' / ', $genreNames)) : 'Dang cap nhat the loai';
?>

<section class="watch-page" data-share-url="<?php echo watch_escape($shareUrl); ?>">
    <div class="watch-container">
        <div class="watch-breadcrumb">
            <a class="watch-back-button" href="<?php echo $detailUrl; ?>" onclick="if (window.history.length > 1) { event.preventDefault(); window.history.back(); }" aria-label="Quay lai">
                <span>&larr;</span>
            </a>
            <div class="watch-breadcrumb-copy">
                <span class="watch-breadcrumb-label">Dang phat</span>
                <h1>Xem phim <?php echo $movieTitle; ?></h1>
            </div>
        </div>

        <div class="watch-player-card">
            <div class="watch-video-frame">
                <?php if ($videoSource !== null && $isLoggedIn) : ?>
                    <?php if ($videoSource['type'] === 'youtube' || $videoSource['type'] === 'iframe') : ?>
                        <iframe
                            src="<?php echo watch_escape($videoSource['src']); ?>"
                            title="Xem phim <?php echo $movieTitle; ?>"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            referrerpolicy="strict-origin-when-cross-origin"
                            allowfullscreen></iframe>
                    <?php else : ?>
                        <video controls preload="metadata" poster="<?php echo watch_escape($posterPath); ?>" data-watch-video>
                            <source src="<?php echo watch_escape($videoSource['src']); ?>" type="video/mp4">
                            Trinh duyet cua ban khong ho tro video HTML5.
                        </video>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="watch-video-placeholder" style="background-image: linear-gradient(180deg, rgba(10, 11, 17, 0.18), rgba(10, 11, 17, 0.92)), url('<?php echo watch_escape($posterPath); ?>');">
                        <div class="watch-video-placeholder-inner">
                            <span class="watch-placeholder-play">&#9658;</span>
                            <?php if (!$isLoggedIn) : ?>
                                <h2>Vui long dang nhap de xem phim</h2>
                                <p>Trang xem phim van giu flow cu: ban can dang nhap de mo player va luu lich su dieu huong.</p>
                                <div class="watch-placeholder-actions">
                                    <a href="login.php">Dang nhap</a>
                                    <a class="secondary" href="<?php echo $detailUrl; ?>">Quay lai chi tiet</a>
                                </div>
                            <?php else : ?>
                                <h2>Video hien chua kha dung</h2>
                                <p>Server xem phim chua duoc cau hinh hop le trong `link1` hoac `link2`.</p>
                                <div class="watch-placeholder-actions">
                                    <a href="<?php echo $detailUrl; ?>">Quay lai chi tiet phim</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="watch-action-bar">
                <span class="watch-status-badge<?php echo $videoSource === null ? ' is-upcoming' : ''; ?>"><?php echo watch_escape($statusLabel); ?></span>
                <a class="watch-action-item<?php echo $watchlistExists ? ' is-active' : ''; ?>" href="<?php echo watch_escape($watchlistUrl); ?>">
                    <span>&hearts;</span>
                    <?php echo $watchlistExists ? 'Da luu' : 'Yeu thich'; ?>
                </a>
                <a class="watch-action-item" href="<?php echo watch_escape($watchlistUrl); ?>">
                    <span>+</span>
                    Them vao
                </a>
                <button class="watch-action-item" type="button" data-skip-intro>
                    <span>OFF</span>
                    Bo qua gioi thieu
                </button>
                <a class="watch-action-item" href="<?php echo $detailUrl; ?>">
                    <span>&#9737;</span>
                    Rap phim
                </a>
                <button class="watch-action-item" type="button" data-share-trigger>
                    <span>&#10148;</span>
                    Chia se
                </button>
            </div>
        </div>

        <div class="watch-main-grid">
            <div class="watch-content">
                <section class="watch-comments">
                    <div class="watch-section-head">
                        <div>
                            <span class="watch-section-kicker">&#128172; Binh luan</span>
                            <h2>Binh luan (<?php echo $reviewCount; ?>)</h2>
                        </div>
                    </div>

                    <?php if (!$isLoggedIn) { ?>
                        <p class="watch-login-hint">Vui long <a href="login.php">dang nhap</a> de tham gia binh luan.</p>
                    <?php } else { ?>
                        <p class="watch-login-hint">Khu vuc nay hien dang hien thi UI nhap binh luan. Backend luu moi binh luan chua duoc trien khai trong `xemphim.php`.</p>
                    <?php } ?>

                    <div class="comment-editor">
                        <textarea maxlength="1000" placeholder="Viet binh luan" <?php echo !$isLoggedIn ? 'disabled' : ''; ?> data-comment-input></textarea>
                        <div class="comment-editor-bar">
                            <label class="comment-spoiler-toggle">
                                <input type="checkbox" <?php echo !$isLoggedIn ? 'disabled' : ''; ?>>
                                <span>Tiet lo?</span>
                            </label>
                            <div class="comment-editor-actions">
                                <span data-comment-count>0 / 1000</span>
                                <button type="button" <?php echo !$isLoggedIn ? 'disabled' : ''; ?>>Gui &#10148;</button>
                            </div>
                        </div>
                    </div>

                    <div class="comment-list">
                        <?php if (empty($reviews)) { ?>
                            <div class="comment-empty-state">
                                <span class="comment-empty-icon">&#128172;</span>
                                <p>Chua co binh luan nao</p>
                            </div>
                        <?php } else { ?>
                            <?php foreach ($reviews as $review) { ?>
                                <?php
                                $displayName = !empty($review['fullname']) ? $review['fullname'] : (!empty($review['username']) ? $review['username'] : 'Nguoi dung');
                                $initial = mb_strtoupper(mb_substr($displayName, 0, 1));
                                ?>
                                <article class="watch-comment-item">
                                    <div class="watch-comment-avatar"><?php echo watch_escape($initial); ?></div>
                                    <div class="watch-comment-body">
                                        <div class="watch-comment-meta">
                                            <strong><?php echo watch_escape($displayName); ?></strong>
                                            <span><?php echo watch_format_datetime($review['review_date']); ?></span>
                                        </div>
                                        <div class="watch-comment-rating">
                                            <span>&#9733; <?php echo (int) $review['rating']; ?>/10</span>
                                        </div>
                                        <p><?php echo nl2br(watch_escape($review['comment'])); ?></p>
                                    </div>
                                </article>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </section>
            </div>

            <aside class="watch-sidebar">
                <div class="watch-side-card watch-side-card-actions">
                    <div class="watch-side-tabs">
                        <span><i>&#9733;</i> Danh gia</span>
                        <span><i>&#128172;</i> Binh luan</span>
                        <strong><?php echo watch_escape($ratingBadge); ?> Danh gia</strong>
                    </div>
                </div>

                <div class="watch-side-card">
                    <div class="watch-section-head">
                        <div>
                            <span class="watch-section-kicker">Thong tin phim</span>
                            <h2><?php echo $movieTitle; ?></h2>
                        </div>
                    </div>
                    <dl class="watch-info-list">
                        <div>
                            <dt>Quoc gia</dt>
                            <dd><?php echo $movieCountry; ?></dd>
                        </div>
                        <div>
                            <dt>Nam phat hanh</dt>
                            <dd><?php echo $movieYear; ?></dd>
                        </div>
                        <div>
                            <dt>Ngon ngu</dt>
                            <dd><?php echo $movieLanguage; ?></dd>
                        </div>
                        <div>
                            <dt>The loai</dt>
                            <dd><?php echo $genreSummary; ?></dd>
                        </div>
                        <div>
                            <dt>Luot xem</dt>
                            <dd><?php echo number_format($movieViews); ?></dd>
                        </div>
                        <div>
                            <dt>Ngay them</dt>
                            <dd><?php echo $movieAddedDate; ?></dd>
                        </div>
                    </dl>
                    <div class="watch-cast-placeholder">
                        <div class="watch-cast-avatar">IT</div>
                        <div>
                            <strong>Dien vien</strong>
                            <p>Du lieu dien vien chua co trong schema hien tai, sidebar dang uu tien thong tin phim that tu database.</p>
                        </div>
                    </div>
                </div>

                <div class="watch-side-card">
                    <div class="watch-section-head">
                        <div>
                            <span class="watch-section-kicker">De xuat cho ban</span>
                            <h2>Phim cung gu</h2>
                        </div>
                    </div>
                    <div class="recommend-list">
                        <?php foreach ($recommendations as $recommendMovie) { ?>
                            <a class="recommend-item" href="index.php?page_layout=chitietphim&id=<?php echo (int) $recommendMovie['movie_id']; ?>">
                                <img class="recommend-poster" src="../movies_admin/hinhanhphim/<?php echo watch_escape($recommendMovie['img']); ?>" alt="<?php echo watch_escape($recommendMovie['title']); ?>">
                                <span class="recommend-copy">
                                    <strong><?php echo watch_escape($recommendMovie['title']); ?></strong>
                                    <small><?php echo !empty($recommendMovie['language']) ? watch_escape($recommendMovie['language']) : 'Dang cap nhat'; ?></small>
                                    <em>T16 / <?php echo !empty($recommendMovie['release_year']) ? watch_escape($recommendMovie['release_year']) : 'Full'; ?> / <?php echo !empty($recommendMovie['country_name']) ? watch_escape($recommendMovie['country_name']) : 'Online'; ?></em>
                                </span>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <div class="mini-bottom-nav" aria-label="Dieu huong nhanh">
        <a href="index.php?page_layout=home" aria-label="Trang chu">
            <span>&#9776;</span>
            <small>Menu</small>
        </a>
        <a class="mini-bottom-brand" href="index.php?page_layout=home" aria-label="ITMOVIES">
            <strong>ITMOVIES</strong>
            <small>Phim hay moi ngay</small>
        </a>
        <a href="index.php?page_layout=timkiemphim" aria-label="Tim kiem">
            <span>&#8981;</span>
            <small>Search</small>
        </a>
    </div>
</section>

<?php if ($isLoggedIn && $videoSource !== null) { ?>
<script>
(function() {
    var startTime = Date.now();
    var viewUpdated = false;
    var commentInput = document.querySelector('[data-comment-input]');
    var commentCount = document.querySelector('[data-comment-count]');
    var shareButton = document.querySelector('[data-share-trigger]');
    var shareUrl = document.querySelector('.watch-page') ? document.querySelector('.watch-page').getAttribute('data-share-url') : window.location.href;
    var skipIntroButton = document.querySelector('[data-skip-intro]');
    var html5Video = document.querySelector('[data-watch-video]');

    function updateCounter() {
        if (!commentInput || !commentCount) {
            return;
        }
        commentCount.textContent = commentInput.value.length + ' / 1000';
    }

    function markViewAfterDelay() {
        if (viewUpdated) {
            return;
        }
        var elapsedSeconds = Math.floor((Date.now() - startTime) / 1000);
        if (elapsedSeconds >= 240) {
            viewUpdated = true;
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'update_view_count.php?id=<?php echo $movieId; ?>', true);
            xhr.send();
            return;
        }
        window.setTimeout(markViewAfterDelay, 3000);
    }

    if (commentInput) {
        commentInput.addEventListener('input', updateCounter);
        updateCounter();
    }

    if (shareButton) {
        shareButton.addEventListener('click', function() {
            if (navigator.share) {
                navigator.share({
                    title: 'Xem phim <?php echo addslashes((string) $movie['title']); ?>',
                    text: 'Xem phim <?php echo addslashes((string) $movie['title']); ?> tren ITMOVIES',
                    url: shareUrl
                }).catch(function() {});
                return;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(shareUrl).then(function() {
                    alert('Da sao chep lien ket phim.');
                }).catch(function() {
                    window.prompt('Sao chep lien ket phim:', shareUrl);
                });
                return;
            }

            window.prompt('Sao chep lien ket phim:', shareUrl);
        });
    }

    if (skipIntroButton) {
        skipIntroButton.addEventListener('click', function() {
            if (html5Video) {
                html5Video.currentTime = Math.min((html5Video.duration || 85), 85);
                html5Video.play().catch(function() {});
                return;
            }

            alert('Tinh nang bo qua gioi thieu hien chi ho tro voi video HTML5.');
        });
    }

    markViewAfterDelay();
})();
</script>
<?php } ?>
