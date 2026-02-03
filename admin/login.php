<?php
session_start();
require_once '../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if ($username && $password) {
        $stmt = $conn->prepare("SELECT admin_id, username, password, role FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($admin_id, $db_username, $hashed_password, $role);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {
                $_SESSION['is_admin'] = true;
                $_SESSION['admin_id'] = $admin_id;
                $_SESSION['admin_username'] = $db_username;
                $_SESSION['admin_role'] = $role;

                header("Location: admin_dashboard.php");
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }
        $stmt->close();
    } else {
        $error = "All fields are required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Login - Sellevo</title>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700&display=swap" rel="stylesheet" />
  <style>
    body {
      font-family: 'Manrope', sans-serif;
      background: #f0f4f7;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      margin: 0;
    }
    .login-container {
      background: #fff;
      padding: 40px 30px;
      border-radius: 10px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
      max-width: 400px;
      width: 90%;
    }
    .login-container h2 {
      margin-bottom: 20px;
      text-align: center;
      color: #106b28;
    }
    .login-container input {
      width: 100%;
      padding: 14px;
      margin-bottom: 15px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 1rem;
      box-sizing: border-box;
    }
    .login-container button {
      width: 100%;
      padding: 14px;
      background: #106b28;
      color: white;
      font-size: 1rem;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: bold;
    }
    .login-container button:hover {
      background: #0b501e;
    }
    .error {
      background: #ffefef;
      color: #c0392b;
      padding: 10px;
      border-radius: 6px;
      margin-bottom: 15px;
      text-align: center;
      font-size: 0.95rem;
    }
    @media(max-width: 480px) {
      .login-container {
        padding: 30px 20px;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <h2>Admin Login</h2>
    <?php if (!empty($error)): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="text" name="username" placeholder="Username" required />
      <input type="password" name="password" placeholder="Password" required />
      <button type="submit">Login</button>
    </form>
  </div>
</body>
</html>