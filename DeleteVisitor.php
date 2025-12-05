<?php
require_once __DIR__ . '/Connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: Dashboard.php');
    exit;
}

$id = $_POST['id'] ?? null;
if ($id === null || $id === '') {
    header('Location: Dashboard.php');
    exit;
}

// Determine the id column name (use first column as fallback)
try {
    $colsStmt = $pdo->query("SHOW COLUMNS FROM `visitors`");
    $cols = $colsStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($cols)) {
        header('Location: Dashboard.php');
        exit;
    }
    $idCol = $cols[0]['Field'];
} catch (PDOException $e) {
    header('Location: Dashboard.php');
    exit;
}

// Make a safe column identifier
$safeCol = '`' . str_replace('`', '``', $idCol) . '`';

// Perform delete using a prepared statement
try {
    $stmt = $pdo->prepare("DELETE FROM `visitors` WHERE " . $safeCol . " = :id LIMIT 1");
    // try to bind as integer when possible
    if (is_numeric($id)) {
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':id', $id, PDO::PARAM_STR);
    }
    $stmt->execute();
} catch (PDOException $e) {
    // For now, silently redirect back on error. Could add logging or flash messages.
}

header('Location: Dashboard.php');
exit;
