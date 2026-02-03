<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require '../config.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';
require '../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

// Create necessary folders and tables
@mkdir('../uploads/email_attachments', 0755, true);
$conn->query("CREATE TABLE IF NOT EXISTS email_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255),
  subject VARCHAR(255),
  content TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS scheduled_emails (
  id INT AUTO_INCREMENT PRIMARY KEY,
  target VARCHAR(50),
  email TEXT,
  subject VARCHAR(255),
  message TEXT,
  attachment_path TEXT,
  scheduled_at DATETIME,
  sent TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$msg = '';
$errors = [];

$edit_template = null;
if (isset($_GET['edit'])) {
    $tid = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM email_templates WHERE id = $tid");
    if ($res->num_rows > 0) $edit_template = $res->fetch_assoc();
}
if (isset($_GET['delete'])) {
    $tid = (int)$_GET['delete'];
    $conn->query("DELETE FROM email_templates WHERE id = $tid");
    $msg = "🗑️ Template deleted.";
}
if (isset($_GET['use'])) {
    $tid = (int)$_GET['use'];
    $res = $conn->query("SELECT * FROM email_templates WHERE id = $tid");
    if ($res->num_rows > 0) {
        $used_template = $res->fetch_assoc();
    }
}
if (isset($_GET['action']) && $_GET['action'] === 'send_due') {
    $now = date('Y-m-d H:i:s');
    $due = $conn->query("SELECT * FROM scheduled_emails WHERE scheduled_at <= '$now' AND sent = 0");
    $count = 0;

    while ($row = $due->fetch_assoc()) {
        $recipients = $row['target'] === 'vendor' && $row['email']
            ? explode(',', $row['email'])
            : [];

        if ($recipients) {
            $emails = array_map('trim', $recipients);
            $in = "'" . implode("','", array_map([$conn, 'real_escape_string'], $emails)) . "'";
            $query = $conn->query("SELECT email FROM vendors WHERE email IN ($in)");
        } else {
            $query = $conn->query("SELECT email FROM vendors");
        }

        while ($v = $query->fetch_assoc()) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'mail.sellevo.store';
                $mail->SMTPAuth = true;
                $mail->Username = 'support@sellevo.store';
                $mail->Password = 'Mfni123yaus123';
                $mail->SMTPSecure = 'ssl';
                $mail->Port = 465;
                
                // ✅ Add these lines here
                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'base64'; 
                $mail->isHTML(true);
                
                $mail->setFrom('support@sellevo.store', 'Sellevo Store');
                $mail->addAddress($v['email']);
                $mail->isHTML(true);
                $mail->Subject = $row['subject'];
                $footer = "<hr><small>If you no longer wish to receive emails, reply with 'unsubscribe'.</small>";
                $mail->Body = $row['message'] . $footer;

                if ($row['attachment_path'] && file_exists("../" . $row['attachment_path'])) {
                    $mail->addAttachment("../" . $row['attachment_path']);
                }

                $mail->send();
                $count++;
            } catch (Exception $e) {}
        }

        $conn->query("UPDATE scheduled_emails SET sent=1 WHERE id=" . $row['id']);
    }

    $msg = "✅ {$count} scheduled email(s) sent.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target = $_POST['target'] ?? 'all';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    $specific_email = $_POST['specific_email'] ?? '';
    $schedule_date = $_POST['schedule_date'] ?? '';
    $attachment_path = '';

    // Handle attachment upload
    if (!empty($_FILES['attachment']['name'])) {
        $filename = basename($_FILES['attachment']['name']);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), ['pdf', 'png', 'jpg', 'jpeg'])) {
            $target_path = '../uploads/email_attachments/' . time() . '_' . $filename;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_path)) {
                $attachment_path = substr($target_path, 3); // remove "../"
            }
        } else {
            $errors[] = "Only PDF, JPG, PNG allowed.";
        }
    }

    if (isset($_POST['send_now'])) {
        $emails = ($target === 'vendor' && $specific_email) ? explode(',', $specific_email) : [];
        $recipients = [];

        if ($emails) {
            $in = "'" . implode("','", array_map([$conn, 'real_escape_string'], array_map('trim', $emails))) . "'";
            $res = $conn->query("SELECT email FROM vendors WHERE email IN ($in)");
        } else {
            $res = $conn->query("SELECT email FROM vendors");
        }

        while ($v = $res->fetch_assoc()) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'mail.sellevo.store';
                $mail->SMTPAuth = true;
                $mail->Username = 'support@sellevo.store';
                $mail->Password = 'Mfni123yaus123';
                $mail->SMTPSecure = 'ssl';
                $mail->Port = 465;

                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'base64';   // <<<<< add this line
                $mail->isHTML(true);
                
                $mail->setFrom('support@sellevo.store', 'Sellevo Store');
                $mail->addAddress($v['email']);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $footer = "<hr><small>If you no longer wish to receive emails, reply with 'unsubscribe'.</small>";
                $mail->Body = $message . $footer;

                if ($attachment_path && file_exists("../" . $attachment_path)) {
                    $mail->addAttachment("../" . $attachment_path);
                }

                $mail->send();
            } catch (Exception $e) {
                $errors[] = "Failed to send to {$v['email']}: " . $mail->ErrorInfo;
            }
        }

        $msg = "✅ Emails sent.";
    }

    if (isset($_POST['schedule_now']) && $schedule_date) {
        $conn->query("INSERT INTO scheduled_emails(target,email,subject,message,attachment_path,scheduled_at)
                      VALUES('$target','" . $conn->real_escape_string($specific_email) . "','$subject',
                      '" . $conn->real_escape_string($message) . "','$attachment_path','$schedule_date')");
        $msg = "📅 Email scheduled.";
    }

    if (isset($_POST['save_template'])) {
        $tname = $_POST['template_name'] ?? '';
        $tsub = $_POST['template_subject'] ?? '';
        $tmsg = $_POST['template_message'] ?? '';
        if ($tname && $tsub && $tmsg) {
            $conn->query("INSERT INTO email_templates(name,subject,content)
                          VALUES('" . $conn->real_escape_string($tname) . "','" . $conn->real_escape_string($tsub) . "',
                          '" . $conn->real_escape_string($tmsg) . "')");
            $msg = "✅ Template saved.";
        }
    }
}
$templates = $conn->query("SELECT * FROM email_templates ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin Email Manager</title>
  <script src="https://cdn.tiny.cloud/1/l42ny67yotl3tp19fzwxqnmb4h4vrjg9oomwrfi1luymptee/tinymce/6/tinymce.min.js"></script>
  <script>tinymce.init({selector:'#editor', height: 250});</script>
  <style>
    body { font-family: Arial; padding: 30px; background: #f5f5f5; }
    .box { max-width: 900px; margin: auto; background: white; padding: 30px; border-radius: 10px; }
    input, textarea, select { width: 100%; padding: 10px; margin-bottom: 15px; }
    button { padding: 10px 18px; margin-right: 10px; background: #0B7C3E; color: #fff; border: none; border-radius: 5px; }
    button:hover { background: #075E2E; }
    .msg { margin-bottom: 20px; padding: 10px; background: #eaf7ef; border-left: 5px solid green; }
    .error { margin-bottom: 20px; padding: 10px; background: #ffe5e5; border-left: 5px solid red; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { padding: 8px; border-bottom: 1px solid #ccc; }
    th { background: #f0f0f0; }
  </style>
</head>
<body>
<div class="box">
  <a href="admin_dashboard.php"><button style="background:#333;color:#fff;margin-bottom:15px;">🏠 Back to Dashboard</button></a>
  <h2>📨 Admin Email Manager</h2>

  <?php if ($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>
  <?php foreach ($errors as $e): ?><div class="error"><?= $e ?></div><?php endforeach; ?>

  <form method="post" enctype="multipart/form-data">
    <label>Target Audience</label>
    <select name="target" onchange="document.getElementById('vendorEmails').style.display = this.value === 'vendor' ? 'block' : 'none';">
      <option value="all">All Vendors</option>
      <option value="vendor">Specific Vendor(s)</option>
    </select>

    <div id="vendorEmails" style="display:none;">
      <input type="text" name="specific_email" placeholder="e.g. vendor1@email.com, vendor2@email.com">
    </div>

    <label>Subject</label>
    <input type="text" name="subject" value="<?= htmlspecialchars($used_template['subject'] ?? '') ?>" required>

    <label>Message</label>
    <textarea id="editor" name="message"><?= htmlspecialchars($used_template['content'] ?? '') ?></textarea>

    <label>Attachment (PDF/Image)</label>
    <input type="file" name="attachment" accept=".pdf,image/*">

    <label>Schedule (optional)</label>
    <input type="datetime-local" name="schedule_date">

    <button type="submit" name="send_now">📤 Send Now</button>
    <button type="submit" name="schedule_now">📅 Schedule</button>
  </form>

  <hr>
  <form method="post">
    <h3>💾 Save Template</h3>
    <input type="text" name="template_name" placeholder="Template Name" value="<?= htmlspecialchars($edit_template['name'] ?? '') ?>" required>
    <input type="text" name="template_subject" placeholder="Subject" value="<?= htmlspecialchars($edit_template['subject'] ?? '') ?>" required>
    <textarea name="template_message" rows="5"><?= htmlspecialchars($edit_template['content'] ?? '') ?></textarea>
    <button type="submit" name="save_template">💾 Save Template</button>
  </form>

  <hr>
  <h3>📂 Saved Templates</h3>
  <table>
    <tr><th>Name</th><th>Subject</th><th>Actions</th></tr>
    <?php while($tpl = $templates->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($tpl['name']) ?></td>
        <td><?= htmlspecialchars($tpl['subject']) ?></td>
        <td>
          <a href="?use=<?= $tpl['id'] ?>">🧩 Use</a> |
          <a href="?edit=<?= $tpl['id'] ?>">✏️ Edit</a> |
          <a href="?delete=<?= $tpl['id'] ?>" onclick="return confirm('Delete template?')">🗑️ Delete</a>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>

  <hr>
  <a href="?action=send_due"><button>🚀 Send Due Scheduled Emails</button></a>
</div>
</body>
</html>