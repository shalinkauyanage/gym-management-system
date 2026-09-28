<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Admin');
$pageTitle = 'Blogs';
$action = $_GET['action'] ?? 'list';
$postId = (int) ($_GET['id'] ?? 0);

if (!tableExists($pdo, 'blog_posts')) {
  //  Load the shared file needed before this page continues.
  require __DIR__ . '/../includes/dashboard_header.php';
  echo '<div class="alert alert-warning">Import the V3 database patch to enable blog management.</div>';
  //  Load the shared file needed before this page continues.
  require __DIR__ . '/../includes/dashboard_footer.php';
  exit;
}

$hasImage = columnExists($pdo, 'blog_posts', 'image_path');
$uploadDirectory = dirname(__DIR__) . '/uploads/blog';

  //  Removes an administrator-uploaded blog cover image from storage.
function removeBlogImage(?string $relativePath): void
{
  // Student function step: This is the start of removeBlogImage(). The lines below do the main work of this helper.
  if (!$relativePath || !str_starts_with($relativePath, 'uploads/blog/')) {
    return;
  }

  $fullPath = dirname(__DIR__) . '/' . $relativePath;
  if (is_file($fullPath)) {
    @unlink($fullPath);
  }
}

  // Validates and stores a blog cover image uploaded by Admin.
function saveBlogImage(array $file, string $uploadDirectory): ?string
{
  // Student function step: This is the start of saveBlogImage(). The lines below do the main work of this helper.
  if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    return null;
  }

  if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    throw new RuntimeException('The image upload failed.');
  }

  if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
    throw new RuntimeException('Blog images must be 3 MB or smaller.');
  }

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($file['tmp_name']);
  $extensions = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
  ];

  if (!isset($extensions[$mime])) {
    throw new RuntimeException('Use a JPG, PNG or WebP image.');
  }

  if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
    throw new RuntimeException('The blog upload folder is not available.');
  }

  $filename = 'blog-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
  $destination = $uploadDirectory . '/' . $filename;

  if (!move_uploaded_file($file['tmp_name'], $destination)) {
    throw new RuntimeException('The image could not be saved.');
  }

  return 'uploads/blog/' . $filename;
}

