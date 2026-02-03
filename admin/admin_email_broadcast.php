<?php
session_start();
require '../config.php';
require '../phpmailer/src/PHPMailer.php';
require '../phpmailer/src/SMTP.php';
require '../phpmailer/src/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;

if (!isset($_SESSION['admin_id'])) {
  header("Location: admin_login.php"); exit();
}

$msg = ""; $count = 0;
$whereClauses = ["1=1"];
$params = []; $types = "";

// Filters
if ($_POST) {
    if ($_POST['target'] === 'user' && !empty($_POST['email_search'])) {
        $whereClauses[] = "u.email = ?";
        $params[] = trim($_POST['email_search']);
        $types .= "s";
    } elseif ($_POST['target'] === 'vendors') {
        $whereClauses[] = "v.id IS NOT NULL";
    }
    // Optionally filter by verified/lite/premium here
}

$sql = "SELECT u.id, u.email, v.id AS vendor_id 
        FROM users u
        LEFT JOIN vendors v ON v.user_id = u.id
        WHERE " . implode(" AND ", $whereClauses);
$stmt = $conn->prepare($sql);
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute(); $res = $stmt->get_result();

// Send email
if ($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['subject']) && !empty($_POST['message'])) {
    while ($row = $res->fetch_assoc()) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.sellevo.store';
            $mail->SMTPAuth = true;
            $mail->Username = 'support@sellevo.store';
            $mail->Password = 'Mfni123yaus123';
            $mail->SMTPSecure = 'ssl'; $mail->Port = 465;

            $mail->setFrom('support@sellevo.store','Sellevo Store');
            $mail->addAddress($row['email']);
            $mail->isHTML(true);
            $mail->Subject = $_POST['subject'];

            $uid = $row['id'];
            $token = hash_hmac('sha256',$uid,'unsubscribe_secret');

            $body = "
              <div style='font-family:Arial,sans-serif; max-width:600px;margin:auto;padding:20px;border:1px solid #eee;'>
                <img src='https://sellevo.store/logo.png' style='max-width:150px;'><br><br>
                <h2 style='color:#0B7C3E;'>".htmlspecialchars($_POST['subject'])."</h2>
                <p>".nl2br(htmlspecialchars($_POST['message']))."</p>
                <hr>
                <p style='font-size:0.9rem;color:#777;'>
                  <strong>Sellevo Store</strong><br>
                  support@sellevo.store<br>
                  Lagos, Nigeria
                </p>
                <p style='font-size:0.8rem;color:#777;'>
                  If you no longer wish to receive emails, <a href='https://sellevo.store/unsubscribe.php?uid=$uid&token=$token'>unsubscribe here</a>.
                </p>
                <img src='https://sellevo.store/unsub_open.php?uid=$uid&token=$token' width='1' height='1' style='display:none;'>
              </div>";

            $mail->Body = $body;
            $mail->send();
            $count++;

            $conn->query("INSERT INTO email_logs(user_id, subject, sent_at) VALUES ($uid,'".$conn->real_escape_string($_POST['subject'])."',NOW())");
        } catch (Exception $e){}
    }
    $msg = "✅ Sent to $count users.";
    $res->data_seek(0);
}

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Email Broadcast – Sellevo Admin</title>
</head>
<body style="font-family:Arial,sans-serif;padding:20px;">
  <h2>Sellevo Email Broadcast</h2>
  <?php if($msg):?>
    <p style="background:#eaf7ef;padding:10px;border-left:4px solid #0B7C3E;"><?=$msg?></p>
  <?php endif;?>

  <form method="post">
    <p><strong>Send To:</strong><br>
      <input type="radio" name="target" value="all" checked> All Users
      <input type="radio" name="target" value="vendors"> Vendors Only
      <input type="radio" name="target" value="user"> Specific User:
      <input type="email" name="email_search" placeholder="user@example.com"></p>

    <p><input type="text" name="subject" placeholder="Email Subject" style="width:100%;" required></p>
    <p><textarea name="message" rows="6" placeholder="Your message…" style="width:100%;" required></textarea></p>
    <p><button style="background:#0B7C3E;color:#fff;padding:10px 20px;border:none;border-radius:5px;">Send Email</button></p>
  </form>

  <hr>
  <h3>Preview:</h3>
  <iframe style="width:100%;height:400px;" srcdoc="<?=
    htmlspecialchars('<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:20px;border:1px solid #eee;">
      <img src="https://sellevo.store/logo.png" style="max-width:150px;"><br><br>
      <h2 style="color:#0B7C3E;">Subject Preview</h2>
      <p>Message body preview here...</p>
      <hr>
      <p style="font-size:0.9rem;color:#777;">
        <strong>Sellevo Store</strong><br>support@sellevo.store<br>Lagos, Nigeria
      </p>
      <p style="font-size:0.8rem;color:#777;">
        If you no longer wish to receive emails, <em>unsubscribe link</em>.
      </p>
    </div>'); ?>"></iframe>
</body>
</html>