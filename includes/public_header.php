<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/functions.php';
$pageTitle = $pageTitle ?? APP_NAME;
$flashes = pullFlashes();
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <meta name="description" content="PowerFit brings memberships, personal coaching, nutrition guidance and progress tracking together in one modern gym experience.">
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= url('assets/css/style.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet">
  <link href="<?= url('assets/css/auth.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/auth.css') ?>" rel="stylesheet">
</head>

<body class="public-body" data-app-url="<?= e(url()) ?>">
  <!-- This nav contains navigation links that help the user move between pages. -->
  <nav class="navbar navbar-expand-lg navbar-dark fixed-top power-nav" data-bs-theme="dark">
    <!-- This div keeps this section content aligned inside the main page width. -->
    <div class="container py-2">
      <a class="navbar-brand brand-lockup" href="<?= url('index.php') ?>">
        <span>POWERFIT</span>
      </a>
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
      <!-- This div groups the main website navigation content. -->
      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav mx-auto gap-lg-2">
          <li class="nav-item"><a class="nav-link" href="<?= url('index.php') ?>">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= url('about.php') ?>">About</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= url('packages.php') ?>">Packages</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= url('blog.php') ?>">Blog</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= url('contact.php') ?>">Contact</a></li>
        </ul>
        <!-- This div uses flexbox to align the child elements in this area. -->
        <div class="d-flex gap-2 align-items-center">
          <?php if (isLoggedIn()): ?>
            <a class="btn btn-outline-light rounded-pill px-4 btn-ripple-expand" href="<?= url(roleDashboard(currentUser()['role'])) ?>">
              <span class="btn-ripple-circle"></span>
              <span class="btn-ripple-label">Dashboard</span>
            </a>
          <?php else: ?>
            <a class="btn btn-power rounded-pill px-4 btn-ripple-expand" href="<?= url('login.php') ?>" data-bs-toggle="modal" data-bs-target="#authModal">
              <span class="btn-ripple-circle"></span>
              <span class="btn-ripple-label">Sign in <i class="bi bi-arrow-up-right ms-2"></i></span>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>
  <?php foreach ($flashes as $f): ?>
    <!-- This div shows a feedback message such as success, warning or error. -->
    <div class="toast-like alert alert-gradient alert-dismissible fade show" role="alert">
      <?= e($f['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endforeach; ?>