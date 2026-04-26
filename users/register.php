<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký tài khoản</title>
    <link rel="stylesheet" href="style_users.css">
</head>

<body>
    <script src="javascript_register.js"></script>

    <div class="login-page auth-register-page">
        <div class="login-backdrop">
            <div class="poster-wall">
                <div class="poster-track poster-track-a">
                    <img src="../movies_admin/hinhanhphim/endgame.jpg" alt="Poster Endgame">
                    <img src="../movies_admin/hinhanhphim/tanglopitewon.jpg" alt="Poster Itaewon">
                    <img src="../movies_admin/hinhanhphim/spider-man.jpg" alt="Poster Spider Man">
                    <img src="../movies_admin/hinhanhphim/sisu.jpg" alt="Poster Sisu">
                    <img src="../movies_admin/hinhanhphim/thanhbai2.jpg" alt="Poster Thanh Bai 2">
                    <img src="../movies_admin/hinhanhphim/nguohungtiachop.jpg" alt="Poster Flash">
                    <img src="../movies_admin/hinhanhphim/ladaihowl.jpg" alt="Poster Howl">
                    <img src="../movies_admin/hinhanhphim/tonngokhongchautinhtri.jpg" alt="Poster Ton Ngo Khong">
                </div>
                <div class="poster-track poster-track-b">
                    <img src="../movies_admin/hinhanhphim/hinh0.jpg" alt="Poster Nha Ba Nu">
                    <img src="../movies_admin/hinhanhphim/hinh1.jpg" alt="Poster Mat Biec">
                    <img src="../movies_admin/hinhanhphim/hinh2.jpg" alt="Poster Harry Potter">
                    <img src="../movies_admin/hinhanhphim/hinh4.jpg" alt="Poster Toi Thay Hoa Vang">
                    <img src="../movies_admin/hinhanhphim/hinh5.jpg" alt="Poster Black Panther">
                    <img src="../movies_admin/hinhanhphim/hinh6.jpg" alt="Poster Guardians">
                    <img src="../movies_admin/hinhanhphim/hondaocuaGiovanni.jpg" alt="Poster Giovanni">
                    <img src="../movies_admin/hinhanhphim/kunfupanda1.jpg" alt="Poster Kungfu Panda">
                </div>
            </div>
            <div class="backdrop-overlay"></div>
        </div>

        <header class="login-brand">
            <a href="index.php?page_layout=home">ITMOVIE</a>
        </header>

        <main class="login-shell">
            <section class="login-panel register-panel">
                <h1>Đăng ký</h1>
                <p class="login-subtitle">Tạo tài khoản mới để lưu danh sách xem sau, xem review và đăng nhập vào hệ
                    thống phim.</p>

                <form action="xuly_register.php" method="post" name="f_themuser" onsubmit="return validateForm()">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="fullname">Họ tên</label>
                            <input type="text" id="fullname" name="fullname" placeholder="Nhập họ tên" required>
                        </div>

                        <div class="form-group">
                            <label for="username">Tên tài khoản</label>
                            <input type="text" id="username" name="username" placeholder="Nhập tên tài khoản" required>
                        </div>

                        <div class="form-group">
                            <label for="password">Mật khẩu</label>
                            <input type="password" id="password" name="password" placeholder="Nhập mật khẩu" required>
                        </div>

                        <div class="form-group">
                            <label for="passwordxacnhan">Xác nhận mật khẩu</label>
                            <input type="password" id="passwordxacnhan" name="passwordxacnhan"
                                placeholder="Nhập lại mật khẩu" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="text" id="email" name="email" placeholder="Nhập email" required>
                    </div>

                    <div class="form-group">
                        <label>Quyền hạn</label>
                        <label class="radio-card">
                            <input type="radio" name="myRadio" id="radio2" value="0" checked>
                            <span>Người dùng xem phim</span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-submit" name="btndangky">Đăng ký</button>

                    <div class="divider"><span>hoặc</span></div>

                    <a class="btn btn-register" href="login.php">Đã có tài khoản? Đăng nhập</a>
                </form>

                <div class="login-note">
                    <p>Bạn đã từng đăng ký? <a href="login.php">Quay lại đăng nhập</a></p>
                    <small>Thông tin đăng ký sẽ được xử lý bởi handler hiện tại `xuly_register.php`.</small>
                </div>
            </section>
        </main>
    </div>
</body>

</html>