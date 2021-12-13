<?php
class DbConnect
{
    private $conn;
    function __construct()
    {
    }

    function connect()
    {
        include_once dirname(__FILE__) . '/config.php';
        $host       = DB_HOST;
        $db_name    = DB_NAME;
        $user       = DB_USERNAME;
        $pass       = DB_PASSWORD;
        $this->conn = new PDO("mysql:host=$host;dbname=$db_name", $user, $pass);
        if (mysqli_connect_errno()) {
            echo "Failed to connect to MySQL: " . mysqli_connect_error();
        }
        return $this->conn;
    }
}
?>
