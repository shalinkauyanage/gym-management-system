<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/functions.php';
requireLogin();
$pageTitle = $pageTitle ?? 'Dashboard';
$user = currentUser();
$flashes = pullFlashes();
$topNav = dashboardNavItems($user['role']);
$unreadNotifications = tableExists($pdo, 'notifications')
  ? (int) scalar($pdo, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$user['id']])
  : 0;
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | PowerFit</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= url('assets/css/style.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet">
  <link href="<?= url('assets/css/auth.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/auth.css') ?>" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
  <script>
    if (window.Chart) {
      Chart.defaults.color = '#414141';
      Chart.defaults.borderColor = 'rgba(65,65,65,.12)';
      Chart.defaults.font.family = "Manrope, system-ui, sans-serif";
      Chart.defaults.plugins.tooltip.backgroundColor = '#0E0E0E';
      Chart.defaults.plugins.tooltip.titleColor = '#FFFFFF';
      Chart.defaults.plugins.tooltip.bodyColor = '#EDEDED';
      Chart.defaults.plugins.tooltip.padding = 12;
      Chart.defaults.plugins.tooltip.cornerRadius = 10;
    }
  </script>
</head>

<body class="dashboard-body" data-app-url="<?= e(url()) ?>">
  <!-- This div groups dashboard content for the current user role. -->
  <div class="dashboard-shell">
    <aside class="sidebar" id="sidebar">
      <a class="brand-lockup text-decoration-none text-white mb-4" href="<?= url(roleDashboard($user['role'])) ?>">
        <span>POWERFIT</span>
      </a>

      <?php include __DIR__ . '/sidebar.php'; ?>

      <?php
      $roleIcon = match (true) {
        $user['role'] === 'Admin' => 'assets/images/admin-ic.webp',
        isCoachRole($user['role']) => 'assets/images/trainer-ic.webp',
        default => 'assets/images/member-ic.webp',
      };
      ?>
      <!-- This div holds the dashboard side navigation links. -->
      <div class="sidebar-user mt-auto">
        <!-- This div holds a small status or category label. -->
        <div class="avatar-sm avatar-role-badge">
          <img src="<?= url($roleIcon) ?>" alt="<?= e(roleLabel($user['role'])) ?>" class="avatar-role-icon">
        </div>
        <!-- This div creates a Bootstrap grid row for responsive columns. -->
        <div class="flex-grow-1 overflow-hidden">
          <strong><?= e($user['username']) ?></strong>
          <small><?= e(roleLabel($user['role'])) ?></small>
        </div>
        <a href="<?= url('logout.php') ?>" class="icon-btn" title="Logout">
          <i class="bi bi-box-arrow-right"></i>
        </a>
      </div>
    </aside>

    <main class="dashboard-main">
      <!-- This header starts the page header area, including branding and navigation. -->
      <header class="dashboard-topbar">
        <button class="btn icon-btn d-lg-none" id="sidebarToggle" type="button" aria-label="Open dashboard menu">
          <i class="bi bi-list"></i>
        </button>

        <!-- This div groups related HTML content so the page structure is easier to manage. -->
        <div>
          <h1 class="h4 mb-0"><?= e($pageTitle) ?></h1>
        </div>

        <!-- This div uses flexbox to align the child elements in this area. -->
        <div class="ms-auto d-flex align-items-center gap-2">
          <a class="icon-btn" href="<?= url('index.php') ?>" title="Public website">
            <i class="bi bi-globe2"></i>
          </a>
          <a class="icon-btn notification-bell <?= $unreadNotifications > 0 ? 'has-unread' : '' ?>" href="<?= url('notifications.php') ?>" title="Notifications">
            <i class="bi bi-bell"></i>
            <?php if ($unreadNotifications > 0): ?>
              <span class="notification-dot" aria-label="Unread notifications"></span>
            <?php endif; ?>
          </a>
        </div>
      </header>

      <!-- This nav contains navigation links that help the user move between pages. -->
      <nav class="dashboard-nav-strip" aria-label="Dashboard navigation">
        <?php foreach ($topNav as $item): ?>
          <a
            class="<?= navActive('/' . ltrim($item[1], '/')) ?>"
            href="<?= url($item[1]) ?>">
            <i class="bi <?= e($item[2]) ?>"></i>
            <span><?= e($item[0]) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>

      <!-- This div groups dashboard content for the current user role. -->
      <div class="dashboard-content">
        <?php foreach ($flashes as $flash): ?>
          <!-- This div shows one success, warning or error flash message to the dashboard user. -->
          <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
            <i class="bi bi-info-circle me-2"></i><?= e($flash['message']) ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endforeach; ?>