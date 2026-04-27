<?php
include_once "cauhinh.php";

if (isset($_POST["btnhuy"])) {
    header("Location: index.php?page_layout=list_user");
    exit();
}

$hoten = isset($_POST["fullname"]) ? trim((string) $_POST["fullname"]) : "";
$username = isset($_POST["username"]) ? trim((string) $_POST["username"]) : "";
$password = isset($_POST["password"]) ? trim((string) $_POST["password"]) : "";
$passwordXacNhan = isset($_POST["passwordxacnhan"]) ? trim((string) $_POST["passwordxacnhan"]) : "";
$email = isset($_POST["email"]) ? trim((string) $_POST["email"]) : "";
$roleValue = isset($_POST["myRadio"]) ? (string) $_POST["myRadio"] : "0";
$role = $roleValue === "1" ? 1 : 0;
$message = "";

function redirect_add_user($status, $message, $hoten = "", $username = "", $email = "", $role = "0")
{
    $query = http_build_query(array(
        "page_layout" => "them_user",
        "status" => $status,
        "message" => $message,
        "fullname" => $hoten,
        "username" => $username,
        "email" => $email,
        "role" => $role,
    ));

    header("Location: index.php?" . $query);
    exit();
}

if ($hoten === "") {
    $message = "Họ tên không được bỏ trống.";
} elseif ($username === "") {
    $message = "Nhập tên tài khoản.";
} elseif ($email === "") {
    $message = "Email không được bỏ trống.";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $message = "Email không đúng định dạng.";
} elseif ($roleValue !== "0" && $roleValue !== "1") {
    $message = "Quyền tài khoản không hợp lệ.";
} elseif ($password === "") {
    $message = "Mật khẩu không được bỏ trống.";
} elseif ($passwordXacNhan === "") {
    $message = "Vui lòng xác nhận lại mật khẩu.";
} elseif ($passwordXacNhan !== $password) {
    $message = "Mật khẩu xác nhận không đúng.";
}

if ($message !== "") {
    redirect_add_user("error", $message, $hoten, $username, $email, (string) $role);
}

$checkStatement = $connect->prepare("SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1");
$exists = false;

if ($checkStatement && $checkStatement->bind_param("ss", $username, $email) && $checkStatement->execute()) {
    $result = $checkStatement->get_result();
    if ($result && $result->fetch_assoc()) {
        $exists = true;
    }
    if ($result) {
        $result->free();
    }
}

if ($checkStatement) {
    $checkStatement->close();
}

if ($exists) {
    redirect_add_user("error", "Username hoặc email đã tồn tại.", $hoten, $username, $email, (string) $role);
}

$insertStatement = $connect->prepare("INSERT INTO users (username, password, email, fullname, role) VALUES (?, ?, ?, ?, ?)");

if ($insertStatement && $insertStatement->bind_param("ssssi", $username, $password, $email, $hoten, $role) && $insertStatement->execute()) {
    if ($insertStatement) {
        $insertStatement->close();
    }
    $connect->close();
    header("Location: index.php?page_layout=list_user&notice=created");
    exit();
}

if ($insertStatement) {
    $insertStatement->close();
}

$connect->close();
redirect_add_user("error", "Không thể tạo tài khoản. Vui lòng thử lại.", $hoten, $username, $email, (string) $role);
