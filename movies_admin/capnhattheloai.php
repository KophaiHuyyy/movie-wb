<?php
include_once "checkpermission.php";

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$target = "index.php?page_layout=list_theloai";

if ($id > 0) {
    $target .= "&drawer=edit&id=" . $id;
}

header("Location: " . $target);
exit();
?>
