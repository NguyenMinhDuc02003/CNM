<?php
require_once 'clsconnect.php';

class clsDanhMuc {
    private $db;

    public function __construct() {
        $this->db = new connect_db();
    }

    // Lấy tất cả danh mục
    public function getAllDanhMuc() {
        $sql = "SELECT * FROM danhmuc";
        return $this->db->xuatdulieu($sql);
    }
}
?>