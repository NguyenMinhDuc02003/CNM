<?php
require_once 'clsconnect.php';

class clsBan extends connect_db {
    public function getBanById($id) {
        $sql = "SELECT * FROM ban WHERE idban = ?";
        $result = $this->xuatdulieu_prepared($sql, [(int)$id]);
        return $result ? $result[0] : null;
    }
}
?>