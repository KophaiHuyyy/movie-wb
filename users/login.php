<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập</title>
    <link rel="stylesheet" href="style_users.css">
</head>

<body>
    <div class="login-page">
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
            <section class="login-panel">
                <h1>Đăng nhập</h1>
                <p class="login-subtitle">Truy cập kho phim, danh sách xem sau và trải nghiệm cá nhân hóa của bạn.</p>

                <form id="login-form" action="xuly_login.php" method="post">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="Nhap email cua ban" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Mật khẩu</label>
                        <input type="password" id="password" name="password" placeholder="Nhập mật khẩu" required>
                    </div>

                    <div class="login-options">
                        <label class="remember-box">
                            <input type="checkbox" name="remember_me" value="1">
                            <span>Ghi nhớ đăng nhập</span>
                        </label>
                        <a href="forgot_password.php">Quên mật khẩu?</a>
                    </div>

                    <button type="submit" class="btn btn-submit">Đăng nhập</button>

                    <div class="divider"><span>hoặc</span></div>

                    <a class="btn btn-register" href="register.php">Tạo tài khoản mới</a>
                </form>

                <div class="login-note">
                    <p>Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
                    <small>Bằng cách đăng nhập, bạn đồng ý với điều khoản sử dụng của website.</small>
                </div>
            </section>
        </main>
    </div>
</body>

</html>