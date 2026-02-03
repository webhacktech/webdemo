<?php
require '../config.php';

$msg = "";

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM posts WHERE id = $id");
    $msg = "❌ Blog post deleted.";
}

// Fetch all posts
$result = $conn->query("
    SELECT posts.*, categories.name AS category_name 
    FROM posts 
    LEFT JOIN categories ON posts.category_id = categories.id 
    ORDER BY posts.created_at DESC
");

$posts = [];
while ($row = $result->fetch_assoc()) {
    $posts[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>All Blog Posts – Sellevo</title>
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
    .msg {
      max-width: 900px;
      margin: 10px auto;
      padding: 12px;
      background: #eaf7ef;
      border-left: 4px solid var(--green);
      color: var(--green-dark);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
      max-width: 960px;
      margin: auto;
      box-shadow: 0 2px 10px rgba(0,0,0,0.04);
      border-radius: 8px;
      overflow: hidden;
    }
    th, td {
      padding: 12px 14px;
      border-bottom: 1px solid #eee;
      text-align: left;
      vertical-align: middle;
    }
    th {
      background: #f5f5f5;
    }
    .thumb {
      width: 60px;
      height: auto;
      border-radius: 4px;
    }
    .actions a {
      margin-right: 10px;
      text-decoration: none;
      color: var(--green-dark);
    }
    .actions a.delete {
      color: red;
    }
    .actions a:hover {
      text-decoration: underline;
    }
    @media (max-width: 600px) {
      table, thead, tbody, th, td, tr {
        display: block;
      }
      tr {
        margin-bottom: 15px;
      }
      td {
        padding-left: 40%;
        position: relative;
      }
      td::before {
        position: absolute;
        left: 10px;
        top: 12px;
        font-weight: bold;
        white-space: nowrap;
      }
      td:nth-of-type(1)::before { content: "Image"; }
      td:nth-of-type(2)::before { content: "Title"; }
      td:nth-of-type(3)::before { content: "Category"; }
      td:nth-of-type(4)::before { content: "Date"; }
      td:nth-of-type(5)::before { content: "Actions"; }
    }
  </style>
</head>
<body>

<a class="back-link" href="admin_dashboard.php">← Back to Admin Dashboard</a>
<h2>All Blog Posts</h2>

<?php if ($msg): ?>
  <div class="msg"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<table>
  <thead>
    <tr>
      <th>Image</th>
      <th>Title</th>
      <th>Category</th>
      <th>Date</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($posts)): ?>
      <tr><td colspan="5">No blog posts yet.</td></tr>
    <?php else: ?>
      <?php foreach ($posts as $post): ?>
        <tr>
          <td>
            <?php if (!empty($post['image'])): ?>
              <img src="../<?= $post['image'] ?>" class="thumb" alt="thumb">
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($post['title']) ?></td>
          <td><?= htmlspecialchars($post['category_name'] ?: '—') ?></td>
          <td><?= date('M d, Y', strtotime($post['created_at'])) ?></td>
          <td class="actions">
            <a href="edit_post.php?id=<?= $post['id'] ?>">Edit</a>
            <a href="?delete=<?= $post['id'] ?>" class="delete" onclick="return confirm('Delete this post?')">Delete</a>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

</body>
</html>