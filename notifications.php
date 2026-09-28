<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pageTitle = 'Notifications';
//  Run this block only when the form is submitted with POST.
if (isPost()) {
  verifyCsrf();
  $mode = $_POST['mode'] ?? 'all';
  if ($mode === 'one') {
    $notificationId = (int) ($_POST['notification_id'] ?? 0);
    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?')->execute([$notificationId, currentUser()['id']]);
  } else {
    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([currentUser()['id']]);
    flash('success', 'Notifications marked as read.');
  }
  //  Redirect the browser after this action to avoid repeating the same request.
  redirect('notifications.php');
}

$rows = all($pdo, 'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_date DESC, notification_id DESC', [currentUser()['id']]);
$unread = count(array_filter($rows, fn($row) => !(bool) $row['is_read']));

//  Load the shared file needed before this page continues.
require __DIR__ . '/includes/dashboard_header.php';
?>

<!-- This div groups related page content using the “panel” layout style. -->
<div class="panel">
  <!-- This div groups related page content using the “panel-title” layout style. -->
  <div class="panel-title">
    <!-- This div groups related HTML content so the page structure is easier to manage. -->
    <div><small class="text-muted">Updates & replies</small>
      <h2>Notifications</h2>
    </div>
    <!-- This div uses flexbox to align the child elements in this area. -->
    <div class="d-flex align-items-center gap-2">
      <?php if ($unread): ?><span class="notification-page-dot" title="New notifications"></span><?php endif; ?>
      <!-- This form collects user input and submits this form using the POST method. -->
      <form method="post"><?= csrfField() ?><input type="hidden" name="mode" value="all"><button class="btn btn-sm btn-outline-secondary rounded-pill">Mark all as read</button></form>
    </div>
  </div>

  <!-- This div groups items in a grid-style layout. -->
  <div class="d-grid gap-2">
    <?php foreach ($rows as $notification): ?>
      <article class="notification-card <?= !$notification['is_read'] ? 'notification-unread' : '' ?>">
        <!-- This div shows the notification icon and unread state. -->
        <div class="notification-icon <?= !$notification['is_read'] ? 'notification-icon-new' : '' ?>"><i class="bi <?= $notification['type'] === 'Contact' ? 'bi-chat-left-text' : 'bi-bell' ?>"></i></div>
        <!-- This div creates a Bootstrap grid row for responsive columns. -->
        <div class="flex-grow-1">
          <!-- This div uses flexbox to align the child elements in this area. -->
          <div class="d-flex flex-wrap justify-content-between gap-2"><strong><?= e($notification['title']) ?></strong><small class="text-muted"><?= date('d M H:i', strtotime($notification['created_date'])) ?></small></div>
          <p class="mb-0 mt-1 text-muted"><?= nl2br(e($notification['message'])) ?></p>
        </div>

      </article>
    <?php endforeach; ?>
    <?php if (!$rows): ?><!-- This div shows one summary value and its label. -->
      <div class="empty-state"><i class="bi bi-bell"></i>No notifications.</div><?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/dashboard_footer.php'; ?>