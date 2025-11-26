<?php
require_once 'clsconnect.php';

class clsMonAn {
    private $db;

    public function __construct() {
        $this->db = new connect_db();
    }

    // Lấy tất cả món ăn đang hoạt động (chỉ approved + active)
    public function getAllMonAn() {
        $sql = "SELECT * FROM monan WHERE TrangThai = 'approved' AND hoatdong = 'active'";
        return $this->db->xuatdulieu($sql);
    }

    // Lấy món ăn theo danh mục
    public function getMonAnByDanhMuc($iddm) {
        $sql = "SELECT * FROM monan WHERE iddm = ? AND TrangThai = 'approved' AND hoatdong = 'active'";
        return $this->db->xuatdulieu_prepared($sql, [$iddm]);
    }

    // Tìm kiếm món ăn theo tên
    public function searchMonAn($keyword) {
        $sql = "SELECT * FROM monan WHERE tenmonan LIKE ? AND TrangThai = 'approved' AND hoatdong = 'active'";
        $keyword = "%$keyword%";
        return $this->db->xuatdulieu_prepared($sql, [$keyword]);
    }

    // Tìm kiếm món ăn theo tên và danh mục
    public function searchMonAnByDanhMuc($keyword, $iddm) {
        $sql = "SELECT * FROM monan WHERE tenmonan LIKE ? AND iddm = ? AND TrangThai = 'approved' AND hoatdong = 'active'";
        $keyword = "%$keyword%";
        return $this->db->xuatdulieu_prepared($sql, [$keyword, $iddm]);
    }
}
?>