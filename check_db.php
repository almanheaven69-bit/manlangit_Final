<?php
require_once __DIR__ . '/Connect.php';

try {
    // Check if `accounts` table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'accounts'");
    $exists = (bool) $stmt->fetchColumn();

    if ($exists) {
        echo "OK: 'accounts' table exists.\n";

        // Show columns
        $cols = $pdo->query("SHOW COLUMNS FROM `accounts`")->fetchAll(PDO::FETCH_ASSOC);
        echo "Columns:\n";
        foreach ($cols as $c) {
            echo " - {$c['Field']} ({$c['Type']})" . PHP_EOL;
        }
    } else {
        echo "NOT FOUND: 'accounts' table not present. Creating table...\n";
        $sql = "CREATE TABLE `accounts` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `Username` VARCHAR(191) NOT NULL UNIQUE,
            `Password` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $pdo->exec($sql);
        echo "Created 'accounts' table successfully.\n";
    }
} catch (PDOException $e) {
    echo "DB ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Done.\n";
