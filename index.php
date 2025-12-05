<?php
// Redirect to SigninPage.php when visiting project root.
// Place this file in the project folder served by Apache (this folder).
$target = 'SigninPage.php';
// Send HTTP redirect header
header('Location: ' . $target);
// Also provide a meta-refresh/html fallback for browsers that ignore headers
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="0;url=SigninPage.php">
    <title>Redirecting...</title>
  </head>
  <body>
    <p>Redirecting to <a href="SigninPage.php">Sign In</a>…</p>
  </body>
</html>
