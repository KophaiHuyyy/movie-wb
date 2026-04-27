<?php
$ID = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!isset($_SESSION['username']) && !isset($_SESSION['role'])) {
    $movieURL = "index.php?page_layout=xemphim&id=$ID";
    $_SESSION['redirect'] = $movieURL;
    echo "<h2 class='thongbao'>Vui lòng đăng nhập để xem phim!</h2>";
    return;
}

$_SESSION['redirect'] = "index.php?page_layout=reviewphim&id=$ID";
include_once "cauhinh.php";

$movie = null;
$firstGenreId = 0;
$videoSource = "";
$relatedMovies = array();

if ($ID > 0) {
    $movieResult = $connect->query("
        SELECT *
        FROM movies
        WHERE movie_id = $ID
        LIMIT 1
    ");

    if ($movieResult && $movieResult->num_rows > 0) {
        $movie = $movieResult->fetch_array(MYSQLI_ASSOC);
    }

    $genreResult = $connect->query("
        SELECT genres.theloai_id
        FROM movie_genre
        INNER JOIN genres ON movie_genre.theloai_id = genres.theloai_id
        WHERE movie_genre.movie_id = $ID
        ORDER BY genres.theloai_id ASC
        LIMIT 1
    ");

    if ($genreResult && $genreResult->num_rows > 0) {
        $genreRow = $genreResult->fetch_array(MYSQLI_ASSOC);
        $firstGenreId = isset($genreRow["theloai_id"]) ? (int) $genreRow["theloai_id"] : 0;
    }
}

if ($movie === null) {
    echo "<div class='movie-detail-empty'><h1>Không tìm thấy phim</h1><p>Phim có thể đã bị xóa hoặc đường dẫn không hợp lệ.</p><a href='index.php?page_layout=home'>Quay về trang chủ</a></div>";
    return;
}

$videoSource = !empty($movie["link2"]) ? trim((string) $movie["link2"]) : "";
if ($videoSource === "" && !empty($movie["link1"])) {
    $videoSource = trim((string) $movie["link1"]);
}

$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

if ($firstGenreId > 0) {
    $relatedQuery = "
        SELECT DISTINCT movies.*
        FROM movies
        INNER JOIN movie_genre ON movies.movie_id = movie_genre.movie_id
        WHERE movie_genre.theloai_id = $firstGenreId
          AND movies.movie_id <> $ID
        ORDER BY movies.view DESC, movies.movie_id DESC
        LIMIT $offset, $itemsPerPage
    ";

    $countQuery = "
        SELECT COUNT(DISTINCT movies.movie_id) AS total
        FROM movies
        INNER JOIN movie_genre ON movies.movie_id = movie_genre.movie_id
        WHERE movie_genre.theloai_id = $firstGenreId
          AND movies.movie_id <> $ID
    ";
} else {
    $relatedQuery = "
        SELECT *
        FROM movies
        WHERE movie_id <> $ID
        ORDER BY view DESC, movie_id DESC
        LIMIT $offset, $itemsPerPage
    ";

    $countQuery = "
        SELECT COUNT(*) AS total
        FROM movies
        WHERE movie_id <> $ID
    ";
}

$relatedResult = $connect->query($relatedQuery);
if ($relatedResult) {
    while ($rowmoviesNew = $relatedResult->fetch_array(MYSQLI_ASSOC)) {
        $relatedMovies[] = $rowmoviesNew;
    }
}

$totalItems = 0;
$countResult = $connect->query($countQuery);
if ($countResult && $countRow = $countResult->fetch_assoc()) {
    $totalItems = isset($countRow["total"]) ? (int) $countRow["total"] : 0;
}
$totalPages = max(1, (int) ceil($totalItems / $itemsPerPage));
?>

<div class="tenphim">
    <h2 style="color: white">Bạn đang xem phim <?php echo htmlspecialchars($movie['title']); ?> - <?php echo htmlspecialchars($movie['language']); ?> - Full HD - IT Phim</h2>
</div>

<div class="videophim">
    <?php if ($videoSource !== "") { ?>
        <iframe width="900" height="500" src="https://www.youtube.com/embed/<?php echo htmlspecialchars($videoSource); ?>" frameborder="0" allowfullscreen></iframe>
    <?php } else { ?>
        <div class="movie-detail-empty">
            <h1>Phim chưa có nguồn review</h1>
            <p>Admin chưa cấu hình server review cho nội dung này.</p>
        </div>
    <?php } ?>
</div>

<div class="chitiet">
    <h3 style="color: white">Lượt xem: <?php echo (int) $movie['view']; ?></h3>
    <a href="xemsau.php?id=<?php echo $ID; ?>&iduser=<?php echo (int) $_SESSION['user_id']; ?>"><button class="btnxemphim" type="button">Lưu Xem Sau</button></a>
</div>

<h2 class="phimcapnhat">PHIM ĐỀ CỬ CHO BẠN</h2>
<section>
    <div class='left-column'>
        <?php foreach ($relatedMovies as $rowmoviesNew) { ?>
            <div class='card_moivenew'>
                <a href='index.php?page_layout=chitietphim&id=<?php echo (int) $rowmoviesNew["movie_id"]; ?>' class='card-link'>
                    <img class='hinhanhphim_new' src='../movies_admin/hinhanhphim/<?php echo htmlspecialchars($rowmoviesNew["img"]); ?>' style='width: 140px; height: 180px;'>
                    <span class='tenphim'><?php echo htmlspecialchars($rowmoviesNew["title"]); ?></span><br />
                </a>
            </div>
        <?php } ?>
    </div>
    <div class='right-column'>
        <h2 class="phimsapchieu">PHIM SẮP CHIẾU</h2>
    </div>
</section>

<div class='pagination'>
    <?php if ($currentPage > 1) { ?>
        <a href='?page_layout=reviewphim&id=<?php echo $ID; ?>&page=<?php echo $currentPage - 1; ?>'><img src='anhnen/previous.png' alt='Back' style='width: 20px; height: 20px;'></a>
    <?php } ?>

    <?php for ($i = 1; $i <= $totalPages; $i++) {
        if ($totalPages > 5 && abs($i - $currentPage) > 2) {
            continue;
        }
        echo "<a href='?page_layout=reviewphim&id=$ID&page=$i'>$i</a> ";
    } ?>

    <?php if ($currentPage < $totalPages) { ?>
        <a href='?page_layout=reviewphim&id=<?php echo $ID; ?>&page=<?php echo $currentPage + 1; ?>'><img src='anhnen/next.png' alt='Next' style='width: 20px; height: 20px;'></a>
    <?php } ?>
</div>

<script>
var startTime = new Date().getTime();
var timeout;
function changeBackgroundImage() {
    var currentTime = new Date().getTime();
    var elapsedTime = Math.floor((currentTime - startTime) / 1000);
    if (elapsedTime >= 120) {
        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status !== 200) {
                alert('Error Code: ' + xhr.status);
                alert('Error Message: ' + xhr.statusText);
            }
        };
        xhr.open('GET', 'update_view_count.php?id=<?php echo $ID; ?>');
        xhr.send();
        clearTimeout(timeout);
    } else {
        timeout = setTimeout(changeBackgroundImage, 3000);
    }
}
changeBackgroundImage();
</script>
