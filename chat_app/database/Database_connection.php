<?php
/**
 * PDO connection helper dedicated to the chat module.
 *
 * We point to the main restaurant database so chat data lives alongside the
 * rest of the system instead of using a standalone schema.
 */
class Database_connection
{
    private $host = 'localhost';
    private $database = 'hceeab2b55_restaurant';
    private $username = 'root';
    private $password = '';

    public function connect(): \PDO
    {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $this->host, $this->database);

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return new \PDO($dsn, $this->username, $this->password, $options);
    }
}

?>
