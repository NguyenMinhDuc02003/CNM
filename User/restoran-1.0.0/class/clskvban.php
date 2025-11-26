<?php
require_once 'clsconnect.php';

class KhuVucBan extends connect_db {

    public function selectKvban($value = '') {
        $str = '';
        $sql = "SELECT * FROM khuvucban";  
        $arr = $this->xuatdulieu($sql);
    
        for ($i = 0; $i < count($arr); $i++) {
            $selected = ($arr[$i]["MaKV"] == $value) ? "selected" : "";
            $str .= '<option ' . $selected . ' value="' . $arr[$i]["MaKV"] . '">'
                    . $arr[$i]["TenKV"] . '</option>';
        }
        return $str;
    }

    function getTenKhuVuc($maKhuVuc) {
        $sql = "SELECT TenKV FROM khuvucban WHERE MaKV = $maKhuVuc";
        $ds = $this->xuatdulieu($sql);
        return $ds[0]['TenKV'] ?? 'Không xác định';
    }
}
