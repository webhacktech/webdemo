<?php
session_start();
require '../config.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';
require '../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdrawal_id'])) {
    $id = intval($_POST['withdrawal_id']);

    // Check file upload
    if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
        die("No receipt file uploaded or upload error.");
    }

    $file = $_FILES['receipt'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','pdf'];
    if (!in_array($ext, $allowed)) die("Invalid file type. Allowed: PDF, JPG, PNG.");

    // Ensure receipts folder exists
    @mkdir('../receipts', 0755, true);

    $filename = 'receipt_' . $id . '_' . time() . '.' . $ext;
    $destination = '../receipts/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        die("Failed to upload file.");
    }

    // Update payout status and save receipt
    $stmt = $conn->prepare("UPDATE vendor_payouts SET status='approved', receipt=? WHERE id=?");
    $stmt->bind_param('si', $filename, $id);
    $stmt->execute();
    $stmt->close();

    // Fetch vendor info
    $vendor = $conn->query("
        SELECT v.email, v.store_name 
        FROM vendors v 
        JOIN vendor_payouts p ON p.vendor_id=v.id 
        WHERE p.id=$id
    ")->fetch_assoc();

    if ($vendor && $vendor['email']) {
        // Send email using PHPMailer
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
            $mail->Encoding = 'base64';
            $mail->isHTML(true);

            $mail->setFrom('support@sellevo.store', 'Sellevo Store');
            $mail->addAddress($vendor['email']);

            $mail->Subject = "✅ Your Withdrawal Has Been Approved";
            $body = "Hello {$vendor['store_name']},<br><br>";
            $body .= "Your withdrawal request has been approved.<br>";
            $body .= "You can download your receipt here: <a href='https://sellevo.store/receipts/{$filename}'>View Receipt</a><br><br>";
            $body .= "Thank you,<br>Sellevo Team";
            $mail->Body = $body;

            // Attach the receipt
            if (file_exists($destination)) {
                $mail->addAttachment($destination);
            }

            $mail->send();
        } catch (Exception $e) {
            // Optional: log the error
            error_log("Receipt email failed: " . $mail->ErrorInfo);
        }
    }

    header("Location: admin_withdrawals.php?success=1");
    exit();
}
?>