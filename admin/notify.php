<?php
function setNotification($type, $message) {
    $_SESSION['admin_notification'] = [
        'type' => $type,
        'message' => $message
    ];
}

function showNotification() {
    if (!empty($_SESSION['admin_notification'])) {
        $type = $_SESSION['admin_notification']['type'];
        $message = htmlspecialchars($_SESSION['admin_notification']['message']);
        $class = $type === 'success' ? 'alert-success' : 'alert-error';

        echo "<div class='alert $class'>$message</div>";
        unset($_SESSION['admin_notification']);
    }
}
?>