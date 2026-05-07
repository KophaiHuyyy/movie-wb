<?php
session_start();

function getConnection() {
    $servername = "sql204.infinityfree.com";
    $username = "if0_41776263";
    $password = "Huynd2003";
    $dbname = "if0_41776263_review";

    $connect = new mysqli($servername, $username, $password, $dbname);

    if ($connect->connect_error) {
        die("Không thể kết nối: " . $connect->connect_error);
    }

    return $connect;
}

function updateViewCount() {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        return;
    }

    $movieId = (int) $_GET['id'];
    $connect = getConnection();

    $stmt = $connect->prepare("UPDATE movies SET view = view + 1 WHERE movie_id = ?");
    $stmt->bind_param("i", $movieId);
    $stmt->execute();

    $stmt->close();
    $connect->close();
}

function checkTimeout() {
    if (!isset($_SESSION['start_time'])) {
        $_SESSION['start_time'] = time();
    }

    $elapsedTime = time() - $_SESSION['start_time'];
    $timeout = 120000;

    if ($elapsedTime >= $timeout) {
        updateViewCount();
        $_SESSION['start_time'] = time();
    }
}

checkTimeout();
?>