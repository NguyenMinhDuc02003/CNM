<?php
/**
 * Table Management Class
 */
class clsBan {
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        require_once 'clsconnect.php';
        $this->db = new connect_db();
    }
    
    /**
     * Get a table by its ID
     * 
     * @param int $idban The ID of the table
     * @return array The table details
     */
    public function getBanById($idban) {
        $sql = "SELECT * FROM ban WHERE idban = ?";
        $result = $this->db->xuatdulieu_prepared($sql, [$idban]);
        return !empty($result) ? $result[0] : null;
    }
}
?>