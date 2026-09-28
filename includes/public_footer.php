<!-- This footer starts the page footer with supporting links and information. -->
<footer class="site-footer">
  <!-- This div groups related page content using the “footer-hero-banner” layout style. -->
  <div class="footer-hero-banner">
    <!-- This div creates a full-width Bootstrap layout container. -->
    <div class="container-fluid px-3 px-md-5 text-center">
      <img src="<?= url('assets/images/footer.webp') ?>" alt="PowerFit" class="footer-brand-img img-fluid" loading="lazy">
    </div>
  </div>
  <!-- This div keeps this section content aligned inside the main page width. -->
  <div class="container pb-5 pt-2">
    <!-- This div creates a Bootstrap grid row for responsive columns. -->
    <div class="row g-4 g-lg-5">
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-lg-4">
        <p class="text-white-50 pe-lg-5 mb-0">Membership, personal coaching, nutrition guidance and progress — brought together to help you stay consistent and move forward with confidence.</p>
      </div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-6 col-lg-2">
        <h6>Explore</h6>
        <a href="<?= url('about.php') ?>">About</a><a href="<?= url('packages.php') ?>">Packages</a><a href="<?= url('blog.php') ?>">Blog</a><a href="<?= url('contact.php') ?>">Contact</a><a href="<?= url('login.php') ?>" data-bs-toggle="modal" data-bs-target="#authModal">Login</a>
      </div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-6 col-lg-2">
        <h6>Your journey</h6>
        <span>Memberships</span><span>Personal coaching</span><span>Nutrition guidance</span><span>Progress tracking</span>
      </div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-lg-4">
        <h6>Need support?</h6>
        <p class="text-white-50">Questions about your membership, account or plans? Our team is ready to help.</p>
        <a class="btn btn-outline-light rounded-pill px-4 btn-ripple-expand" href="<?= url('contact.php') ?>">
          <span class="btn-ripple-circle"></span>
          <span class="btn-ripple-label">Contact PowerFit</span>
        </a>
      </div>
    </div>
    <!-- This div creates a Bootstrap grid row for responsive columns. -->
    <div class="footer-bottom d-flex flex-column flex-md-row justify-content-between gap-3 mt-5 pt-4">
      <span>© <?= date('Y') ?> PowerFit. All rights reserved.</span>
      <!-- This div uses flexbox to align the child elements in this area. -->
      <div class="d-flex gap-4"><a href="<?= url('terms.php') ?>">Terms & Conditions</a><a href="<?= url('privacy.php') ?>">Privacy Policy</a></div>
    </div>
  </div>
</footer>

<?php
/**
 * --------------------------------------------------------------------------
 * STUDENT VIVA EXPLANATION (simple English)
 * File: includes/public_footer.php
 * Main user/area: Shared system code
 * Simple purpose: This file handles the Public Footer part of PowerFit.
 * How to explain: PHP prepares/validates the data first, then the HTML shows
 * the result to the user. Database work is done with PDO prepared statements.
 * --------------------------------------------------------------------------
 */
require __DIR__ . '/auth_modal.php';
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/app.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body>

</html>