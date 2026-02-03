<?php
require '../config.php';

$msg = '';
$edit_mode = false;
$edit_post = null;

// Handle delete
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $msg = "✅ Blog post deleted successfully.";
    } else {
        $msg = "❌ Error deleting post: " . $stmt->error;
    }
    header("Location: admin_blog.php?msg=" . urlencode($msg));
    exit;
}

// Load edit
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_post = $result->fetch_assoc();
    $stmt->close();
    if ($edit_post) $edit_mode = true;
    else $msg = "❌ Post not found.";
}

// Submit (create or update)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $excerpt = trim($_POST['excerpt']);
    $content = trim($_POST['content']);
    $video_url = trim($_POST['video_url']);
    $category_id = (int)$_POST['category_id'];
    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;

    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));

    $image = $edit_post['image'] ?? '';
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "../uploads/blog/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        $filename = time() . "_" . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $filename;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image = 'uploads/blog/' . $filename;
            if ($edit_mode && !empty($edit_post['image']) && file_exists('../' . $edit_post['image'])) {
                unlink('../' . $edit_post['image']);
            }
        }
    }

    if ($post_id > 0) {
        $stmt = $conn->prepare("UPDATE posts SET title=?, slug=?, excerpt=?, content=?, image=?, video_url=?, category_id=? WHERE id=?");
        $stmt->bind_param("ssssssii", $title, $slug, $excerpt, $content, $image, $video_url, $category_id, $post_id);
        if ($stmt->execute()) {
            $msg = "✅ Blog post updated!";
            header("Location: admin_blog.php?msg=" . urlencode($msg));
            exit;
        } else {
            $msg = "❌ Error updating post: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO posts (title, slug, excerpt, content, image, video_url, category_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssi", $title, $slug, $excerpt, $content, $image, $video_url, $category_id);
        if ($stmt->execute()) {
            $msg = "✅ Blog post published!";
            header("Location: admin_blog.php?msg=" . urlencode($msg));
            exit;
        } else {
            $msg = "❌ Error: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch categories
$catResult = $conn->query("SELECT id, name FROM categories ORDER BY name");
$categories = [];
while ($row = $catResult->fetch_assoc()) $categories[] = $row;

// Fetch posts
$postResult = $conn->query("SELECT p.*, c.name AS category_name FROM posts p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");
$posts = [];
while ($row = $postResult->fetch_assoc()) $posts[] = $row;

// Show msg
if (isset($_GET['msg'])) $msg = $_GET['msg'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= $edit_mode ? 'Edit Blog Post' : 'Add Blog Post' ?> – Sellevo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="../logo.png">
  <script src="https://cdn.tiny.cloud/1/l42ny67yotl3tp19fzwxqnmb4h4vrjg9oomwrfi1luymptee/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
  <script>
    tinymce.init({
      selector: 'textarea[name="content"]',
      height: 400,
      plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
      toolbar: 'undo redo | styles | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media | code preview',
      menubar: false,
      image_title: true,
      automatic_uploads: true,
      file_picker_types: 'image',
      images_upload_url: 'upload_image.php',
      file_picker_callback: function (cb, value, meta) {
        const input = document.createElement('input');
        input.setAttribute('type', 'file');
        input.setAttribute('accept', 'image/*');
        input.onchange = function () {
          const file = this.files[0];
          const formData = new FormData();
          formData.append('file', file);
          fetch('upload_image.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => cb(data.location, { title: file.name }))
          .catch(() => alert('Image upload failed.'));
        };
        input.click();
      }
    });
  </script>
  <style>
    :root {
      --green: #0B7C3E;
      --green-dark: #075E2E;
      --bg: #f9f9f9;
    }
    body {
      font-family: 'Segoe UI', sans-serif;
      padding: 20px;
      background: var(--bg);
      color: #222;
    }
    h2 {
      color: var(--green);
      text-align: center;
    }
    .back-link {
      display: inline-block;
      margin-bottom: 20px;
      color: var(--green);
      text-decoration: none;
      font-weight: bold;
    }
    .back-link:hover {
      text-decoration: underline;
    }
    form {
      background: #fff;
      padding: 25px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,.05);
      max-width: 700px;
      margin: auto;
      margin-bottom: 50px;
    }
    input, textarea, select {
      width: 100%;
      margin-bottom: 20px;
      padding: 12px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 1rem;
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
      max-width: 700px;
      margin: 15px auto;
      padding: 10px;
      background: #eaf7ef;
      color: #075E2E;
      border-left: 4px solid #0B7C3E;
    }
    table {
      width: 100%;
      max-width: 900px;
      margin: 0 auto 40px;
      border-collapse: collapse;
      background: #fff;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgb(0 0 0 / 0.1);
    }
    th, td {
      padding: 12px 15px;
      border-bottom: 1px solid #ddd;
      text-align: left;
    }
    th {
      background: #f1f1f1;
      font-weight: 700;
    }
    a.action-link {
      margin-right: 15px;
      font-weight: 600;
      text-decoration: none;
      color: var(--green);
      cursor: pointer;
    }
    a.action-link.delete {
      color: #d93025;
    }
    a.action-link:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

<a class="back-link" href="admin_dashboard.php">← Back to Admin Dashboard</a>

<h2><?= $edit_mode ? 'Edit Blog Post' : 'Add New Blog Post' ?></h2>

<?php if ($msg): ?>
  <div class="msg"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<form action="" method="POST" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="post_id" value="<?= $edit_mode ? (int)$edit_post['id'] : '' ?>">

  <label>Post Title</label>
  <input type="text" name="title" required value="<?= $edit_mode ? htmlspecialchars($edit_post['title']) : '' ?>">

  <label>Excerpt</label>
  <textarea name="excerpt" rows="2" required><?= $edit_mode ? htmlspecialchars($edit_post['excerpt']) : '' ?></textarea>

  <label>Content</label>
  <textarea name="content" rows="8" required><?= $edit_mode ? htmlspecialchars($edit_post['content']) : '' ?></textarea>

  <label>Category</label>
  <?php if (count($categories) > 0): ?>
    <select name="category_id" required>
      <option value="">Select Category</option>
      <?php foreach($categories as $cat): ?>
        <option value="<?=$cat['id']?>" <?= ($edit_mode && $edit_post['category_id'] == $cat['id']) ? 'selected' : '' ?>>
          <?=htmlspecialchars($cat['name'])?>
        </option>
      <?php endforeach; ?>
    </select>
  <?php else: ?>
    <p style="color:#d00;">⚠️ No categories found.</p>
  <?php endif; ?>

  <label>Blog Image (optional)</label>
  <input type="file" name="image" accept="image/*">
  <?php if ($edit_mode && !empty($edit_post['image'])): ?>
    <p>Current: <img src="../<?= htmlspecialchars($edit_post['image']) ?>" style="max-width:150px;"></p>
  <?php endif; ?>

  <label>YouTube Video URL (optional)</label>
  <input type="url" name="video_url" value="<?= $edit_mode ? htmlspecialchars($edit_post['video_url']) : '' ?>">

  <button type="submit"><?= $edit_mode ? 'Update Post' : 'Publish Post' ?></button>
  <?php if ($edit_mode): ?>
    <a href="admin_blog.php" style="margin-left: 15px; color:#d00; font-weight:bold;">Cancel Edit</a>
  <?php endif; ?>
</form>

<h2 style="text-align:center;">Existing Blog Posts</h2>
<?php if (count($posts) > 0): ?>
  <table>
    <thead>
      <tr>
        <th>Title</th>
        <th>Excerpt</th>
        <th>Category</th>
        <th>Image</th>
        <th>Video</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($posts as $p): ?>
      <tr>
        <td><?= htmlspecialchars($p['title']) ?></td>
        <td><?= htmlspecialchars(strlen($p['excerpt']) > 50 ? substr($p['excerpt'], 0, 50) . '...' : $p['excerpt']) ?></td>
        <td><?= htmlspecialchars($p['category_name']) ?></td>
        <td>
          <?php if ($p['image']): ?>
            <img src="../<?= htmlspecialchars($p['image']) ?>" style="max-width: 80px; max-height: 50px;">
          <?php else: ?> - <?php endif; ?>
        </td>
        <td>
          <?= $p['video_url'] ? '<a href="' . htmlspecialchars($p['video_url']) . '" target="_blank">View</a>' : '-' ?>
        </td>
        <td>
          <a href="?edit=<?= (int)$p['id'] ?>" class="action-link">Edit</a>
          <a href="?delete=<?= (int)$p['id'] ?>" onclick="return confirm('Are you sure?')" class="action-link delete">Delete</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php else: ?>
  <p style="text-align:center;">No blog posts found yet.</p>
<?php endif; ?>

</body>
</html>