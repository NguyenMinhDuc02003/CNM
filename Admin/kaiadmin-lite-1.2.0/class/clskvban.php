<?php
/**
 * Table Area Management Class
 */
class KhuVucBan {
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        require_once 'clsconnect.php';
        $this->db = new connect_db();
    }
    
    /**
     * Get the name of a table area by its ID
     * 
     * @param int $maKV The ID of the table area
     * @return string The name of the table area
     */
    public function getTenKhuVuc($maKV) {
        $sql = "SELECT TenKV FROM khuvucban WHERE MaKV = ?";
        $result = $this->db->xuatdulieu_prepared($sql, [$maKV]);
        return !empty($result) ? $result[0]['TenKV'] : 'Không xác định';
    }

    public function selectKvban($value = '') {
        $str = '';
        $sql = "SELECT * FROM khuvucban";  
        $arr = $this->db->xuatdulieu($sql);
    
        for ($i = 0; $i < count($arr); $i++) {
            $selected = ($arr[$i]["MaKV"] == $value) ? "selected" : "";
            $str .= '<option ' . $selected . ' value="' . $arr[$i]["MaKV"] . '">'
                    . $arr[$i]["TenKV"] . '</option>';
        }
        return $str;
    }
}