// POST handling: only run this block when a form has been submitted.
//  Run this block only when the form is submitted with POST.
if (isPost()) {
  //  reject forged or expired form submissions before processing input.
  //  Check the CSRF token before saving, updating or deleting data.
  verifyCsrf();
  $mode = $_POST['mode'] ?? 'save';
  $postId = (int) ($_POST['post_id'] ?? 0);

  if ($mode === 'delete') {
    $post = one($pdo, 'SELECT * FROM blog_posts WHERE post_id = ?', [$postId]);
    if ($post) {
      //  Prepare and run this SQL statement using PDO.
      $pdo->prepare('DELETE FROM blog_posts WHERE post_id = ?')->execute([$postId]);
      if ($hasImage) {
        removeBlogImage($post['image_path'] ?? null);
      }
      //  Save a one-time message so the next page can tell the user what happened.
      flash('success', 'Blog post deleted.');
    }
    //  Redirect the browser after this action to avoid repeating the same request.
    redirect('admin/blogs.php');
  }

  $title = trim($_POST['title'] ?? '');
  $category = trim($_POST['category'] ?? '');
  $excerpt = trim($_POST['excerpt'] ?? '');
  $body = trim($_POST['body'] ?? '');
  $status = $_POST['status'] ?? 'Draft';
  $publishedAt = trim($_POST['published_at'] ?? '');
  $publishedAt = $publishedAt !== '' ? str_replace('T', ' ', $publishedAt) . ':00' : null;

  if ($title === '' || $excerpt === '' || $body === '') {
    //  Save a one-time message so the next page can tell the user what happened.
    flash('danger', 'Title, excerpt and article content are required.');
    redirect('admin/blogs.php?action=' . ($postId ? 'edit&id=' . $postId : 'new'));
  }

  if ($status === 'Published' && !$publishedAt) {
    $publishedAt = date('Y-m-d H:i:s');
  }

  $existing = $postId ? one($pdo, 'SELECT * FROM blog_posts WHERE post_id = ?', [$postId]) : null;
  $imagePath = $existing['image_path'] ?? null;

  try {
    if ($hasImage && isset($_FILES['image'])) {
      $newImage = saveBlogImage($_FILES['image'], $uploadDirectory);
      if ($newImage) {
        removeBlogImage($imagePath);
        $imagePath = $newImage;
      }
    }

    $slug = slugify($title);
    $duplicate = one(
      $pdo,
      'SELECT post_id FROM blog_posts WHERE slug = ? AND post_id <> ?',
      [$slug, $postId],
    );
    if ($duplicate) {
      $slug .= '-' . ($postId ?: substr((string) time(), -5));
    }

    if ($postId) {
      if ($hasImage) {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
          'UPDATE blog_posts SET title = ?, slug = ?, category = ?, excerpt = ?, body = ?, status = ?, published_at = ?, image_path = ? WHERE post_id = ?',
        )->execute([$title, $slug, $category, $excerpt, $body, $status, $publishedAt, $imagePath, $postId]);
      } else {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
          'UPDATE blog_posts SET title = ?, slug = ?, category = ?, excerpt = ?, body = ?, status = ?, published_at = ? WHERE post_id = ?',
        )->execute([$title, $slug, $category, $excerpt, $body, $status, $publishedAt, $postId]);
      }
      //  Save a one-time message so the next page can tell the user what happened.
      flash('success', 'Blog post updated successfully.');
    } else {
      if ($hasImage) {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
          'INSERT INTO blog_posts(title, slug, category, excerpt, body, status, published_at, image_path) VALUES(?, ?, ?, ?, ?, ?, ?, ?)',
        )->execute([$title, $slug, $category, $excerpt, $body, $status, $publishedAt, $imagePath]);
      } else {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
          'INSERT INTO blog_posts(title, slug, category, excerpt, body, status, published_at) VALUES(?, ?, ?, ?, ?, ?, ?)',
        )->execute([$title, $slug, $category, $excerpt, $body, $status, $publishedAt]);
      }
      //  Save a one-time message so the next page can tell the user what happened.
      flash('success', 'Blog post created successfully.');
    }
  } catch (Throwable $error) {
    //  Save a one-time message so the next page can tell the user what happened.
    flash('danger', $error instanceof RuntimeException ? $error->getMessage() : 'The blog post could not be saved.');
  }

  //  Redirect the browser after this action to avoid repeating the same request.
  redirect('admin/blogs.php');
}

$selectFields = $hasImage
  ? 'post_id, title, slug, category, excerpt, body, status, published_at, image_path'
  : 'post_id, title, slug, category, excerpt, body, status, published_at, NULL AS image_path';

$edit = $postId ? one($pdo, "SELECT {$selectFields} FROM blog_posts WHERE post_id = ?", [$postId]) : null;
$posts = all($pdo, "SELECT {$selectFields} FROM blog_posts ORDER BY post_id DESC");

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<?php if ($action === 'new' || $action === 'edit'): ?>
  <!-- This div groups related page content using the “panel” layout style. -->
  <div class="panel">
    <!-- This div groups related page content using the “panel-title” layout style. -->
    <div class="panel-title">
      <!-- This div groups related HTML content so the page structure is easier to manage. -->
      <div><small class="text-muted">Content manager</small>
        <h2><?= $edit ? 'Edit article' : 'Create article' ?></h2>
      </div>
      <a class="btn btn-outline-light rounded-pill px-4 btn-ripple-expand" href="<?= url('admin/blogs.php') ?>">
        <span class="btn-ripple-circle"></span>
        <span class="btn-ripple-label"><i class="bi bi-arrow-left me-1"></i>Back</span>
      </a>
    </div>

    <!-- This form collects user input and submits this form using the POST method. -->
    <form method="post" enctype="multipart/form-data" class="row g-3" data-loading-form>
      <?= csrfField() ?>
      <input type="hidden" name="mode" value="save">
      <input type="hidden" name="post_id" value="<?= (int) ($edit['post_id'] ?? 0) ?>">

      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-8"><label class="form-label">Title</label><input class="form-control" name="title" maxlength="180" required value="<?= e($edit['title'] ?? '') ?>"></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-4"><label class="form-label">Category</label><input class="form-control" name="category" maxlength="80" value="<?= e($edit['category'] ?? '') ?>"></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-12"><label class="form-label">Short excerpt</label><textarea class="form-control" name="excerpt" rows="3" maxlength="300" required><?= e($edit['excerpt'] ?? '') ?></textarea></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-12"><label class="form-label">Article content</label><textarea class="form-control" name="body" rows="12" required><?= e($edit['body'] ?? '') ?></textarea></div>

      <?php if ($hasImage): ?>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-md-6">
          <label class="form-label">Cover image</label>
          <input class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp">
          <small class="form-text text-muted">JPG, PNG or WebP · maximum 3 MB.</small>
        </div>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-md-3">
          <?php if (!empty($edit['image_path'])): ?>
            <img class="blog-admin-preview" src="<?= url($edit['image_path']) ?>" alt="Current blog cover">
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['Draft', 'Published'] as $status): ?><option <?= ($edit['status'] ?? 'Draft') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-4"><label class="form-label">Publish date/time</label><input class="form-control" type="datetime-local" name="published_at" value="<?= !empty($edit['published_at']) ? e(date('Y-m-d\TH:i', strtotime($edit['published_at']))) : '' ?>"></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-12"><button class="btn btn-outline-light rounded-pill px-4 btn-ripple-expand" type="submit"><span class="btn-ripple-circle"></span><span class="btn-ripple-label">Save article</span></button></div>
    </form>
  </div>
