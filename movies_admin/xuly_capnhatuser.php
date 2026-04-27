<?php
include_once "cauhinh.php";

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

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
$quyen = $roleValue === "1" ? 1 : 0;

function redirect_edit_user($id, $status, $message, $hoten = "", $username = "", $email = "", $role = "0")
{
    $query = http_build_query(array(
        "page_layout" => "capnhattaikhoan",
        "id" => (int) $id,
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

if ($id <= 0) {
    redirect_edit_user($id, "error", "Không tìm thấy tài khoản cần cập nhật.");
}

if ($hoten === "") {
    redirect_edit_user($id, "error", "Họ tên không được bỏ trống.", $hoten, $username, $email, (string) $quyen);
}

if ($username === "") {
    redirect_edit_user($id, "error", "Username không được bỏ trống.", $hoten, $username, $email, (string) $quyen);
}

if ($email === "") {
    redirect_edit_user($id, "error", "Email không được bỏ trống.", $hoten, $username, $email, (string) $quyen);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_edit_user($id, "error", "Email không đúng định dạng.", $hoten, $username, $email, (string) $quyen);
}

if ($roleValue !== "0" && $roleValue !== "1") {
    redirect_edit_user($id, "error", "Quyền tài khoản không hợp lệ.", $hoten, $username, $email, (string) $quyen);
}

if (($password === "" && $passwordXacNhan !== "") || ($password !== "" && $passwordXacNhan === "")) {
    redirect_edit_user($id, "error", "Vui lòng nhập đầy đủ hai ô mật khẩu nếu muốn cập nhật mật khẩu.", $hoten, $username, $email, (string) $quyen);
}

if ($password !== "" && $password !== $passwordXacNhan) {
    redirect_edit_user($id, "error", "Mật khẩu xác nhận không đúng.", $hoten, $username, $email, (string) $quyen);
}

$existsStatement = $connect->prepare("SELECT COUNT(*) AS total FROM users WHERE user_id = ?");
$existsTotal = 0;

if ($existsStatement && $existsStatement->bind_param("i", $id) && $existsStatement->execute()) {
    $result = $existsStatement->get_result();
    if ($result) {
        $row = $result->fetch_assoc();
        $existsTotal = isset($row["total"]) ? (int) $row["total"] : 0;
        $result->free();
    }
}

if ($existsStatement) {
    $existsStatement->close();
}

if ($existsTotal <= 0) {
    $connect->close();
    redirect_edit_user($id, "error", "Không tìm thấy tài khoản cần cập nhật.", $hoten, $username, $email, (string) $quyen);
}

$duplicateStatement = $connect->prepare("SELECT COUNT(*) AS total FROM users WHERE (username = ? OR email = ?) AND user_id <> ?");
$duplicateTotal = 0;

if ($duplicateStatement && $duplicateStatement->bind_param("ssi", $username, $email, $id) && $duplicateStatement->execute()) {
    $result = $duplicateStatement->get_result();
    if ($result) {
        $row = $result->fetch_assoc();
        $duplicateTotal = isset($row["total"]) ? (int) $row["total"] : 0;
        $result->free();
    }
}

if ($duplicateStatement) {
    $duplicateStatement->close();
}

if ($duplicateTotal > 0) {
    $connect->close();
    redirect_edit_user($id, "error", "Username hoặc email đã tồn tại.", $hoten, $username, $email, (string) $quyen);
}

if ($password === "") {
    $statement = $connect->prepare("UPDATE users SET username = ?, email = ?, fullname = ?, role = ? WHERE user_id = ?");
    $ok = $statement && $statement->bind_param("sssii", $username, $email, $hoten, $quyen, $id) && $statement->execute();
} else {
    $statement = $connect->prepare("UPDATE users SET username = ?, password = ?, email = ?, fullname = ?, role = ? WHERE user_id = ?");
    $ok = $statement && $statement->bind_param("ssssii", $username, $password, $email, $hoten, $quyen, $id) && $statement->execute();
}

if ($statement) {
    $statement->close();
}

$connect->close();

if ($ok) {
    header("Location: index.php?page_layout=list_user&notice=updated");
    exit();
}

redirect_edit_user($id, "error", "Không thể cập nhật tài khoản. Vui lòng thử lại.", $hoten, $username, $email, (string) $quyen);
