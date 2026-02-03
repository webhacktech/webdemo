<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once '../config.php';

// Fetch settings
$settings = $conn->query("SELECT * FROM site_settings WHERE id = 1")->fetch_assoc() ?? [
    'paystack_secret_key' => '',
    'flutterwave_secret_key' => '',
    'crypto_wallet_address' => '',
    'platform_commission_percent' => '10',
    'withdrawal_threshold' => '2000',
    'usd_to_ngn_rate' => '1500'
];

$success_msg = $_SESSION['settings_success'] ?? '';
unset($_SESSION['settings_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin - Site Settings</title>
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      background: #f9fafb;
      margin: 0; padding: 30px 15px;
      color: #333;
    }
    h1 {
      color: #106b28;
      margin-bottom: 25px;
      font-weight: 700;
      font-size: 28px;
      text-align: center;
    }
    form {
      max-width: 520px;
      margin: 0 auto;
      background: white;
      border-radius: 10px;
      padding: 25px 30px;
      box-shadow: 0 3px 10px rgb(0 0 0 / 0.1);
    }
    label {
      display: block;
      font-weight: 600;
      margin-bottom: 6px;
      margin-top: 20px;
      color: #106b28;
    }
    input[type="text"],
    input[type="number"],
    input[type="password"] {
      width: 100%;
      padding: 11px 14px;
      border: 1.8px solid #ddd;
      border-radius: 6px;
      font-size: 15px;
    }
    input:focus {
      border-color: #106b28;
      outline: none;
    }
    .input-group {
      position: relative;
    }
    .toggle-password {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      font-size: 14px;
      color: #555;
      font-weight: 700;
    }
    .row {
      display: flex;
      gap: 20px;
      margin-top: 20px;
    }
    .row > div {
      flex: 1;
    }
    button[type="submit"] {
      margin-top: 30px;
      width: 100%;
      background: #106b28;
      border: none;
      color: white;
      font-size: 18px;
      padding: 14px 0;
      font-weight: 700;
      border-radius: 8px;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }
    button:hover {
      background: #0c4e1e;
    }
    .success-msg {
      max-width: 520px;
      margin: 0 auto 20px auto;
      background: #d1e7dd;
      border: 1.5px solid #0f5132;
      color: #0f5132;
      padding: 15px 20px;
      border-radius: 8px;
      font-weight: 600;
      text-align: center;
    }
    @media (max-width: 600px) {
      .row {
        flex-direction: column;
      }
      form {
        padding: 20px 15px;
      }
    }
  </style>
</head>
<body>

<h1>⚙️ Platform Settings</h1>

<?php if ($success_msg): ?>
  <div class="success-msg"><?= htmlspecialchars($success_msg) ?></div>
<?php endif; ?>

<form method="post" action="update_settings.php" novalidate>
  <label for="paystack_secret_key">Paystack Secret Key</label>
  <div class="input-group">
    <input type="password" id="paystack_secret_key" name="paystack_secret_key" autocomplete="off" value="<?= htmlspecialchars($settings['paystack_secret_key']) ?>" required>
    <span class="toggle-password" onclick="togglePassword('paystack_secret_key')">Show</span>
  </div>

  <label for="flutterwave_secret_key">Flutterwave Secret Key</label>
  <div class="input-group">
    <input type="password" id="flutterwave_secret_key" name="flutterwave_secret_key" autocomplete="off" value="<?= htmlspecialchars($settings['flutterwave_secret_key']) ?>" required>
    <span class="toggle-password" onclick="togglePassword('flutterwave_secret_key')">Show</span>
  </div>

  <label for="crypto_wallet_address">Crypto Wallet Address</label>
  <input type="text" id="crypto_wallet_address" name="crypto_wallet_address" value="<?= htmlspecialchars($settings['crypto_wallet_address']) ?>" required>

  <div class="row">
    <div>
      <label for="platform_commission_percent">Platform Commission (%)</label>
      <input type="number" min="0" max="100" id="platform_commission_percent" name="platform_commission_percent" value="<?= htmlspecialchars($settings['platform_commission_percent']) ?>" required>
    </div>
    <div>
      <label for="withdrawal_threshold">Withdrawal Threshold (₦)</label>
      <input type="number" min="0" step="100" id="withdrawal_threshold" name="withdrawal_threshold" value="<?= htmlspecialchars($settings['withdrawal_threshold']) ?>" required>
    </div>
  </div>

  <label for="usd_to_ngn_rate">USD to NGN Exchange Rate (Manual)</label>
  <input type="number" min="1" step="1" id="usd_to_ngn_rate" name="usd_to_ngn_rate" value="<?= htmlspecialchars($settings['usd_to_ngn_rate']) ?>" required>

  <button type="submit">💾 Save Settings</button>
</form>

<script>
  function togglePassword(fieldId) {
    const input = document.getElementById(fieldId);
    const toggle = input.nextElementSibling;
    if (input.type === "password") {
      input.type = "text";
      toggle.textContent = "Hide";
    } else {
      input.type = "password";
      toggle.textContent = "Show";
    }
  }
</script>

</body>
</html>