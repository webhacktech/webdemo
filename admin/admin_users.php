<?php
session_start();
require_once '../config.php';
require_once '../email_config.php';
require_once '../send_mail.php';

// Authentication check
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$admin_role = $_SESSION['admin_role'] ?? 'staff';

// Helpers
function sendAdminNotification($to, $subject, $body) {
    sendEmail($to, $subject, $body);
}

function logAdminAction($admin_id, $action) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action) VALUES (?, ?)");
    $stmt->bind_param("is", $admin_id, $action);
    $stmt->execute();
    $stmt->close();
}

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $username = trim($_POST['new_username']);
    $password = trim($_POST['new_password']);
    $role = $_POST['role'];
    if ($username && $password && in_array($role, ['super', 'staff'])) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admin_users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashed, $role);
        if ($stmt->execute()) {
            logAdminAction($admin_id, "Created admin '$username'");
            sendAdminNotification($mailer_username, "New Admin Created", "Admin <b>$username</b> was created.");
            $_SESSION['success'] = "Admin '$username' created successfully.";
        } else {
            $_SESSION['error'] = "Error: " . $stmt->error;
        }
        header("Location: admin_users.php"); exit;
    } else {
        $_SESSION['error'] = "All fields required and role must be super or staff.";
    }
}

// Handle Reset Password
if (isset($_POST['reset_password']) && isset($_POST['reset_id'])) {
    $id = (int)$_POST['reset_id'];
    if ($id !== $admin_id) {
        $newPass = password_hash("changeme123", PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admin_users SET password=? WHERE admin_id=?");
        $stmt->bind_param("si", $newPass, $id);
        if ($stmt->execute()) {
            logAdminAction($admin_id, "Reset password for admin ID $id");
            $_SESSION['success'] = "Password reset to 'changeme123'.";
        } else {
            $_SESSION['error'] = "Password reset failed.";
        }
    } else {
        $_SESSION['error'] = "You can't reset your own password here.";
    }
    header("Location: admin_users.php"); exit;
}

// Handle Suspend
if (isset($_POST['suspend_admin']) && isset($_POST['suspend_id'])) {
    $id = (int)$_POST['suspend_id'];
    if ($id !== $admin_id) {
        $stmt = $conn->prepare("UPDATE admin_users SET status='suspended' WHERE admin_id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            logAdminAction($admin_id, "Suspended admin ID $id");
            $_SESSION['success'] = "Admin suspended.";
        } else {
            $_SESSION['error'] = "Suspend failed.";
        }
    } else {
        $_SESSION['error'] = "You can't suspend yourself.";
    }
    header("Location: admin_users.php"); exit;
}

// Handle Bulk Suspend
if (isset($_POST['bulk_suspend']) && !empty($_POST['selected_admins'])) {
    $ids = array_map('intval', $_POST['selected_admins']);
    $ids = array_filter($ids, fn($id) => $id !== $admin_id); // Don't suspend self
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $conn->prepare("UPDATE admin_users SET status='suspended' WHERE admin_id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids);
    if ($stmt->execute()) {
        logAdminAction($admin_id, "Bulk suspended admin IDs: " . implode(',', $ids));
        $_SESSION['success'] = "Selected admins suspended.";
    } else {
        $_SESSION['error'] = "Bulk suspend failed.";
    }
    header("Location: admin_users.php"); exit;
}

// Fetch admins
$stmt = $conn->prepare("SELECT admin_id, username, role, status, created_at FROM admin_users ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Users</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; padding: 20px; }
        h2 { color: #106b28; margin-bottom: 15px; }
        .alert-success, .alert-error {
            padding: 10px; border-radius: 5px; margin-bottom: 15px;
        }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        form { margin-bottom: 20px; }
        input, select, button {
            padding: 10px; margin: 5px; border-radius: 4px; border: 1px solid #ccc;
        }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-info { background: #17a2b8; color: white; }
        table {
            width: 100%; border-collapse: collapse; background: white; box-shadow: 0 0 8px rgba(0,0,0,0.05);
        }
        th, td {
            padding: 12px; border: 1px solid #ddd;
        }
        th { background: #106b28; color: white; }
        .action-buttons form {
            display: inline;
        }
        @media (max-width: 768px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            tr {
                margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 6px; padding: 10px; background: white;
            }
            td {
                border: none; padding: 6px 0;
            }
            td:before {
                content: attr(data-label);
                font-weight: bold; display: block; margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>

<h2>Admin Management</h2>

<?php if (!empty($_SESSION['success'])): ?>
    <div class="alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<!-- Add Admin -->
<form method="POST">
    <input type="text" name="new_username" placeholder="Username" required>
    <input type="password" name="new_password" placeholder="Password" required>
    <select name="role" required>
        <option value="">Select role</option>
        <option value="super">Super</option>
        <option value="staff">Staff</option>
    </select>
    <button type="submit" name="add_admin" class="btn-success">Add Admin</button>
</form>

<!-- Admin Table -->
<form method="POST">
    <table>
        <thead>
            <tr>
                <th><input type="checkbox" onclick="toggleAll(this)"></th>
                <th>Username</th>
                <th>Role</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($admin = $result->fetch_assoc()): ?>
                <tr>
                    <td data-label="Select">
                        <?php if ($admin['admin_id'] != $admin_id): ?>
                            <input type="checkbox" name="selected_admins[]" value="<?= $admin['admin_id'] ?>">
                        <?php endif; ?>
                    </td>
                    <td data-label="Username"><?= htmlspecialchars($admin['username']) ?></td>
                    <td data-label="Role"><?= ucfirst($admin['role']) ?></td>
                    <td data-label="Status"><?= ucfirst($admin['status']) ?></td>
                    <td data-label="Created"><?= $admin['created_at'] ?></td>
                    <td data-label="Actions" class="action-buttons">
                        <?php if ($admin['admin_id'] != $admin_id): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="reset_id" value="<?= $admin['admin_id'] ?>">
                                <button type="submit" name="reset_password" class="btn-info" onclick="return confirm('Reset password?')">Reset</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="suspend_id" value="<?= $admin['admin_id'] ?>">
                                <button type="submit" name="suspend_admin" class="btn-warning" onclick="return confirm('Suspend admin?')">Suspend</button>
                            </form>
                            <?php if ($admin_role === 'super'): ?>
                                <a href="delete_admin.php?id=<?= $admin['admin_id'] ?>" class="btn-danger" onclick="return confirm('Delete this admin?')">Delete</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <em>Logged In</em>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <?php if ($admin_role === 'super'): ?>
        <button type="submit" name="bulk_suspend" class="btn-warning" onclick="return confirm('Suspend selected admins?')">Suspend Selected</button>
    <?php endif; ?>
</form>

<script>
function toggleAll(source) {
    const checkboxes = document.querySelectorAll('input[type="checkbox"][name="selected_admins[]"]');
    checkboxes.forEach(cb => cb.checked = source.checked);
}
</script>

</body>
</html>