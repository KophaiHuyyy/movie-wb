<?php
include_once "checkpermission.php";

$movieId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$query = array("page_layout" => "list_theloai_phim");

if ($movieId > 0) {
    $query["movie_id"] = $movieId;
}

header("Location: index.php?" . http_build_query($query));
exit();
?>
