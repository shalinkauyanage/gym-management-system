<?php

$role = currentUser()['role']; ?>
<!-- This nav contains navigation links that help the user move between pages. -->
<nav class="sidebar-nav">
  <?php if ($role === 'Admin'): ?>
    <a class="<?= navActive('/admin/dashboard') ?>" href="<?= url('admin/dashboard.php') ?>"><i class="bi bi-grid-1x2"></i>Overview</a>
    <a class="<?= navActive('/admin/members') ?>" href="<?= url('admin/members.php') ?>"><i class="bi bi-people"></i>Members</a>
    <a class="<?= navActive('/admin/packages') ?>" href="<?= url('admin/packages.php') ?>"><i class="bi bi-box-seam"></i>Packages</a>
    <a class="<?= navActive('/admin/payments') ?>" href="<?= url('admin/payments.php') ?>"><i class="bi bi-credit-card"></i>Payments</a>
    <a class="<?= navActive('/admin/assignments') ?>" href="<?= url('admin/assignments.php') ?>"><i class="bi bi-diagram-3"></i>Coach assignments</a>
    <a class="<?= navActive('/admin/staff') ?>" href="<?= url('admin/staff.php') ?>"><i class="bi bi-person-badge"></i>Staff</a>
    <a class="<?= navActive('/admin/finances') ?>" href="<?= url('admin/finances.php') ?>"><i class="bi bi-cash-stack"></i>Finances</a>
    <a class="<?= navActive('/admin/ratings') ?>" href="<?= url('admin/ratings.php') ?>"><i class="bi bi-star"></i>Ratings</a>
    <a class="<?= navActive('/admin/blogs') ?>" href="<?= url('admin/blogs.php') ?>"><i class="bi bi-journal-text"></i>Blogs</a>
    <a class="<?= navActive('/admin/reports') ?>" href="<?= url('admin/reports.php') ?>"><i class="bi bi-bar-chart"></i>Reports</a>
    <a class="<?= navActive('/admin/contact_messages') ?>" href="<?= url('admin/contact_messages.php') ?>"><i class="bi bi-chat-left-text"></i>Contact messages</a>
  <?php elseif (isCoachRole($role)): ?>
    <a class="<?= navActive('/coach/dashboard') ?>" href="<?= url('coach/dashboard.php') ?>"><i class="bi bi-grid-1x2"></i>Overview</a>
    <a class="<?= navActive('/coach/members') ?>" href="<?= url('coach/members.php') ?>"><i class="bi bi-people"></i>Members</a>
    <a class="<?= navActive('/coach/workouts') ?>" href="<?= url('coach/workouts.php') ?>"><i class="bi bi-clipboard2-pulse"></i>Workout plans</a>
    <a class="<?= navActive('/coach/meals') ?>" href="<?= url('coach/meals.php') ?>"><i class="bi bi-egg-fried"></i>Meal plans</a>
    <a class="<?= navActive('/coach/progress_reports') ?>" href="<?= url('coach/progress_reports.php') ?>"><i class="bi bi-clipboard-data"></i>Progress reports</a>
  <?php else: ?>
    <a class="<?= navActive('/member/dashboard') ?>" href="<?= url('member/dashboard.php') ?>"><i class="bi bi-grid-1x2"></i>Overview</a>
    <a class="<?= navActive('/member/workout') ?>" href="<?= url('member/workout.php') ?>"><i class="bi bi-activity"></i>My workout</a>
    <a class="<?= navActive('/member/meals') ?>" href="<?= url('member/meals.php') ?>"><i class="bi bi-egg-fried"></i>My meals</a>
    <a class="<?= navActive('/member/progress') ?>" href="<?= url('member/progress.php') ?>"><i class="bi bi-graph-up-arrow"></i>Progress</a>
    <a class="<?= navActive('/member/payments') ?>" href="<?= url('member/payments.php') ?>"><i class="bi bi-receipt"></i>Payments</a>
    <a class="<?= navActive('/member/rating') ?>" href="<?= url('member/rating.php') ?>"><i class="bi bi-star"></i>Rate coach</a>
  <?php endif; ?>
  <a class="<?= navActive('/notifications.php') ?>" href="<?= url('notifications.php') ?>"><span class="sidebar-notification-icon"><i class="bi bi-bell"></i><?php if (($unreadNotifications ?? 0) > 0): ?><span class="sidebar-notification-dot"></span><?php endif; ?></span>Notifications</a>
</nav>