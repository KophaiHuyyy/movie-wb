<?php
include_once "cauhinh.php";

$hoten = isset($_POST["fullname"]) ? trim((string) $_POST["fullname"]) : "";
$username = isset($_POST["username"]) ? trim((string) $_POST["username"]) : "";
$password = isset($_POST["password"]) ? trim((string) $_POST["password"]) : "";
$passwordXacNhan = isset($_POST["passwordxacnhan"]) ? trim((string) $_POST["passwordxacnhan"]) : "";
$email = isset($_POST["email"]) ? trim((string) $_POST["email"]) : "";
$message = "";

if (isset($_POST["btnhuy"])) {
    header("Location: index.php?page_layout=list_user");
    exit();
}

if ($hoten === "") {
    $message = "Họ tên không được bỏ trống.";
} elseif ($username === "") {
    $message = "Nhập tên tài khoản.";
} elseif ($password === "") {
    $message = "Mật khẩu không được bỏ trống.";
} elseif ($passwordXacNhan === "") {
    $message = "Vui lòng xác nhận lại mật khẩu.";
} elseif ($passwordXacNhan !== $password) {
    $message = "Mật khẩu xác nhận không đúng.";
} elseif ($email === "") {
    $message = "Email không được bỏ trống.";
}

if ($message === "") {
    $checkStatement = $connect->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
    $exists = false;

    if ($checkStatement && $checkStatement->bind_param("s", $username) && $checkStatement->execute()) {
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
        $message = "Tên tài khoản đã tồn tại.";
    }
}

if ($message === "") {
    $role = 0;
    $insertStatement = $connect->prepare("INSERT INTO users (username, password, email, fullname, role) VALUES (?, ?, ?, ?, ?)");

    if ($insertStatement && $insertStatement->bind_param("ssssi", $username, $password, $email, $hoten, $role) && $insertStatement->execute()) {
        $message = "Bạn đã tạo tài khoản thành công.";
    } else {
        $message = "Không thể tạo tài khoản. Vui lòng thử lại.";
    }

    if ($insertStatement) {
        $insertStatement->close();
    }
}

$connect->close();
?>
<?php if ($message === "Bạn đã tạo tài khoản thành công."): ?>
    <script>
        window.location.href = 'index.php?page_layout=list_user';
        alert("<?php echo $message; ?>");
    </script>
<?php else: ?>
    <script>
        window.location.href = 'index.php?page_layout=them_user';
        alert("<?php echo $message; ?>");
    </script>
<?php endif; ?>
