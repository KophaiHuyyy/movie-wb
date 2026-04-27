<?php
include_once "cauhinh.php";

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$hoten = isset($_POST["fullname"]) ? trim((string) $_POST["fullname"]) : "";
$password = isset($_POST["password"]) ? trim((string) $_POST["password"]) : "";
$passwordXacNhan = isset($_POST["passwordxacnhan"]) ? trim((string) $_POST["passwordxacnhan"]) : "";
$email = isset($_POST["email"]) ? trim((string) $_POST["email"]) : "";
$quyen = isset($_POST["myRadio"]) && $_POST["myRadio"] === "1" ? 1 : 0;
$message = "";

if (isset($_POST["btnhuy"])) {
    header("Location: index.php?page_layout=list_user");
    exit();
}

if ($id <= 0) {
    $message = "Không tìm thấy tài khoản cần cập nhật.";
} elseif ($hoten === "") {
    $message = "Họ tên không được bỏ trống.";
} elseif ($email === "") {
    $message = "Email không được bỏ trống.";
} elseif (($password === "" && $passwordXacNhan !== "") || ($password !== "" && $passwordXacNhan === "")) {
    $message = "Vui lòng nhập đầy đủ hai ô mật khẩu nếu muốn cập nhật mật khẩu.";
} elseif ($password !== "" && $password !== $passwordXacNhan) {
    $message = "Mật khẩu xác nhận không đúng.";
}

if ($message === "") {
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
        $message = "Không tìm thấy tài khoản cần cập nhật.";
    }
}

if ($message === "") {
    if ($password === "") {
        $statement = $connect->prepare("UPDATE users SET email = ?, fullname = ?, role = ? WHERE user_id = ?");
        if ($statement && $statement->bind_param("ssii", $email, $hoten, $quyen, $id) && $statement->execute()) {
            $message = "Đã cập nhật lại thông tin tài khoản.";
        } else {
            $message = "Không thể cập nhật tài khoản. Vui lòng thử lại.";
        }
    } else {
        $statement = $connect->prepare("UPDATE users SET password = ?, email = ?, fullname = ?, role = ? WHERE user_id = ?");
        if ($statement && $statement->bind_param("sssii", $password, $email, $hoten, $quyen, $id) && $statement->execute()) {
            $message = "Đã cập nhật lại thông tin tài khoản.";
        } else {
            $message = "Không thể cập nhật tài khoản. Vui lòng thử lại.";
        }
    }

    if (isset($statement) && $statement) {
        $statement->close();
    }
}

$connect->close();
?>
<?php if ($message === "Đã cập nhật lại thông tin tài khoản."): ?>
    <script>
        window.location.href = 'index.php?page_layout=list_user';
        alert("<?php echo $message; ?>");
    </script>
<?php else: ?>
    <script>
        window.location.href = 'index.php?page_layout=capnhattaikhoan&id=<?php echo (int) $id; ?>';
        alert("<?php echo $message; ?>");
    </script>
<?php endif; ?>
