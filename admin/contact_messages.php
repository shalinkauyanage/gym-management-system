<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Admin');

$pageTitle = 'Contact Messages';
$hasReplyFields = tableExists($pdo, 'contact_messages') && columnExists($pdo, 'contact_messages', 'reply_text');

// POST handling: only run this block when a form has been submitted.
//  Run this block only when the form is submitted with POST.
if (isPost()) {
  //  reject forged or expired form submissions before processing input.
  //  Check the CSRF token before saving, updating or deleting data.
  verifyCsrf();
  $messageId = (int) ($_POST['message_id'] ?? 0);
  $reply = trim($_POST['reply_text'] ?? '');
  $message = one($pdo, 'SELECT * FROM contact_messages WHERE message_id = ?', [$messageId]);

  if (!$message || $reply === '') {
    //  Save a one-time message so the next page can tell the user what happened.
    flash('danger', 'Select a valid message and write a reply.');
    redirect('admin/contact_messages.php');
  }
  if (!$hasReplyFields) {
    //  Save a one-time message so the next page can tell the user what happened.
    flash('warning', 'Import the final database patch to enable replies.');
    redirect('admin/contact_messages.php');
  }

  //  Prepare and run this SQL statement using PDO.
  $pdo->prepare(
    "UPDATE contact_messages SET reply_text = ?, replied_by = ?, replied_at = NOW(), status = 'Replied' WHERE message_id = ?",
  )->execute([$reply, currentUser()['id'], $messageId]);

  if (!empty($message['user_id'])) {
    notifyUser(
      $pdo,
      (int) $message['user_id'],
      'Support reply: ' . ($message['subject'] ?: 'Your message'),
      $reply,
      'Contact',
    );
  }

  //  Save a one-time message so the next page can tell the user what happened.
  flash('success', 'Reply saved and sent to the user notification inbox.');
  redirect('admin/contact_messages.php#message-' . $messageId);
}

$hasMessageUser = tableExists($pdo, 'contact_messages') && columnExists($pdo, 'contact_messages', 'user_id');
if (tableExists($pdo, 'contact_messages')) {
  $rows = $hasMessageUser
    ? all($pdo, 'SELECT cm.*, u.username FROM contact_messages cm LEFT JOIN users u ON u.user_id = cm.user_id ORDER BY cm.created_at DESC, cm.message_id DESC')
    : all($pdo, 'SELECT cm.*, NULL AS username FROM contact_messages cm ORDER BY cm.created_at DESC, cm.message_id DESC');
} else {
  $rows = [];
}

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<!-- This div groups related page content using the “panel” layout style. -->
<div class="panel">
  <!-- This div groups related page content using the “panel-title” layout style. -->
  <div class="panel-title"><!-- This div groups related HTML content on this page. -->
    <div><small class="text-muted">Account-linked support</small>
      <h2>Contact messages</h2>
    </div><span class="badge text-bg-light rounded-pill"><?= count($rows) ?> messages</span>
  </div>

  <?php if (!$hasReplyFields): ?><!-- This div shows a feedback message such as success, warning or error. -->
    <div class="alert alert-warning">Import <code>database/patch_final_features.sql</code> to enable account-linked replies.</div><?php endif; ?>

  <!-- This div groups items in a grid-style layout. -->
  <div class="d-grid gap-3">
    <?php foreach ($rows as $row): ?>
      <article class="contact-message-card" id="message-<?= (int) $row['message_id'] ?>">
        <!-- This div uses flexbox to align the child elements in this area. -->
        <div class="d-flex flex-wrap justify-content-between gap-3">
          <!-- This div groups related HTML content so the page structure is easier to manage. -->
          <div><strong><?= e($row['name']) ?></strong><small class="d-block text-muted"><?= e($row['email']) ?> · <?= e($row['username'] ?? 'Legacy message') ?></small></div>
          <!-- This div groups related page content using the “text-end” layout style. -->
          <div class="text-end"><?= isset($row['status']) ? statusBadge($row['status']) : '' ?><small class="d-block text-muted mt-1"><?= date('d M Y H:i', strtotime($row['created_at'])) ?></small></div>
        </div>
        <h3 class="h6 mt-3 mb-2"><?= e($row['subject'] ?: 'No subject') ?></h3>
        <p class="mb-3"><?= nl2br(e($row['message'])) ?></p>

        <?php if (!empty($row['reply_text'])): ?>
          <!-- This div groups related page content using the “admin-reply-box” layout style. -->
          <div class="admin-reply-box"><small class="text-uppercase fw-bold">Admin reply</small>
            <p class="mb-0 mt-2"><?= nl2br(e($row['reply_text'])) ?></p><small class="text-muted d-block mt-2"><?= !empty($row['replied_at']) ? date('d M Y H:i', strtotime($row['replied_at'])) : '' ?></small>
          </div>
        <?php endif; ?>

        <?php if ($hasReplyFields): ?>
          <!-- This form collects user input and submits this form using the POST method. -->
          <form method="post" class="mt-3">
            <?= csrfField() ?><input type="hidden" name="message_id" value="<?= (int) $row['message_id'] ?>">
            <label class="form-label"><?= !empty($row['reply_text']) ? 'Update reply' : 'Reply to user' ?></label>
            <textarea class="form-control" name="reply_text" rows="3" maxlength="500" required><?= e($row['reply_text'] ?? '') ?></textarea>
            <button class="btn btn-danger rounded-pill px-4 mt-2"><i class="bi bi-reply me-2"></i><?= !empty($row['reply_text']) ? 'Update & notify' : 'Send reply' ?></button>
          </form>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
    <?php if (!$rows): ?><!-- This div shows one summary value and its label. -->
      <div class="empty-state"><i class="bi bi-chat-left-text"></i>No contact messages yet.</div><?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>