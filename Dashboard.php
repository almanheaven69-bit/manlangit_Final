<?php
require_once __DIR__ . '/Connect.php';
// Ensure session is available so we can provide a logout link
if (session_status() === PHP_SESSION_NONE) session_start();

$errors = [];
$visitors = [];
$total_today = 0;
$exam_count = 0;
$others_count = 0;

// Detect visitors table and columns
$tableExists = true;
try {
    $colsStmt = $pdo->query("SHOW COLUMNS FROM `visitors`");
    $colsRows = $colsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tableExists = false;
    $errors[] = 'Visitors table not found in database: ' . htmlspecialchars($e->getMessage());
    $colsRows = [];
}

$colMap = [];
foreach ($colsRows as $r) {
    $field = $r['Field'];
    $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $field));
    $colMap[$normalized] = $field;
}

function findCol(array $map, array $candidates)
{
    foreach ($candidates as $c) {
      $norm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $c));
        if (isset($map[$norm])) return $map[$norm];
    }
    return null;
}

function quoteIdent(?string $ident)
{
  if ($ident === null) return null;
  $safe = str_replace('`', '``', $ident);
  return "`" . $safe . "`";
}

$nameCol = $COLUMN_MAP['visitor_name'] ?? null;
$dateCol = $COLUMN_MAP['visited_at'] ?? null;
$timeCol = $COLUMN_MAP['time'] ?? null;
$addressCol = $COLUMN_MAP['address'] ?? null;
$contactCol = $COLUMN_MAP['contact'] ?? null;
$schoolCol = $COLUMN_MAP['school_or_office'] ?? null;
$purposeCol = $COLUMN_MAP['purpose'] ?? null;

$nameCol = $nameCol ?? findCol($colMap, ['visitor_name', "visitor'sname", 'visitorname', 'name']);
$dateCol = $dateCol ?? findCol($colMap, ['visited_at', 'dateofvisit', 'date']);
$timeCol = $timeCol ?? findCol($colMap, ['time', 'visit_time']);
$addressCol = $addressCol ?? findCol($colMap, ['address', 'addr']);
$contactCol = $contactCol ?? findCol($colMap, ['contact', 'contactnum', 'phone']);
$schoolCol = $schoolCol ?? findCol($colMap, [
  'school_or_office',
  'school',
  'schoolname',
  'schooloffice',
  'school_office',
  'schooloroffice',
  'schoolnameoffice',
  'office',
  'office_name',
  'company'
]);
$purposeCol = $purposeCol ?? findCol($colMap, ['purpose', 'reason']);

if ($tableExists) {
  // Fetch visitors using optional filters (from/to dates and name). Defaults to today if no filters provided.
  try {
    $where = [];
    $params = [];

    // incoming filters via GET
    $from = trim($_GET['from'] ?? ($_GET['from_date'] ?? ''));
    $to = trim($_GET['to'] ?? ($_GET['to_date'] ?? ''));
    $nameSearch = trim($_GET['name'] ?? ($_GET['q'] ?? ''));

    if ($dateCol) {
      // build date conditions
      if ($from !== '' && $to !== '') {
        $where[] = 'DATE(' . quoteIdent($dateCol) . ') BETWEEN :from AND :to';
        $params[':from'] = $from;
        $params[':to'] = $to;
      } elseif ($from !== '') {
        $where[] = 'DATE(' . quoteIdent($dateCol) . ') >= :from';
        $params[':from'] = $from;
      } elseif ($to !== '') {
        $where[] = 'DATE(' . quoteIdent($dateCol) . ') <= :to';
        $params[':to'] = $to;
      }
    }

    // name search (case-insensitive wildcard)
    if ($nameSearch !== '') {
      if ($nameCol) {
        $where[] = 'LOWER(' . quoteIdent($nameCol) . ') LIKE :name';
      } else {
        // fallback to common name columns
        $where[] = '(LOWER(`Name`) LIKE :name OR LOWER(`visitor_name`) LIKE :name)';
      }
      $params[':name'] = '%' . strtolower($nameSearch) . '%';
    }

    // If no filters provided, default to today (preserve original behavior)
    if (empty($where) && $dateCol) {
      $where[] = 'DATE(' . quoteIdent($dateCol) . ') = CURDATE()';
    }

    $q = 'SELECT * FROM `visitors`';
    if (!empty($where)) $q .= ' WHERE ' . implode(' AND ', $where);
    // prefer ordering by date/time columns if present
    if ($dateCol && $timeCol) {
      $q .= ' ORDER BY ' . quoteIdent($dateCol) . ' DESC, ' . quoteIdent($timeCol) . ' DESC';
    } elseif ($dateCol) {
      $q .= ' ORDER BY ' . quoteIdent($dateCol) . ' DESC';
    } else {
      $q .= ' ORDER BY 1 DESC';
    }

    $stmt = $pdo->prepare($q);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $visitors = $stmt->fetchAll();
  } catch (PDOException $e) {
    $errors[] = 'Error fetching visitors: ' . htmlspecialchars($e->getMessage());
  }

  // Calculate stats
  $total_today = count($visitors);
  foreach ($visitors as $v) {
    $pval = '';
    if (!empty($purposeCol) && isset($v[$purposeCol])) $pval = (string)$v[$purposeCol];
    elseif (isset($v['Purpose'])) $pval = (string)$v['Purpose'];
    elseif (isset($v['purpose'])) $pval = (string)$v['purpose'];
    $p = strtolower($pval);
    if (strpos($p, 'exam') !== false) $exam_count++;
    else if ($p !== '') $others_count++;
  }
}

