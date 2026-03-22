<?php
$baseUrl = '../';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_admin();

$errors = [];

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $editId = (int)($_POST['edit_id'] ?? 0);

        if (!$slug) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
            $slug = trim($slug, '-');
        }

        if (strlen($name) < 2 || strlen($name) > 100) {
            $errors[] = 'Category name must be 2-100 characters.';
        }
        if (strlen($slug) < 2 || strlen($slug) > 100) {
            $errors[] = 'Slug must be 2-100 characters.';
        }

        if (empty($errors)) {
            if ($action === 'add') {
                $stmt = $pdo->prepare('INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)');
                $stmt->execute([$name, $slug, $description ?: null]);
                flash('success', 'Category added.');
            } else {
                $stmt = $pdo->prepare('UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?');
                $stmt->execute([$name, $slug, $description ?: null, $editId]);
                flash('success', 'Category updated.');
            }
            redirect('categories.php');
        }
    }

    if ($action === 'delete') {
        $deleteId = (int)($_POST['delete_id'] ?? 0);
        if ($deleteId > 0) {
            // Check if category has submissions (RESTRICT)
            $count = $pdo->prepare('SELECT COUNT(*) FROM submissions WHERE category_id = ?');
            $count->execute([$deleteId]);
            if ((int)$count->fetchColumn() > 0) {
                flash('error', 'Cannot delete category — it has submissions. Reassign them first.');
            } else {
                $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$deleteId]);
                flash('success', 'Category deleted.');
            }
        }
        redirect('categories.php');
    }
}

$categories = $pdo->query("
    SELECT c.*, (SELECT COUNT(*) FROM submissions WHERE category_id = c.id) AS submission_count
    FROM categories c ORDER BY c.name
")->fetchAll();

// Editing?
$editing = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($categories as $cat) {
        if ($cat['id'] === $editId) { $editing = $cat; break; }
    }
}

$pageTitle = 'Manage Categories';
require_once '../includes/header.php';
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/_sidebar.php'; ?>
    <div class="admin-content">
        <h1>Categories</h1>

        <?php if ($errors): ?>
            <div class="flash flash-error">
                <?php foreach ($errors as $e): ?>
                    <p><?= sanitize($e) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div style="background: var(--color-surface); border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow); margin-bottom: 24px;">
            <h2 style="font-size: 1rem; margin-bottom: 12px;"><?= $editing ? 'Edit Category' : 'Add Category' ?></h2>
            <form method="POST" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editing ? 'edit' : 'add' ?>">
                <?php if ($editing): ?>
                    <input type="hidden" name="edit_id" value="<?= $editing['id'] ?>">
                <?php endif; ?>
                <div class="form-group" style="margin-bottom:0;flex:1;min-width:150px;">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" value="<?= sanitize($editing['name'] ?? '') ?>" required>
                </div>
                <div class="form-group" style="margin-bottom:0;flex:1;min-width:150px;">
                    <label for="slug">Slug</label>
                    <input type="text" id="slug" name="slug" value="<?= sanitize($editing['slug'] ?? '') ?>" placeholder="auto-generated">
                </div>
                <div class="form-group" style="margin-bottom:0;flex:2;min-width:200px;">
                    <label for="description">Description</label>
                    <input type="text" id="description" name="description" value="<?= sanitize($editing['description'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><?= $editing ? 'Update' : 'Add' ?></button>
                <?php if ($editing): ?>
                    <a href="categories.php" class="btn btn-outline btn-sm">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Description</th>
                    <th>Submissions</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><strong><?= sanitize($cat['name']) ?></strong></td>
                        <td><code><?= sanitize($cat['slug']) ?></code></td>
                        <td><?= sanitize($cat['description'] ?? '—') ?></td>
                        <td><?= $cat['submission_count'] ?></td>
                        <td>
                            <div class="admin-actions">
                                <a href="?edit=<?= $cat['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this category?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="delete_id" value="<?= $cat['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
