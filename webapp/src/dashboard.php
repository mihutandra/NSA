<?php
require_once __DIR__ . '/config.php';
session_start();
requireLogin();

$db      = getDB();
$userId  = (int)$_SESSION['user_id'];
$error   = '';
$success = '';

// ── Server info ────────────────────────────────────────────────────────
$containerIp = gethostbyname(gethostname());
// Extract the first (leftmost) IP from X-Forwarded-For to get the real client IP
$xForwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
$clientIp = $_SERVER['HTTP_X_REAL_IP']
          ?? (strstr($xForwardedFor, ',', true) ?: $xForwardedFor)
          ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$clientIp = trim($clientIp);

// ── Handle CRUD actions ────────────────────────────────────────────────
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['create', 'update', 'delete'], true)) {
    verifyCsrf();
}

switch ($action) {

    // ── Create ──────────────────────────────────────────────────────
    case 'create':
        $title       = trim($_POST['title']       ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($title === '') {
            $error = 'Title is required.';
        } else {
            $stmt = $db->prepare(
                'INSERT INTO items (user_id, title, description) VALUES (:uid, :title, :desc)'
            );
            $stmt->execute([':uid' => $userId, ':title' => $title, ':desc' => $description]);
            $success = 'Item created.';
        }
        break;

    // ── Update ──────────────────────────────────────────────────────
    case 'update':
        $id          = (int)($_POST['id']          ?? 0);
        $title       = trim($_POST['title']        ?? '');
        $description = trim($_POST['description']  ?? '');
        if ($id <= 0 || $title === '') {
            $error = 'Invalid data for update.';
        } else {
            $stmt = $db->prepare(
                'UPDATE items SET title = :title, description = :desc
                  WHERE id = :id AND user_id = :uid'
            );
            $stmt->execute([':title' => $title, ':desc' => $description,
                            ':id'   => $id,     ':uid'  => $userId]);
            $success = 'Item updated.';
        }
        break;

    // ── Delete ──────────────────────────────────────────────────────
    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare('DELETE FROM items WHERE id = :id AND user_id = :uid');
            $stmt->execute([':id' => $id, ':uid' => $userId]);
            $success = 'Item deleted.';
        }
        break;
}

// ── Fetch items for current user ───────────────────────────────────────
$stmt  = $db->prepare('SELECT * FROM items WHERE user_id = :uid ORDER BY created_at DESC');
$stmt->execute([':uid' => $userId]);
$items = $stmt->fetchAll();

// ── Pre-populate edit form ─────────────────────────────────────────────
$editItem = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt   = $db->prepare('SELECT * FROM items WHERE id = :id AND user_id = :uid LIMIT 1');
    $stmt->execute([':id' => $editId, ':uid' => $userId]);
    $editItem = $stmt->fetch() ?: null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard – <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>

<nav>
    <div class="container">
        <span class="brand"><?= APP_NAME ?></span>
        <ul>
            <li><a href="/dashboard.php">Dashboard</a></li>
            <li><span style="color:#94a3b8">Hi, <?= htmlspecialchars($_SESSION['username']) ?></span></li>
            <li><a href="/logout.php">Logout</a></li>
        </ul>
    </div>
</nav>

<div class="container">

    <!-- Server Info -->
    <div class="card">
        <h2>Server Information</h2>
        <div class="info-grid">
            <div class="info-item">
                <div class="label">Container IP</div>
                <div class="value"><?= htmlspecialchars($containerIp) ?></div>
            </div>
            <div class="info-item">
                <div class="label">Instance</div>
                <div class="value"><?= htmlspecialchars(INSTANCE) ?></div>
            </div>
            <div class="info-item">
                <div class="label">Hostname</div>
                <div class="value"><?= htmlspecialchars(gethostname()) ?></div>
            </div>
            <div class="info-item">
                <div class="label">Client IP</div>
                <div class="value"><?= htmlspecialchars($clientIp) ?></div>
            </div>
            <div class="info-item">
                <div class="label">PHP Version</div>
                <div class="value"><?= PHP_VERSION ?></div>
            </div>
            <div class="info-item">
                <div class="label">DB Host</div>
                <div class="value"><?= htmlspecialchars(DB_HOST) ?></div>
            </div>
        </div>
        <p style="font-size:.85rem;color:#64748b;margin-top:.5rem">
            Useful links:
            <a href="/phpmyadmin/">phpMyAdmin</a> ·
            <a href="/mail/">MailHog</a> ·
            <a href="/logs/">Access Logs (GoAccess)</a>
        </p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Create / Edit Form -->
    <div class="card">
        <h2><?= $editItem ? 'Edit Item' : 'Add New Item' ?></h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <?php if ($editItem): ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id"     value="<?= (int)$editItem['id'] ?>">
            <?php else: ?>
                <input type="hidden" name="action" value="create">
            <?php endif; ?>

            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" id="title" name="title"
                       value="<?= htmlspecialchars($editItem['title'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description"><?= htmlspecialchars($editItem['description'] ?? '') ?></textarea>
            </div>

            <div style="display:flex;gap:.75rem">
                <button type="submit" class="btn btn-primary">
                    <?= $editItem ? 'Save Changes' : 'Add Item' ?>
                </button>
                <?php if ($editItem): ?>
                    <a href="/dashboard.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Items Table -->
    <div class="card">
        <h2>My Items (<?= count($items) ?>)</h2>
        <?php if (empty($items)): ?>
            <p style="color:#64748b">No items yet. Add one above!</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= (int)$item['id'] ?></td>
                    <td><?= htmlspecialchars($item['title']) ?></td>
                    <td><?= nl2br(htmlspecialchars($item['description'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($item['created_at']) ?></td>
                    <td class="actions">
                        <a href="/dashboard.php?edit=<?= (int)$item['id'] ?>"
                           class="btn btn-secondary btn-sm">Edit</a>
                        <form method="post" style="display:inline"
                              onsubmit="return confirm('Delete this item?')">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id"     value="<?= (int)$item['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div><!-- /container -->
</body>
</html>
