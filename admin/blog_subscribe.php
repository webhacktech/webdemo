<?php
require 'config.php';

$msg = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "❌ Please enter a valid email.";
    } else {
        // Check if already subscribed
        $stmt = $conn->prepare("SELECT id FROM blog_subscribers WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $msg = "🔁 You're already subscribed.";
        } else {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO blog_subscribers (email) VALUES (?)");
            $stmt->bind_param("s", $email);
            if ($stmt->execute()) {
                $msg = "✅ Thanks! You've been subscribed.";
                $email = "";
            } else {
                $msg = "❌ Something went wrong. Try again.";
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Subscribe – Sellevo Blog</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="logo.png">
  <style>
    :root {
      --green: #0B7C3E;
      --green-dark: #075E2E;
      --bg: #f4f4f4;
    }
    body {
      font-family: 'Segoe UI', sans-serif;
      background: var(--bg);
      padding: 40px 20px;
      color: #222;
    }
    .box {
      background: #fff;
      padding: 30px;
      max-width: 500px;
      margin: auto;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.05);
      text-align: center;
    }
    h2 {
      color: var(--green);
    }
    input[type="email"] {
      width: 100%;
      padding: 14px;
      border: 1px solid #ccc;
      border-radius: 6px;
      margin: 20px 0;
      font-size: 1rem;
    }
    button {
      background: var(--green);
      color: #fff;
      padding: 12px 20px;
      border: none;
      border-radius: 6px;
      font-size: 1rem;
      cursor: pointer;
    }
    button:hover {
      background: var(--green-dark);
    }
    .msg {
      background: #eaf7ef;
      padding: 12px;
      border-left: 4px solid var(--green);
      margin-top: 15px;
      color: var(--green-dark);
    }
  </style>
</head>
<body>

<div class="box">
  <h2>📬 Join the Sellevo Blog</h2>
  <p>Get new posts, tips and product updates in your inbox.</p>

  <form method="post">
    <input type="email" name="email" placeholder="Enter your email" value="<?=htmlspecialchars($email)?>" required>
    <button type="submit">Subscribe</button>
  </form>

  <?php if ($msg): ?>
    <div class="msg"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
</div>

</body>
</html>