<!-- Footer Start -->
<div class="container-fluid bg-dark text-light footer pt-5 mt-5 wow fadeIn" data-wow-delay="0.1s">
            <div class="container py-5">
                <div class="row g-5">
                    <div class="col-lg-3 col-md-6">
                        <h4 class="section-title ff-secondary text-start text-warning fw-normal mb-4">Công Ty</h4>
                        <a class="btn btn-link" href="">Về Chúng Tôi</a>
                        <a class="btn btn-link" href="">Liên Hệ</a>
                        <a class="btn btn-link" href="">Đặt Bàn</a>
                        <a class="btn btn-link" href="">Chính Sách Bảo Mật</a>
                        <a class="btn btn-link" href="">Điều Khoản & Điều Kiện</a>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <h4 class="section-title ff-secondary text-start text-warning fw-normal mb-4">Liên Hệ</h4>
                        <p class="mb-2"><i class="fa fa-map-marker-alt me-3"></i>123 Đường ABC, Quận XYZ, TP.HCM</p>
                        <p class="mb-2"><i class="fa fa-phone-alt me-3"></i>+84 123 456 789</p>
                        <p class="mb-2"><i class="fa fa-envelope me-3"></i>info@restoran.com</p>
                        <div class="d-flex pt-2">
                            <a class="btn btn-outline-light btn-social" href=""><i class="fab fa-twitter"></i></a>
                            <a class="btn btn-outline-light btn-social" href=""><i class="fab fa-facebook-f"></i></a>
                            <a class="btn btn-outline-light btn-social" href=""><i class="fab fa-youtube"></i></a>
                            <a class="btn btn-outline-light btn-social" href=""><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <h4 class="section-title ff-secondary text-start text-warning fw-normal mb-4">Giờ Mở Cửa</h4>
                        <h5 class="text-light fw-normal">Thứ Hai - Thứ Bảy</h5>
                        <p>09:00 - 21:00</p>
                        <h5 class="text-light fw-normal">Chủ Nhật</h5>
                        <p>10:00 - 20:00</p>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <h4 class="section-title ff-secondary text-start text-warning fw-normal mb-4">Bản Tin</h4>
                        <p>Đăng ký để nhận thông tin về các chương trình khuyến mãi mới nhất.</p>
                        <div class="position-relative mx-auto" style="max-width: 400px;">
                            <input class="form-control border-warning w-100 py-3 ps-4 pe-5" type="text" placeholder="Email của bạn">
                            <button type="button" class="btn btn-warning py-2 position-absolute top-0 end-0 mt-2 me-2">Đăng Ký</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="container">
                <div class="copyright">
                    <div class="row">
                        <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                            &copy; <a class="border-bottom" href="#">Restoran</a>, Bản Quyền Thuộc Về Chúng Tôi.
                        </div>
                        <div class="col-md-6 text-center text-md-end">
                            <div class="footer-menu">
                                <a href="">Trang Chủ</a>
                                <a href="">Cookie</a>
                                <a href="">Trợ Giúp</a>
                                <a href="">FAQ</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Footer End -->


        <!-- Back to Top -->
        <a href="#" class="btn btn-lg btn-warning btn-lg-square back-to-top"><i class="bi bi-arrow-up"></i></a>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="lib/wow/wow.min.js"></script>
    <script src="lib/easing/easing.min.js"></script>
    <script src="lib/waypoints/waypoints.min.js"></script>
    <script src="lib/counterup/counterup.min.js"></script>
    <script src="lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="lib/tempusdominus/js/moment.min.js"></script>
    <script src="lib/tempusdominus/js/moment-timezone.min.js"></script>
    <script src="lib/tempusdominus/js/tempusdominus-bootstrap-4.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Template Javascript -->
    <script src="js/main.js"></script>

<?php
// Hiển thị bong bóng chat khi khách hàng đã đăng nhập
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$showChatWidget = isset($_SESSION['khachhang_id']);
$chatWidgetUrl = '/CNM/chat_app/chat_widget.php';
?>

<?php if ($showChatWidget): ?>
    <style>
        .cnm-chat-bubble {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: #FEA116;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 9998;
            color: #fff;
            font-size: 26px;
        }
        .cnm-chat-panel {
            position: fixed;
            bottom: 96px;
            right: 24px;
            width: 360px;
            max-width: calc(100% - 32px);
            height: 520px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
            overflow: hidden;
            display: none;
            flex-direction: column;
            z-index: 9999;
        }
        .cnm-chat-panel.open {
            display: flex;
        }
        .cnm-chat-panel header {
            background: #FEA116;
            color: #fff;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .cnm-chat-panel header h5 {
            margin: 0;
            font-size: 16px;
        }
        .cnm-chat-panel header button {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
        }
        .cnm-chat-panel iframe {
            border: none;
            width: 100%;
            flex: 1;
        }
        @media (max-width: 576px) {
            .cnm-chat-panel {
                width: calc(100% - 32px);
                height: 70vh;
                bottom: 96px;
                right: 16px;
            }
        }
    </style>

    <div id="cnmChatBubble" class="cnm-chat-bubble" aria-label="Mở chat hỗ trợ">
        <i class="fas fa-comments"></i>
    </div>

    <div id="cnmChatPanel" class="cnm-chat-panel" aria-live="polite">
        <header>
            <h5>Chat hỗ trợ</h5>
            <button type="button" id="cnmChatClose" aria-label="Đóng chat">&times;</button>
        </header>
        <iframe src="<?php echo htmlspecialchars($chatWidgetUrl, ENT_QUOTES, 'UTF-8'); ?>"></iframe>
    </div>

    <script>
        (function(){
            const bubble = document.getElementById('cnmChatBubble');
            const panel = document.getElementById('cnmChatPanel');
            const closeBtn = document.getElementById('cnmChatClose');

            if(!bubble || !panel || !closeBtn){
                return;
            }

            const togglePanel = () => {
                panel.classList.toggle('open');
            };

            bubble.addEventListener('click', togglePanel);
            closeBtn.addEventListener('click', togglePanel);
        })();
    </script>
<?php endif; ?>
