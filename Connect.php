<?php
// Central connection file (PDO) and optional column mapping
define('DB_HOST', 'localhost');
define('DB_NAME', 'visitors_inquiry');
define('DB_USER', 'root');
define('DB_PASS', '');

// Backwards-compatible constants for any legacy mysqli helper
const HOST = DB_HOST;
const USER = DB_USER;
const PWD = DB_PASS;
const DBNAME = DB_NAME;

// Column map for non-standard column names in `visitors` table
$COLUMN_MAP = [
    // Mapped to the actual column names present in your `visitors` table
    'visitor_name'     => 'Name',
    'visited_at'       => 'DateOfVisit',
    'time'             => 'Time',
    'address'          => 'Address',
    'contact'          => 'Contact',
    'school_or_office' => 'SchoolOrOffice',
    'purpose'          => 'Purpose',
];

date_default_timezone_set('Asia/Manila');

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo 'Database connection failed: ' . htmlspecialchars($e->getMessage());
    exit;
}

// Optional legacy mysqli helper
if (!function_exists('ConnectDB')) {
    function ConnectDB() {
        $conn = new mysqli(HOST, USER, PWD, DBNAME);
        if($conn->connect_error){
            die('Error Connection: ' . $conn->connect_error);
        }
        return $conn;
    }
}