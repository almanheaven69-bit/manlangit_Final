<?php
require_once __DIR__ . '/Connect.php';

$errors = [];

// Discover columns
$tableExists = true;
try {
    $colsStmt = $pdo->query("SHOW COLUMNS FROM `visitors`");
    $colsRows = $colsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tableExists = false;
    $colsRows = [];
    $errors[] = 'Visitors table not found: ' . htmlspecialchars($e->getMessage());
}

$colMap = [];
foreach ($colsRows as $r) {
    $field = $r['Field'];
    $normalized = strtolower(preg_replace('/[^a-z0-9]/', '', $field));
    $colMap[$normalized] = $field;
}

function findCol(array $map, array $candidates)
{
    foreach ($candidates as $c) {
        $norm = strtolower(preg_replace('/[^a-z0-9]/', '', $c));
        if (isset($map[$norm])) return $map[$norm];
    }
    return null;
}

// Safely quote an identifier (column/table) using backticks and escape any backticks inside name
function quoteIdent(?string $ident)
{
  if ($ident === null) return null;
  $safe = str_replace('`', '``', $ident);
  return "`" . $safe . "`";
}

// Preferred mapped names
$nameCol = $COLUMN_MAP['visitor_name'] ?? null;
$dateCol = $COLUMN_MAP['visited_at'] ?? null;
$timeCol = $COLUMN_MAP['time'] ?? null;
$addressCol = $COLUMN_MAP['address'] ?? null;
$contactCol = $COLUMN_MAP['contact'] ?? null;
$schoolCol = $COLUMN_MAP['school_or_office'] ?? null;
$purposeCol = $COLUMN_MAP['purpose'] ?? null;

$nameCol = $nameCol ?? findCol($colMap, ['visitor_name', "visitor'sname", 'visitorname', 'name']);
$dateCol = $dateCol ?? findCol($colMap, ['visited_at', 'dateofvisit', 'date']);
$timeCol = $timeCol ?? findCol($colMap, ['time']);
$addressCol = $addressCol ?? findCol($colMap, ['address', 'addr']);
$contactCol = $contactCol ?? findCol($colMap, ['contact', 'contactnum', 'phone']);
$schoolCol = $schoolCol ?? findCol($colMap, ['school_or_office', 'schoolname']);
$purposeCol = $purposeCol ?? findCol($colMap, ['purpose', 'reason']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['visitor_name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $school = trim($_POST['school'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');

    if ($name === '') $errors[] = 'Visitor name is required.';
    if ($purpose === '') $errors[] = 'Purpose is required.';

    if (empty($errors)) {
        $fields = [];
        $values = [];
        $params = [];

        if ($nameCol) { $fields[] = quoteIdent($nameCol); $values[] = ':name'; $params[':name'] = $name; }
        // If a date column exists, insert current date; also always insert current time into Time column when present
        if ($dateCol) { $fields[] = quoteIdent($dateCol); $values[] = 'CURDATE()'; }
        if ($timeCol) { $fields[] = quoteIdent($timeCol); $values[] = 'CURRENT_TIME()'; }
        if ($addressCol) { $fields[] = quoteIdent($addressCol); $values[] = ':address'; $params[':address'] = $address ?: null; }
        if ($contactCol) { $fields[] = quoteIdent($contactCol); $values[] = ':contact'; $params[':contact'] = $contact ?: null; }
        if ($schoolCol) { $fields[] = quoteIdent($schoolCol); $values[] = ':school'; $params[':school'] = $school ?: null; }
        if ($purposeCol) { $fields[] = quoteIdent($purposeCol); $values[] = ':purpose'; $params[':purpose'] = $purpose; }

        if (count($fields) > 0) {
            $sql = 'INSERT INTO `visitors` (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
            try {
              // Log SQL and params for debugging
              try {
                $currentDb = $pdo->query('SELECT DATABASE()')->fetchColumn();
              } catch (Exception $e) { $currentDb = 'unknown'; }
              $logLine = sprintf("[%s] DB: %s | SQL: %s | PARAMS: %s\n", date('c'), $currentDb, $sql, json_encode($params));
              file_put_contents(__DIR__ . '/insert_debug.log', $logLine, FILE_APPEND);

              $stmt = $pdo->prepare($sql);
              $stmt->execute($params);

              $lid = $pdo->lastInsertId();
              file_put_contents(__DIR__ . '/insert_debug.log', sprintf("[%s] Inserted ID: %s\n", date('c'), $lid), FILE_APPEND);

              header('Location: Dashboard.php?added=1');
              exit;
            } catch (PDOException $ex) {
              $errors[] = 'Database insert error: ' . htmlspecialchars($ex->getMessage());
              file_put_contents(__DIR__ . '/insert_debug.log', sprintf("[%s] ERROR: %s\n", date('c'), $ex->getMessage()), FILE_APPEND);
            }
        } else {
            $errors[] = 'No matching writable columns found in visitors table.';
        }
    }
}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>New Visitor</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body>
    <div class="container mt-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>New Visitor</h2>
        <a href="Dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
      <?php endif; ?>

      <?php if (!$tableExists): ?>
        <div class="alert alert-warning">Visitors table not found. Create the `visitors` table or update `Connect.php` DB_NAME.</div>
      <?php endif; ?>

      <form id="newVisitorForm" method="post" action="NewVisitor.php">
        <div class="form-group">
          <label for="visitor_name">Name</label>
          <input type="text" class="form-control" id="visitor_name" name="visitor_name" required>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label for="contact">Contact #</label>
            <input type="text" class="form-control" id="contact" name="contact">
          </div>
          <div class="form-group col-md-6">
            <label for="school">School/Office</label>
            <input type="text" class="form-control" id="school" name="school">
          </div>
        </div>
        <div class="form-group">
          <label for="address">Address</label>
          <input type="text" class="form-control" id="address" name="address">
        </div>
        <div class="form-group">
          <label for="purpose">Purpose</label>
          <select class="form-control" id="purpose" name="purpose" required>
            <option value="">-- Select purpose --</option>
            <option>Exam</option>
            <option>Inquiry</option>
            <option>Visit</option>
            <option>Other</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
      </form>
    </div>

    <script src="js/jquery-3.5.1.slim.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/form-validation.js"></script>
  </body>
</html>
