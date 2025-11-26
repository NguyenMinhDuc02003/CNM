<?php
class Permission {
    private $conn;
    private $permissions = [];
    private $user_id;
    private $role_id;

    public function __construct($conn) {
        $this->conn = $conn;
        if (isset($_SESSION['nhanvien_id'])) {
            $this->user_id = $_SESSION['nhanvien_id'];
            $this->role_id = $_SESSION['vaitro_id'];
            $this->loadPermissions();
        }
    }

    private function loadPermissions() {
        $query = "SELECT v.quyen 
                 FROM nhanvien n 
                 JOIN vaitro v ON n.idvaitro = v.idvaitro 
                 WHERE n.idnv = ?";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $this->user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $roleData = mysqli_fetch_assoc($result);
        
        if ($roleData && !empty($roleData['quyen'])) {
            $this->permissions = array_map('trim', explode(",", $roleData['quyen']));
        }
    }

    // public function hasPermission($permission) {
    //     // Nếu là quản lý (role_id = 4) thì có tất cả quyền
    //     if ($this->role_id == 4) {
    //         return true;
    //     }
    //     return in_array(trim($permission), $this->permissions);
    // }

    public function checkAccess($page, $action = 'view') {
        // Danh sách các trang và quyền tương ứng
        $pagePermissions = [
            'dsnhanvien' => [
                'view' => 'xem nhan vien',
                'add' => 'them nhan vien',
                'edit' => 'sua nhan vien',
                'delete' => 'xoa nhan vien'
            ],
            'dskhachhang' => [
                'view' => 'xem khach hang',
                'add' => 'them khach hang',
                'edit' => 'sua khach hang',
                'delete' => 'xoa khach hang'
            ],
            'dsmonan' => [
                'view' => 'xem mon an',
                'add' => 'them mon an',
                'edit' => 'sua mon an',
                'delete' => 'xoa mon an'
            ],
            'dsdonhang' => [
                'view' => 'xem don hang',
                'add' => 'them don hang',
                'edit' => 'sua don hang',
                'delete' => 'xoa don hang'
            ],
            'dshoadon' => [
                'view' => 'xem hoa don',
                'add' => 'them hoa don',
                'edit' => 'sua hoa don',
                'delete' => 'xoa hoa don'
            ],
            'phanquyen' => [
                'view' => 'xem vai tro',
                'add' => 'them vai tro',
                'edit' => 'sua vai tro',
                'delete' => 'xoa vai tro',
            ]
        ];

        // Nếu là quản lý thì cho phép truy cập tất cả
        // if ($this->role_id == 4) {
        //     return true;
        // }

        // Kiểm tra quyền cho trang và action cụ thể
        if (isset($pagePermissions[$page]) && isset($pagePermissions[$page][$action])) {
            return $this->hasPermission($pagePermissions[$page][$action]);
        }

        // Nếu trang không có trong danh sách kiểm tra, mặc định cho phép truy cập
        return true;
    }

    public function getMenuItems() {
        $menuItems = [
            [
                'title' => 'Khách hàng',
                'icon' => 'fas fa-address-card',
                'url' => 'index.php?page=dskhachhang',
                'permission' => 'xem khach hang'
            ],
            [
                'title' => 'Món ăn',
                'icon' => 'fas fa-utensils',
                'url' => 'index.php?page=dsmonan',
                'permission' => 'xem mon an'
            ],
            [
                'title' => 'Đơn đặt bàn',
                'icon' => 'far fa-address-book',
                'url' => 'index.php?page=dsdatban',
                'permission' => 'xem don dat ban',
            ],
            [
                'title' => 'Đơn hàng',
                'icon' => 'fas fa-pen-square',
                'url' => 'index.php?page=dsdonhang',
                'permission' => 'xem don hang'
            ],
            [
                'title' => 'Hóa đơn',
                'icon' => 'fas fa-align-right',
                'url' => 'index.php?page=dshoadon',
                'permission' => 'xem hoa don'
            ],
            [
                'title' => 'Tồn kho',
                'icon' => 'fas icon-layers',
                'url' => 'index.php?page=dstonkho',
                'permission' => 'xem ton kho'
            ],
            [
                'title' => 'Nhân viên',
                'icon' => 'fas icon-people',
                'url' => 'index.php?page=dsnhanvien',
                'permission' => 'xem nhan vien'
            ],
            [
                'title' => 'Phân quyền',
                'icon' => 'fas icon-wrench',
                'url' => 'index.php?page=phanquyen',
                'permission' => 'Phan quyen'
               
            ]
            
        ];

        $filteredMenu = [];
        foreach ($menuItems as $item) {
            if (isset($item['role_required'])) {
                if ($this->role_id == $item['role_required']) {
                    $filteredMenu[] = $item;
                }
            } else if ($this->hasPermission($item['permission'])) {
                $filteredMenu[] = $item;
            }
        }

        return $filteredMenu;
    }
}
?> 