function fmtDate($dt)
{
    if (!$dt) return '';
    $t = strtotime($dt);
    if ($t === false) return htmlspecialchars($dt);
    return strtoupper(date('d M Y', $t));
}

function fmtTime($t)
{
  if (!$t) return '';
  $ts = strtotime($t);
  if ($ts === false) return htmlspecialchars($t);
  return date('g:ia', $ts);
}

function getRowField(array $row, array $candidates)
{
  $map = [];
  foreach (array_keys($row) as $k) {
    $norm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$k));
    $map[$norm] = $k;
  }

  foreach ($candidates as $cand) {
    $norm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$cand));
    if (isset($map[$norm])) return $row[$map[$norm]];
  }

  return null;
}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Dashboard</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.0/css/all.css" integrity="sha384-lZN37f5QGtY3VHgisS14W3ExzMWZxybE1SJSEsQp9S+oqd12jhcu+A56Ebc1zFSJ" crossorigin="anonymous">

    <style>
      :root {
        --primary: #6366f1;
        --primary-dark: #4f46e5;
        --secondary: #8b5cf6;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --light-bg: #f8fafc;
        --card-bg: #ffffff;
        --text-dark: #1e293b;
        --text-light: #64748b;
        --border: #e2e8f0;
      }

      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      body {
        font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        background: linear-gradient(135deg, var(--light-bg) 0%, #f0f4f8 100%);
        color: var(--text-dark);
        padding-top: 20px;
        padding-bottom: 40px;
      }

      .navbar-top {
        background: white;
        padding: 20px 0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        margin-bottom: 30px;
      }

      .navbar-top .container {
        display: flex;
        justify-content: space-between;
        align-items: center;
      }

      .navbar-brand {
        font-size: 24px;
        font-weight: 700;
        color: var(--primary);
        text-decoration: none;
      }

      .navbar-actions {
        display: flex;
        gap: 12px;
      }

      .btn-modern {
        padding: 10px 20px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
      }

      .btn-primary-modern {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
      }

      .btn-primary-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
        color: white;
        text-decoration: none;
      }

      .btn-secondary-modern {
        background: var(--light-bg);
        color: var(--text-dark);
        border: 1px solid var(--border);
      }

      .btn-secondary-modern:hover {
        background: white;
        border-color: var(--primary);
        color: var(--primary);
      }

      .btn-danger-modern {
        background: #fee2e2;
        color: var(--danger);
        border: 1px solid #fecaca;
      }

      .btn-danger-modern:hover {
        background: var(--danger);
        color: white;
      }

      .container {
        max-width: 1200px;
      }

      .dashboard-header {
        margin-bottom: 40px;
      }

      .dashboard-header h1 {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 8px;
      }

      .dashboard-header p {
        color: var(--text-light);
        font-size: 14px;
      }

      .stat-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
      }

      .stat-card {
        background: white;
        padding: 24px;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        transition: all 0.3s ease;
        border-top: 4px solid var(--primary);
      }

      .stat-card:nth-child(2) {
        border-top-color: var(--success);
      }

      .stat-card:nth-child(3) {
        border-top-color: var(--warning);
      }

      .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
      }

      .stat-card-label {
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-light);
        margin-bottom: 8px;
      }

      .stat-card-value {
        font-size: 36px;
        font-weight: 700;
        color: var(--text-dark);
      }

      .filter-section {
        background: white;
        padding: 24px;
        border-radius: 16px;
        margin-bottom: 30px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
      }

      .filter-section h5 {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 16px;
        color: var(--text-dark);
      }

      .filter-controls {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
      }

      .filter-controls .form-group {
        margin: 0;
      }

      .filter-controls label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 6px;
        color: var(--text-light);
      }

      .filter-controls input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s ease;
      }

      .filter-controls input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
      }

      .filter-buttons {
        display: flex;
        gap: 12px;
      }

      .visitors-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        overflow: hidden;
      }

      .visitors-card-header {
        padding: 24px;
        border-bottom: 1px solid var(--border);
      }

      .visitors-card h5 {
        font-size: 18px;
        font-weight: 700;
        margin: 0;
        color: var(--text-dark);
      }

      .table-responsive {
        overflow-x: auto;
      }

      .table {
        margin: 0;
        border-collapse: collapse;
      }

      .table thead th {
        background: var(--light-bg);
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-light);
        padding: 16px;
        border: none;
        text-align: left;
      }

      .table tbody td {
        padding: 16px;
        border-bottom: 1px solid var(--border);
        font-size: 14px;
        color: var(--text-dark);
      }

      .table tbody tr:hover {
        background: var(--light-bg);
      }

      .table tbody tr:last-child td {
        border-bottom: none;
      }

      .alert {
        border: none;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 30px;
        font-size: 14px;
      }

      .alert-danger {
        background: #fee2e2;
        color: var(--danger);
        border-left: 4px solid var(--danger);
      }

      .alert-warning {
        background: #fef3c7;
        color: #92400e;
        border-left: 4px solid var(--warning);
      }

      .no-data {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-light);
      }

      .no-data i {
        font-size: 48px;
        color: var(--border);
        margin-bottom: 16px;
        display: block;
      }

      @media (max-width: 768px) {
        .navbar-top .container {
          flex-direction: column;
          gap: 12px;
        }

        .filter-controls {
          grid-template-columns: 1fr;
        }

        .dashboard-header h1 {
          font-size: 24px;
        }

        .stat-cards {
          grid-template-columns: 1fr;
        }
      }
    </style>
  </head>
  <body>
    <div class="navbar-top">
      <div class="container">
        <a class="navbar-brand" href="Dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
        <div class="navbar-actions">
          <a href="NewVisitor.php" class="btn-modern btn-primary-modern"><i class="fas fa-plus"></i> New Visitor</a>
          <a href="logout.php" class="btn-modern btn-secondary-modern"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
      </div>
    </div>

    <div class="container">
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <i class="fas fa-exclamation-circle"></i>
          <?php foreach ($errors as $err) echo '<div>' . htmlspecialchars($err) . '</div>'; ?>
        </div>
      <?php endif; ?>

      <?php if (!$tableExists): ?>
        <div class="alert alert-warning">
          <i class="fas fa-exclamation-triangle"></i>
          Visitors table is not available. Create a `visitors` table or update `Connect.php` DB constants.
        </div>
      <?php endif; ?>

      <div class="dashboard-header">
        <h1>Welcome Back</h1>
        <p>Manage and track your visitors efficiently</p>
      </div>

      <div class="stat-cards">
        <div class="stat-card">
          <div class="stat-card-label"><i class="fas fa-users"></i> Today's Visitors</div>
          <div class="stat-card-value"><?= $total_today ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-card-label"><i class="fas fa-clipboard"></i> Exams</div>
          <div class="stat-card-value"><?= $exam_count ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-card-label"><i class="fas fa-folder"></i> Others</div>
          <div class="stat-card-value"><?= $others_count ?></div>
        </div>
      </div>

      <div class="filter-section">
        <h5><i class="fas fa-filter"></i> Filter Visitors</h5>
        <form method="get" action="Dashboard.php">
          <div class="filter-controls">
            <div class="form-group">
              <label for="from">From Date</label>
              <input type="date" id="from" name="from" value="<?= htmlspecialchars($_GET['from'] ?? $_GET['from_date'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="to">To Date</label>
              <input type="date" id="to" name="to" value="<?= htmlspecialchars($_GET['to'] ?? $_GET['to_date'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="name">Search by Name</label>
              <input type="search" id="name" name="name" placeholder="Enter visitor name" value="<?= isset($_GET['name']) ? htmlspecialchars($_GET['name']) : (isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '') ?>">
            </div>
          </div>
          <div class="filter-buttons">
            <button type="submit" class="btn-modern btn-primary-modern"><i class="fas fa-search"></i> Filter</button>
            <a href="Dashboard.php" class="btn-modern btn-secondary-modern"><i class="fas fa-redo"></i> Reset</a>
          </div>
        </form>
      </div>

      <div class="visitors-card">
        <div class="visitors-card-header">
          <h5><i class="fas fa-table"></i> Visitors List</h5>
        </div>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Time of Visit</th>
                <th>Name</th>
                <th>Contact #</th>
                <th>Address</th>
                <th>School/Office</th>
                <th>Purpose</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($visitors)): ?>
                <tr>
                  <td colspan="8">
                    <div class="no-data">
                      <i class="fas fa-inbox"></i>
                      <p>No visitors found</p>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($visitors as $v): ?>
                  <tr>
                    <td><?php
                      $dt = '';
                      if (!empty($dateCol) && isset($v[$dateCol])) $dt = $v[$dateCol];
                      echo fmtDate($dt);
                    ?></td>
                    <td><?php
                      $tv = '';
                      if (!empty($timeCol) && isset($v[$timeCol])) $tv = $v[$timeCol];
                      elseif (isset($v['Time'])) $tv = $v['Time'];
                      elseif (isset($v['time'])) $tv = $v['time'];
                      echo htmlspecialchars(fmtTime($tv));
                    ?></td>
                    <td><?= htmlspecialchars($nameCol && isset($v[$nameCol]) ? $v[$nameCol] : ($v['Name'] ?? ($v['visitor_name'] ?? ''))) ?></td>
                    <td><?= htmlspecialchars($contactCol && isset($v[$contactCol]) ? $v[$contactCol] : ($v['Contact'] ?? ($v['contact'] ?? ''))) ?></td>
                    <td><?= htmlspecialchars($addressCol && isset($v[$addressCol]) ? $v[$addressCol] : ($v['Address'] ?? ($v['address'] ?? ''))) ?></td>
                    <td><?php
                      $schoolVal = getRowField($v, [
                        'school_or_office','school','schoolname','schooloffice','school_office',
                        'schooloroffice','schoolnameoffice','SchoolOrOffice','School','SchoolName',
                        'SchoolName/Office','office','Office','company','Company','office_name'
                      ]);
                      if ($schoolVal === null) $schoolVal = '';
                      echo htmlspecialchars($schoolVal);
                    ?></td>
                    <td><?= htmlspecialchars($purposeCol && isset($v[$purposeCol]) ? $v[$purposeCol] : ($v['Purpose'] ?? ($v['purpose'] ?? ''))) ?></td>
                    <td>
                      <?php
                        $idVal = getRowField($v, ['id','ID','Id']);
                        $idAttr = htmlspecialchars((string)($idVal ?? ''));
                      ?>
                      <form method="post" action="DeleteVisitor.php" onsubmit="return confirm('Delete this visitor?');" style="display:inline">
                        <input type="hidden" name="id" value="<?= $idAttr ?>">
                        <button type="submit" class="btn-modern btn-danger-modern"><i class="fas fa-trash"></i> Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <script src="js/jquery-3.5.1.slim.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
  </body>
</html>
