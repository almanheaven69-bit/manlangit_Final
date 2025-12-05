<?php
require_once __DIR__ . '/Connect.php';
session_start();
$errors = [];
$fieldErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '') {
      $fieldErrors['username'] = 'Username is required.';
      $errors[] = 'Please provide your username.';
    }
    if ($password === '') {
      $fieldErrors['password'] = 'Password is required.';
      $errors[] = 'Please provide your password.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('SELECT `Username`, `Password` FROM `accounts` WHERE `Username` = :u LIMIT 1');
            $stmt->execute([':u' => $username]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['Password']) && password_verify($password, $row['Password'])) {
                // Authentication success
                $_SESSION['username'] = $row['Username'];
                // Redirect to dashboard
                header('Location: Dashboard.php');
                exit;
            } else {
              // set a field-level error for better UX while also keeping a generic message
              $fieldErrors['username'] = 'Invalid username or password.';
              $fieldErrors['password'] = 'Invalid username or password.';
              $errors[] = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}
$registered = !empty($_GET['registered']);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Account Login</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.0/css/all.css" crossorigin="anonymous">
    <style>
      body { background:#f0f4fb; }
      .auth-wrapper { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:40px; }
      .auth-card { background:#fff;border-radius:12px;padding:26px 22px;box-shadow:0 6px 20px rgba(28,47,100,0.08); width:380px; }
      .auth-card img { width:120px;height:120px;border-radius:8px; display:block; margin:0 auto 10px; }
      .auth-card h1 { text-align:center;font-size:20px;margin-bottom:6px;font-weight:700; }
      .auth-card p.lead { text-align:center;color:#6c757d;margin-bottom:18px; }
      .input-group-text { background:#f7f9ff;border:1px solid #eef2ff;color:#6c757d }
      .form-control.is-invalid { border-color:#e3342f; }
      .btn-primary { font-weight:600;border-radius:6px; }
    </style>
  </head>
  <body>
    <div class="auth-wrapper">
      <div class="auth-card">
        <div style="text-align:center;margin-bottom:6px;"><img src="Images/CCDI_Logo.jpg" alt="Logo"></div>
        <h1>ACCOUNT LOGIN</h1>
        <p class="lead">Manage Visitors</p>

        <?php if ($registered): ?>
          <div class="alert alert-success">Account created successfully. Please sign in.</div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger"><?php foreach ($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?></div>
        <?php endif; ?>

        <form id="signinForm" action="SigninPage.php" method="post" novalidate>
          <div class="input-group mb-3">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
            <input type="text" class="form-control<?= isset($fieldErrors['username']) ? ' is-invalid' : '' ?>" id="Username" name="username" placeholder="Username" autocomplete="username" value="<?= isset($username) ? htmlspecialchars($username) : '' ?>">
            <?php if (isset($fieldErrors['username'])): ?><div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['username']) ?></div><?php endif; ?>
          </div>

          <div class="input-group mb-3">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-key"></i></span></div>
            <input type="password" class="form-control<?= isset($fieldErrors['password']) ? ' is-invalid' : '' ?>" id="Password" name="password" placeholder="Password" required minlength="7" autocomplete="current-password">
            <?php if (isset($fieldErrors['password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['password']) ?></div><?php endif; ?>
          </div>

          <button class="btn btn-primary btn-block" style="padding:12px;" type="submit">Sign In</button>
          <div style="text-align:center;margin-top:14px;color:#6c757d;font-size:14px;">Don't have an account? <a href="RegisterPage.php">Register here</a></div>
        </form>
        <div style="text-align:center;margin-top:12px;color:#9aa3b2;font-size:12px;">&copy; 2025</div>
      </div>
    </div>

    <script src="js/form-validation.js"></script>
  </body>
</html>