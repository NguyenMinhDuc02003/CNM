<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../class/clsconnect.php';
$db = new connect_db();

$projectRoot = $_SERVER['DOCUMENT_ROOT'] . '/CNM/Admin/kaiadmin-lite-1.2.0/';
require_once $projectRoot . 'class/clsDonHang.php';

$orderService = new clsDonHang();

$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
$tableRow = null;
$orderRow = null;
$orderId = 0;
$successMessages = [];
$errorMessages = [];

if ($token === '') {
    $errorMessages[] = 'Mã QR không hợp lệ. Vui lòng quét lại.';
} else {
    $tableRow = $orderService->getTableByQrToken($token);
    if (!$tableRow) {
        $errorMessages[] = 'Không tìm thấy bàn từ mã QR này.';
    } else {
        $orderRow = $orderService->getOpenOrderForTable((int)$tableRow['idban']);
        if (!$orderRow) {
            $errorMessages[] = 'Bàn ' . htmlspecialchars($tableRow['SoBan'] ?? '#' . $tableRow['idban']) . ' chưa được mở phục vụ.';
        } else {
            $orderId = (int)$orderRow['idDH'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $orderId > 0) {
    $payloadRaw = $_POST['order_payload'] ?? '[]';
    $payload = json_decode($payloadRaw, true);
    if (!is_array($payload) || empty($payload)) {
        $errorMessages[] = 'Danh sách món ăn chưa hợp lệ.';
    } else {
        try {
            $result = $orderService->addItemsToOrder($orderId, $payload, null);
            $addedCount = count($result['inserted']);
            if ($addedCount > 0) {
                $successMessages[] = 'Đã gửi ' . $addedCount . ' món tới bếp. Nhân viên sẽ xác nhận trong chốc lát.';
            } else {
                $errorMessages[] = 'Không thể thêm món. Vui lòng thử lại.';
            }
        } catch (Throwable $th) {
            $errorMessages[] = $th->getMessage();
        }
    }
}

$menuRows = $db->xuatdulieu(
    "SELECT idmonan, tenmonan, DonGia, DonViTinh, mota, hinhanh, TrangThai, hoatdong
     FROM monan
     WHERE TrangThai = 'approved' AND hoatdong = 'active'
     ORDER BY tenmonan ASC"
);

$menuItems = [];
foreach ($menuRows as $row) {
    $image = 'img/menu_1.jpg';
    if (!empty($row['hinhanh'])) {
        $candidate = 'img/' . ltrim($row['hinhanh'], '/');
        if (file_exists(__DIR__ . '/../../' . $candidate)) {
            $image = $candidate;
        }
    }
    $menuItems[] = [
        'id' => (int)$row['idmonan'],
        'name' => $row['tenmonan'],
        'price' => (float)$row['DonGia'],
        'unit' => $row['DonViTinh'],
        'description' => $row['mota'],
        'image' => $image,
    ];
}

$tableName = $tableRow['SoBan'] ?? ($tableRow ? ('Bàn #' . $tableRow['idban']) : '');
$orderCode = $orderRow['MaDonHang'] ?? ($orderId ? ('DH#' . $orderId) : '');
?>

<style>
    .qr-order-wrapper .card {
        border-radius: 18px;
    }
    .qr-order-wrapper .card-body {
        padding: 1.5rem;
    }
    .qr-order-wrapper #menuGrid .card img {
        border-top-left-radius: 18px;
        border-top-right-radius: 18px;
    }
    .qr-order-wrapper .btn,
    .qr-order-wrapper .form-control {
        border-radius: 999px;
    }
    .qr-order-wrapper .table > :not(caption) > * > * {
        padding-top: 0.9rem;
        padding-bottom: 0.9rem;
    }
    .cart-row {
        position: relative;
        transition: transform 0.2s ease, background-color 0.2s ease;
        touch-action: pan-y;
    }
    .cart-row .delete-btn {
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease;
    }
    .cart-row.swipe-revealed {
        background-color: rgba(255, 193, 7, 0.12);
    }
    .cart-row.swipe-revealed .delete-btn {
        opacity: 1;
        pointer-events: auto;
    }
    @media (min-width: 992px) {
        .cart-row .delete-btn {
            opacity: 1;
            pointer-events: auto;
        }
    }
    .cart-fab {
        position: fixed;
        right: 1rem;
        bottom: 1rem;
        border-radius: 999px;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.25);
        z-index: 1050;
    }
    .cart-fab {
        position: fixed;
        right: 1rem;
        bottom: 1rem;
        border-radius: 999px;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.25);
        z-index: 1050;
    }
    @media (max-width: 991.98px) {
        .qr-order-wrapper .card-body {
            padding: 1.25rem;
        }
        .qr-order-wrapper #menuGrid .card {
            border-radius: 16px;
        }
        .cart-fab {
            bottom: 80px;
        }
        .cart-fab {
            bottom: 80px;
        }
    }
    @media (max-width: 767.98px) {
        .qr-order-wrapper .row {
            flex-direction: column;
        }
        .qr-order-wrapper .row > div {
            width: 100%;
        }
        .qr-order-wrapper #menuGrid .card img {
            height: 140px;
        }
        .qr-order-wrapper .input-group {
            width: 100% !important;
        }
        .qr-order-wrapper #menuGrid .card {
            margin-bottom: 0.75rem;
        }
    }
    @media (max-width: 575.98px) {
        .qr-order-wrapper .card-body {
            padding: 1rem;
        }
        .qr-order-wrapper h1 {
            font-size: 1.75rem;
        }
        .qr-order-wrapper .btn-lg {
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
            font-size: 1rem;
        }
        .qr-order-wrapper #menuGrid .card img {
            height: 120px;
        }
        .qr-order-wrapper .table thead {
            font-size: 0.85rem;
        }
    }
