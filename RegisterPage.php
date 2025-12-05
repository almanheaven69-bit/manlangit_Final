<?php
require_once __DIR__ . '/Connect.php';
session_start();
$errors = [];
$fieldErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? ($_POST['confirm'] ?? '');

  // Debug: log that a POST was received (do not log passwords)
  $logLine = date('Y-m-d H:i:s') . " - Register POST received; username=" . str_replace(["\n","\r"], '', $username) . "\n";
  @file_put_contents(__DIR__ . '/register_debug.log', $logLine, FILE_APPEND | LOCK_EX);

    if ($username === '') {
      $fieldErrors['username'] = 'Username is required.';
      $errors[] = 'Please provide a username.';
    }

    if ($password === '') {
      $fieldErrors['password'] = 'Password is required.';
      $errors[] = 'Please provide a password.';
    } elseif (strlen($password) < 7) {
      $fieldErrors['password'] = 'Password must be at least 7 characters.';
      $errors[] = 'Password must be at least 7 characters.';
    }

    if ($password !== $password2) {
      $fieldErrors['confirm'] = 'Passwords do not match.';
      $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM `accounts` WHERE `Username` = :u');
            $stmt->execute([':u' => $username]);
            $count = (int)$stmt->fetchColumn();
            if ($count > 0) {
                $errors[] = 'Username is already taken.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $pdo->prepare('INSERT INTO `accounts` (`Username`, `Password`) VALUES (:u, :p)');
                $ins->execute([':u' => $username, ':p' => $hash]);
                header('Location: SigninPage.html?registered=1');
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Create Account</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.0/css/all.css" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
      :root{--bg-1:#6a11cb;--bg-2:#2575fc;--card-bg:rgba(255,255,255,0.96);--accent:#ff6b6b;--muted:#6c757d}
      html,body{height:100%}
      body{font-family:'Poppins',system-ui,Segoe UI,Roboto,Arial;margin:0;min-height:100%;background:linear-gradient(135deg,var(--bg-1),var(--bg-2));display:flex;align-items:center;justify-content:center;padding:40px}
      .auth-card{width:100%;max-width:760px;border-radius:14px;background:var(--card-bg);box-shadow:0 12px 40px rgba(16,24,40,0.25);display:flex;overflow:hidden}
      .auth-card .left{flex:1;padding:36px}
      .auth-card .right{width:300px;background:linear-gradient(180deg,rgba(255,255,255,0.03),rgba(255,255,255,0.02));padding:26px;display:flex;flex-direction:column;justify-content:center}
      .brand{display:flex;align-items:center;gap:12px;margin-bottom:12px}
      .brand img{width:80px;height:80px;border-radius:12px;object-fit:cover}
      h1{font-size:20px;margin:0 0 6px 0}
      .muted{color:var(--muted);font-size:14px}
      .form-control{border-radius:10px;padding:12px 14px;border:1px solid #e9eefb}
      .input-group-text{background:transparent;border:none;padding:0 12px;color:var(--muted)}
      .btn-primary{background:linear-gradient(90deg,var(--accent),#ff9a9e);border:none;border-radius:10px;padding:12px 16px;font-weight:600}
      @media(max-width:800px){.auth-card{flex-direction:column}.auth-card .right{width:100%}}
    </style>
  </head>
  <body>
    <div style="padding:20px;width:100%;display:flex;justify-content:center;">
      <div class="auth-card">
        <div class="left">
          <div class="brand">
            <img src="Images/cropped-ccdilogo.png" alt="Logo">
            <div>
              <h1>CREATE ACCOUNT</h1>
              <div class="muted">Join CCDI and stay connected</div>
            </div>
          </div>

          <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><ul><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
          <?php endif; ?>

          <form id="registerForm" action="RegisterPage.php" method="post" novalidate>
            <div class="form-group">
              <label class="sr-only" for="RegUsername">Username</label>
              <div class="input-group mb-3">
                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
                <input type="text" class="form-control<?= isset($fieldErrors['username']) ? ' is-invalid' : '' ?>" id="RegUsername" name="username" placeholder="Username" autocomplete="username" value="<?= isset($username) ? htmlspecialchars($username) : '' ?>">
                <?php if (isset($fieldErrors['username'])): ?><div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['username']) ?></div><?php endif; ?>
              </div>
            </div>

            <div class="form-group">
              <label class="sr-only" for="RegPassword">Password</label>
              <div class="input-group mb-3">
                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-key"></i></span></div>
                <input type="password" class="form-control<?= isset($fieldErrors['password']) ? ' is-invalid' : '' ?>" id="RegPassword" name="password" placeholder="Password" minlength="7" autocomplete="new-password">
                <?php if (isset($fieldErrors['password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['password']) ?></div><?php endif; ?>
              </div>
            </div>

            <div class="form-group">
              <label class="sr-only" for="ConfirmPassword">Confirm Password</label>
              <div class="input-group mb-3">
                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-lock"></i></span></div>
                <input type="password" class="form-control<?= isset($fieldErrors['confirm']) ? ' is-invalid' : '' ?>" id="ConfirmPassword" name="confirm" placeholder="Confirm Password" autocomplete="new-password">
                <?php if (isset($fieldErrors['confirm'])): ?><div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['confirm']) ?></div><?php endif; ?>
              </div>
            </div>

            <button class="btn btn-primary btn-block" type="submit">Create Account</button>
            <div style="text-align:center;margin-top:12px;" class="muted">Already have an account? <a href="SigninPage.html">Log In</a></div>
          </form>
        </div>

        <div class="right">
          <div style="text-align:center;color:#fff;margin-bottom:10px">
            <h2 style="color:#fff;margin-bottom:6px">Welcome to CCDI</h2>
            <p style="color:rgba(255,255,255,0.9)">Register to receive announcements, event invites, and community updates.</p>
            <div style="margin-top:18px;color:rgba(255,255,255,0.9);font-size:13px">Safe • Fast • Community</div>
            <div style="margin-top:22px;color:rgba(255,255,255,0.7)">© 2025 CCDI</div>
          </div>
        </div>
      </div>
    </div>
    <script src="js/form-validation.js"></script>
    <style>
      .auth-card { background:#fff;border-radius:12px;padding:26px 22px;box-shadow:0 6px 20px rgba(28,47,100,0.08); }
      .input-group-text { background:#f7f9ff;border:1px solid #eef2ff;color:#6c757d }
      .form-control.is-invalid { border-color:#e3342f; }
    </style>
  </body>
</html>