<?php else: ?>
  <!-- This div groups related page content using the “panel” layout style. -->
  <div class="panel">
    <!-- This div groups related page content using the “panel-title” layout style. -->
    <div class="panel-title">
      <!-- This div groups related HTML content so the page structure is easier to manage. -->
      <div><small class="text-muted">Blog CMS</small>
        <h2>Articles</h2>
      </div>
      <a class="btn btn-outline-light rounded-pill px-4 btn-ripple-expand" href="<?= url('admin/blogs.php?action=new') ?>">
        <span class="btn-ripple-circle"></span>
        <span class="btn-ripple-label"><i class="bi bi-plus-lg me-1"></i>New article</span>
      </a>
    </div>

    <!-- This div allows this table to scroll safely on smaller screens. -->
    <div class="table-responsive">
      <!-- This table displays database records in rows and columns for easy reading. -->
      <table class="table table-modern align-middle">
        <thead>
          <tr>
            <th>Article</th>
            <th>Category</th>
            <th>Status</th>
            <th>Publish date</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($posts as $post): ?>
            <tr>
              <td>
                <!-- This div uses flexbox to align the child elements in this area. -->
                <div class="d-flex align-items-center gap-3">
                  <?php if ($post['image_path']): ?><img class="blog-table-thumb" src="<?= url($post['image_path']) ?>" alt=""><?php else: ?><!-- This div groups related page content using the “blog-table-thumb blog-thumb-placeholder” layout style. -->
                    <div class="blog-table-thumb blog-thumb-placeholder"><i class="bi bi-image"></i></div><?php endif; ?>
                  <!-- This div groups related HTML content so the page structure is easier to manage. -->
                  <div><strong><?= e($post['title']) ?></strong><small class="d-block text-muted text-truncate" style="max-width:420px"><?= e($post['excerpt']) ?></small></div>
                </div>
              </td>
              <td><?= e($post['category']) ?></td>
              <td><?= statusBadge($post['status']) ?></td>
              <td><?= shortDate($post['published_at']) ?></td>
              <td class="text-end">
                <!-- This div groups related page content using the “d-inline-flex gap-1” layout style. -->
                <div class="d-inline-flex gap-1">
                  <?php if ($post['status'] === 'Published'): ?><a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= url('blog-detail.php?slug=' . urlencode($post['slug'])) ?>" target="_blank">View</a><?php endif; ?>
                  <a class="btn btn-sm btn-outline-secondary rounded-pill" href="?action=edit&id=<?= (int) $post['post_id'] ?>">Edit</a>
                  <!-- This form collects user input and submits this form using the POST method. -->
                  <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="mode" value="delete"><input type="hidden" name="post_id" value="<?= (int) $post['post_id'] ?>">
                    <button class="btn btn-sm btn-outline-danger rounded-pill" data-confirm="Delete this blog article and its uploaded cover image?">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$posts): ?><tr>
              <td colspan="5" class="text-center text-muted py-5">No blog posts yet.</td>
            </tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>