</style>

<div class="container py-4 py-md-5 qr-order-wrapper">
    <div class="text-center mb-4">
        <h1 class="fw-bold text-warning"><i class="fas fa-mobile-alt me-2"></i>Gọi món tại bàn</h1>
        <p class="text-muted mb-0">Quý khách chọn món và gửi yêu cầu, nhân viên sẽ xác nhận và phục vụ ngay.</p>
    </div>

    <?php foreach ($successMessages as $message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>

    <?php foreach ($errorMessages as $message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>

    <?php if ($orderId === 0): ?>
        <div class="alert alert-warning">
            <i class="fas fa-info-circle me-2"></i>Vui lòng liên hệ nhân viên để mở bàn trước khi gọi món bằng QR.
        </div>
        <?php return; ?>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                        <div>
                            <h4 class="mb-0"><?php echo htmlspecialchars($tableName); ?></h4>
                            <small class="text-muted">Đơn hiện tại: <?php echo htmlspecialchars($orderCode); ?></small>
                        </div>
                        <div class="input-group input-group-sm" style="max-width: 280px;">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search"></i></span>
                            <input type="search" class="form-control border-start-0" placeholder="Tìm món theo tên..." id="menuSearch">
                        </div>
                        <button type="button" class="btn btn-outline-primary d-none d-lg-inline-flex" id="scrollToCartBtn">
                            <i class="fas fa-shopping-basket me-2"></i>Giỏ gọi món
                        </button>
                    </div>
                    <div class="row g-3" id="menuGrid"></div>
                    <div class="text-center text-muted py-4 d-none" id="menuEmpty">
                        <i class="fas fa-info-circle me-2"></i>Không tìm thấy món theo từ khóa.
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 mb-4" id="orderCard">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="fas fa-clipboard-list text-warning me-2"></i>Giỏ gọi món</h5>
                        <span class="badge bg-light text-dark"><span id="draftCount">0</span> món</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Món</th>
                                    <th class="text-center" style="width: 130px;">SL</th>
                                    <th class="text-end" style="width: 120px;">Thành tiền</th>
                                    <th style="width: 60px;"></th>
                                </tr>
                            </thead>
                            <tbody id="draftBody">
                                <tr id="draftEmptyRow">
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="fas fa-info-circle me-2"></i>Chưa có món nào trong giỏ.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2">
                        <div class="text-muted">Tạm tính: <strong id="cartTotalLabel">0đ</strong></div>
                        <form method="post" class="d-flex flex-column flex-sm-row gap-2 mt-2 mt-sm-0">
                            <input type="hidden" name="order_payload" id="orderPayloadField" value="">
                            <button type="button" class="btn btn-outline-secondary" id="clearDraftBtn">Làm mới</button>
                            <button type="button" class="btn btn-warning btn-lg flex-fill" id="sendOrderBtn">
                                <i class="fas fa-paper-plane me-2"></i>Gửi yêu cầu
                            </button>
                        </form>
                    </div>
                    <div class="alert alert-light border mt-3 mb-0 small">
                        Nhấn "Gửi yêu cầu" để thông báo cho bếp. Nhân viên sẽ xác nhận món ăn trước khi chế biến.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<button type="button" class="btn btn-warning cart-fab d-lg-none" id="mobileCartToggle">
    <i class="fas fa-shopping-basket me-2"></i>
    <span id="mobileCartSummary">0 món • 0đ</span>
</button>

<div class="offcanvas offcanvas-bottom d-lg-none" tabindex="-1" id="mobileCartDrawer">
    <div class="offcanvas-header">
        <h5 class="mb-0">Giỏ gọi món (<span id="mobileCartCount">0</span> món)</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Món</th>
                        <th class="text-center" style="width: 100px;">SL</th>
                        <th class="text-end" style="width: 120px;">Thành tiền</th>
                        <th style="width: 60px;"></th>
                    </tr>
                </thead>
                <tbody id="draftBodyMobile">
                    <tr id="draftEmptyRowMobile">
                        <td colspan="4" class="text-center text-muted py-4">
                            <i class="fas fa-info-circle me-2"></i>Chưa có món nào trong giỏ.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
            <div class="text-muted">Tạm tính: <strong id="mobileCartTotal">0đ</strong></div>
            <div class="d-flex gap-2 w-100">
                <button type="button" class="btn btn-outline-secondary flex-fill" data-trigger="clearDraft">Làm mới</button>
                <button type="button" class="btn btn-primary flex-fill" data-trigger="sendOrder">
                    <i class="fas fa-paper-plane me-2"></i>Gửi yêu cầu
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const menuData = <?php echo json_encode($menuItems, JSON_UNESCAPED_UNICODE); ?>;
        const menuGrid = document.getElementById('menuGrid');
        const menuEmpty = document.getElementById('menuEmpty');
        const menuSearch = document.getElementById('menuSearch');
        const draftBody = document.getElementById('draftBody');
        const draftCount = document.getElementById('draftCount');
        const draftEmptyRow = document.getElementById('draftEmptyRow');
        const draftBodyMobile = document.getElementById('draftBodyMobile');
        const draftEmptyRowMobile = document.getElementById('draftEmptyRowMobile');
        const orderPayloadField = document.getElementById('orderPayloadField');
        const sendOrderBtn = document.getElementById('sendOrderBtn');
        const clearDraftBtn = document.getElementById('clearDraftBtn');
        const cartTotalLabel = document.getElementById('cartTotalLabel');
        const mobileCartSummary = document.getElementById('mobileCartSummary');
        const mobileCartTotal = document.getElementById('mobileCartTotal');
        const mobileCartCount = document.getElementById('mobileCartCount');
        const mobileCartToggle = document.getElementById('mobileCartToggle');
        const mobileCartDrawer = document.getElementById('mobileCartDrawer');
        const scrollToCartBtn = document.getElementById('scrollToCartBtn');
        const orderCard = document.getElementById('orderCard');

        const draftTables = [];
        if (draftBody && draftEmptyRow) {
            draftTables.push({ body: draftBody, emptyRow: draftEmptyRow });
        }
        if (draftBodyMobile && draftEmptyRowMobile) {
            draftTables.push({ body: draftBodyMobile, emptyRow: draftEmptyRowMobile });
        }

        let draftItems = [];
        let currentSearch = '';

        const formatCurrency = (value) => new Intl.NumberFormat('vi-VN').format(value) + 'đ';

        const renderMenu = () => {
            menuGrid.innerHTML = '';
            const filtered = menuData.filter((item) => {
                if (currentSearch === '') {
                    return true;
                }
                return item.name.toLowerCase().includes(currentSearch.toLowerCase());
            });
            if (filtered.length === 0) {
                menuEmpty.classList.remove('d-none');
                return;
            }
            menuEmpty.classList.add('d-none');

            filtered.forEach((item) => {
                const col = document.createElement('div');
                col.className = 'col-12 col-md-6';
                col.innerHTML = `
                    <div class="card h-100 border-0 shadow-sm">
                        <img src="${item.image}" class="card-img-top" alt="${item.name}" style="height: 160px; object-fit: cover;">
                        <div class="card-body">
                            <h6 class="mb-1">${item.name}</h6>
                            <p class="text-muted small mb-2">${item.description ?? ''}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <strong class="text-primary">${formatCurrency(item.price)}</strong>
                                <button class="btn btn-sm btn-warning" data-add-dish="${item.id}">
                                    <i class="fas fa-plus"></i> Thêm
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                menuGrid.appendChild(col);
            });

            menuGrid.querySelectorAll('[data-add-dish]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const dishId = parseInt(btn.getAttribute('data-add-dish'), 10);
                    addDishToDraft(dishId);
                });
            });
        };

        const updateCartSummary = () => {
            const total = draftItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const totalQty = draftItems.reduce((sum, item) => sum + item.quantity, 0);
            const totalFormatted = formatCurrency(total);
            if (cartTotalLabel) {
                cartTotalLabel.textContent = totalFormatted;
            }
            if (mobileCartTotal) {
                mobileCartTotal.textContent = totalFormatted;
            }
            if (mobileCartCount) {
                mobileCartCount.textContent = String(totalQty);
            }
            if (mobileCartSummary) {
                mobileCartSummary.textContent = `${totalQty} món • ${totalFormatted}`;
            }
        };

        const renderDraft = () => {
            draftTables.forEach(({ body, emptyRow }) => {
                body.querySelectorAll('tr').forEach((row) => {
                    if (row !== emptyRow) {
                        row.remove();
                    }
                });
                if (draftItems.length === 0) {
                    emptyRow.style.display = '';
                } else {
                    emptyRow.style.display = 'none';
                }
            });

            draftCount.textContent = String(draftItems.length);

            if (draftItems.length === 0) {
                updateCartSummary();
                return;
            }

            draftItems.forEach((item) => {
                const rowMarkup = `
                    <td class="text-center">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary" data-qty-change="${item.id}" data-delta="-1"><i class="fas fa-minus"></i></button>
                            <span class="btn btn-light px-3">${item.quantity}</span>
                            <button type="button" class="btn btn-outline-secondary" data-qty-change="${item.id}" data-delta="1"><i class="fas fa-plus"></i></button>
                        </div>
                    </td>
                    <td class="text-end fw-semibold">${formatCurrency(item.price * item.quantity)}</td>
                    <td class="text-end">
                        <button class="btn btn-link text-danger btn-sm delete-btn" data-remove-draft="${item.id}">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                `;
                draftTables.forEach(({ body }) => {
                    const row = document.createElement('tr');
                    row.className = 'cart-row';
                    row.setAttribute('data-cart-row', String(item.id));
                    row.innerHTML = `
                    <td>
                        <div class="fw-semibold">${item.name}</div>
                    </td>
                    ${rowMarkup}
                `;
                    body.appendChild(row);
                });
            });

            document.querySelectorAll('[data-qty-change]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const dishId = parseInt(btn.getAttribute('data-qty-change'), 10);
                    const delta = parseInt(btn.getAttribute('data-delta'), 10);
                    changeDraftQuantity(dishId, delta);
                });
            });

            document.querySelectorAll('[data-remove-draft]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const dishId = parseInt(btn.getAttribute('data-remove-draft'), 10);
                    draftItems = draftItems.filter((entry) => entry.id !== dishId);
                    renderDraft();
                });
            });

            updateCartSummary();
            setupSwipeHandlers();
        };

        const addDishToDraft = (dishId) => {
            const dish = menuData.find((item) => item.id === dishId);
            if (!dish) {
                return;
            }
            const existing = draftItems.find((item) => item.id === dishId);
            if (existing) {
                existing.quantity += 1;
            } else {
                draftItems.push({ id: dishId, name: dish.name, price: dish.price, quantity: 1 });
            }
            renderDraft();
        };

        const changeDraftQuantity = (dishId, delta) => {
            const item = draftItems.find((entry) => entry.id === dishId);
            if (!item) {
                return;
            }
            item.quantity += delta;
            if (item.quantity <= 0) {
                draftItems = draftItems.filter((entry) => entry.id !== dishId);
            }
            renderDraft();
        };

        menuSearch.addEventListener('input', (event) => {
            currentSearch = event.target.value.trim();
            renderMenu();
        });

        const handleClearDraft = () => {
            draftItems = [];
            renderDraft();
        };

        const handleSendOrder = () => {
            if (draftItems.length === 0) {
                alert('Vui lòng chọn ít nhất một món.');
                return;
            }
            const payload = draftItems.map((item) => ({
                idmonan: item.id,
                soluong: item.quantity,
            }));
            orderPayloadField.value = JSON.stringify(payload);
            if (sendOrderBtn && sendOrderBtn.closest('form')) {
                sendOrderBtn.closest('form').submit();
            }
            if (mobileCartDrawer && window.bootstrap && window.bootstrap.Offcanvas) {
                const instance = window.bootstrap.Offcanvas.getInstance(mobileCartDrawer);
                if (instance) {
                    instance.hide();
                }
            }
        };

        if (clearDraftBtn) {
            clearDraftBtn.addEventListener('click', handleClearDraft);
        }
        if (sendOrderBtn) {
            sendOrderBtn.addEventListener('click', handleSendOrder);
        }
        document.querySelectorAll('[data-trigger="clearDraft"]').forEach((btn) => {
            btn.addEventListener('click', handleClearDraft);
        });
        document.querySelectorAll('[data-trigger="sendOrder"]').forEach((btn) => {
            btn.addEventListener('click', handleSendOrder);
        });

        const scrollToCart = () => {
            if (orderCard) {
                orderCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        };

        if (scrollToCartBtn) {
            scrollToCartBtn.addEventListener('click', scrollToCart);
        }

        if (mobileCartToggle) {
            mobileCartToggle.addEventListener('click', () => {
                scrollToCart();
                if (mobileCartDrawer && window.bootstrap && window.bootstrap.Offcanvas) {
                    window.bootstrap.Offcanvas.getOrCreateInstance(mobileCartDrawer).show();
                }
            });
        }

        let revealedRow = null;
        const hideRevealedRow = () => {
            if (revealedRow) {
                revealedRow.classList.remove('swipe-revealed');
                revealedRow = null;
            }
        };

        const setupSwipeHandlers = () => {
            document.querySelectorAll('.cart-row').forEach((row) => {
                let startX = 0;
                let currentX = 0;
                let touching = false;

                row.addEventListener('touchstart', (event) => {
                    const touch = event.touches[0];
                    startX = touch.clientX;
                    touching = true;
                    currentX = startX;
                });

                row.addEventListener('touchmove', (event) => {
                    if (!touching) {
                        return;
                    }
                    const touch = event.touches[0];
                    currentX = touch.clientX;
                });

                row.addEventListener('touchend', () => {
                    if (!touching) {
                        return;
                    }
                    const deltaX = currentX - startX;
                    touching = false;
                    if (deltaX < -40) {
                        if (revealedRow && revealedRow !== row) {
                            revealedRow.classList.remove('swipe-revealed');
                        }
                        row.classList.add('swipe-revealed');
                        revealedRow = row;
                    } else if (deltaX > 20 && revealedRow === row) {
                        hideRevealedRow();
                    }
                });

                row.addEventListener('mouseleave', () => {
                    touching = false;
                });
            });

            document.addEventListener('click', (event) => {
                if (!revealedRow) {
                    return;
                }
                if (!revealedRow.contains(event.target)) {
                    hideRevealedRow();
                }
            });
        };

        renderMenu();
        renderDraft();
    })();
</script>

