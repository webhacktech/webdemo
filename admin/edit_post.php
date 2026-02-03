<?php
require '../config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid post ID.");
}

$id = intval($_GET['id']);
$post = null;
$msg = "";

// Fetch post
$stmt = $conn->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Post not found.");
}
$post = $result->fetch_assoc();
$stmt->close();

// Fetch categories
$catResult = $conn->query("SELECT id, name FROM categories ORDER BY name");
$categories = [];
while ($row = $catResult->fetch_assoc()) {
    $categories[] = $row;
}

// Update post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $excerpt = trim($_POST['excerpt']);
    $content = trim($_POST['content']);
    $video_url = trim($_POST['video_url']);
    $category_id = intval($_POST['category_id']);

    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));

    $image = $post['image'];
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "../uploads/blog/";
        $filename = time() . "_" . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $filename;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image = 'uploads/blog/' . $filename;
        }
    }

    $stmt = $conn->prepare("UPDATE posts SET title=?, slug=?, excerpt=?, content=?, image=?, video_url=?, category_id=? WHERE id=?");
    $stmt->bind_param("ssssssii", $title, $slug, $excerpt, $content, $image, $video_url, $category_id, $id);
    if ($stmt->execute()) {
        $msg = "✅ Post updated successfully!";
        $post = array_merge($post, compact('title', 'slug', 'excerpt', 'content', 'image', 'video_url', 'category_id'));
    } else {
        $msg = "❌ Update failed: " . $conn->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Blog Post – Sellevo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="../logo.png">
  <style>
    :root {
      --green: #0B7C3E;
      --green-dark: #075E2E;
      --bg: #f9f9f9;
    }
    body {
      font-family: 'Segoe UI', sans-serif;
      background: var(--bg);
      padding: 20px;
      color: #222;
    }
    h2 {
      text-align: center;
      color: var(--green);
    }
    a.back-link {
      color: var(--green);
      text-decoration: none;
      display: inline-block;
      margin-bottom: 20px;
      font-weight: bold;
    }
    form {
      background: #fff;
      padding: 25px;
      max-width: 720px;
      margin: auto;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,.05);
    }
    input, textarea, select {
      width: 100%;
      margin-bottom: 18px;
      padding: 12px;
      border-radius: 6px;
      border: 1px solid #ccc;
      font-size: 1rem;
      box-sizing: border-box;
    }
    button {
      background: var(--green);
      color: #fff;
      border: none;
      padding: 12px 20px;
      border-radius: 6px;
      font-size: 1rem;
      cursor: pointer;
    }
    button:hover {
      background: var(--green-dark);
    }
    .msg {
      max-width: 720px;
      margin: 15px auto;
      padding: 12px;
      background: #eaf7ef;
      border-left: 4px solid var(--green);
      color: var(--green-dark);
    }
    .preview-img {
      max-width: 100%;
      height: auto;
      margin-bottom: 20px;
    }
  </style>
</head>
<body>

<a class="back-link" href="admin_dashboard.php">← Back to Admin Dashboard</a>
<h2>Edit Blog Post</h2>

<?php if ($msg): ?>
  <div class="msg"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <label>Title</label>
  <input type="text" name="title" value="<?= htmlspecialchars($post['title']) ?>" required>

  <label>Excerpt</label>
  <textarea name="excerpt" rows="2" required><?= htmlspecialchars($post['excerpt']) ?></textarea>

  <label>Content</label>
  <textarea name="content" rows="8" required><?= htmlspecialchars($post['content']) ?></textarea>

  <label>Category</label>
  <select name="category_id" required>
    <option value="">Select Category</option>
    <?php foreach ($categories as $cat): ?>
      <option value="<?= $cat['id'] ?>" <?= $post['category_id'] == $cat['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($cat['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label>Current Image:</label><br>
  <?php if (!empty($post['image'])): ?>
    <img src="../<?= $post['image'] ?>" alt="Current Image" class="preview-img">
  <?php else: ?>
    <p>No image uploaded.</p>
  <?php endif; ?>

  <label>Change Image (optional)</label>
  <input type="file" name="image" accept="image/*">

  <label>YouTube Video URL (optional)</label>
  <input type="url" name="video_url" value="<?= htmlspecialchars($post['video_url']) ?>">

  <button type="submit">Save Changes</button>
</form>

</body>
</html>