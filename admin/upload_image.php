<?php
// upload_image.php
$uploadDir = '../uploads/blog/';
$uploadUrl = 'uploads/blog/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['file']['tmp_name'];
    $fileName = time() . "_" . basename($_FILES['file']['name']);
    $destPath = $uploadDir . $fileName;

    if (move_uploaded_file($fileTmpPath, $destPath)) {
        $url = $uploadUrl . $fileName;
        echo json_encode(['location' => $url]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Image upload failed']);