
//  This callback runs when this browser event happens.
document.addEventListener('DOMContentLoaded', () => {
  // --------------------------------------------------------------------------
  // 1. Local UI configuration
  // --------------------------------------------------------------------------
  // These values were previously stored in ui-config.json. Keeping them here
  // removes an unnecessary network request and makes the final project simpler.
  const uiConfig = {
    motion: {
      pauseLabel: 'Pause motion',
      playLabel: 'Resume motion',
      storageKey: 'powerfit-motion-paused',
    },
  };

  // --------------------------------------------------------------------------
  // 2. Dashboard mobile sidebar
  // --------------------------------------------------------------------------
  // The toggle button adds/removes the "open" class so CSS can show the sidebar
  // on smaller screens.
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  //  This callback runs when this browser event happens.
  sidebarToggle?.addEventListener('click', () => {
    sidebar?.classList.toggle('open');
  });

  // --------------------------------------------------------------------------
  // 3. Confirmation dialog for destructive actions
  // --------------------------------------------------------------------------
  // Any element with data-confirm gets a browser confirmation before continuing.
  //  This callback repeats the same UI task for every matching element.
  document.querySelectorAll('[data-confirm]').forEach((element) => {
    element.addEventListener('click', (event) => {
      const message = element.getAttribute('data-confirm') || 'Are you sure?';
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    });
  });

  // --------------------------------------------------------------------------
  // 4. Floating public navigation scroll effect
  // --------------------------------------------------------------------------
  const nav = document.querySelector('.power-nav');

  // This small helper updates the navigation style depending on scroll position.
  //  This JavaScript function handles one small front-end behaviour.
  const updateNav = () => {
    nav?.classList.toggle('scrolled', window.scrollY > 30);
  };

  updateNav();
  window.addEventListener('scroll', updateNav, { passive: true });

  // --------------------------------------------------------------------------
  // 5. Global motion / hero-video pause control
  // --------------------------------------------------------------------------
  const heroVideo = document.querySelector('.hero-video-media');
  const motionButtons = document.querySelectorAll('[data-motion-toggle], [data-video-toggle]');
  const storageKey = uiConfig.motion.storageKey;

  // Read the visitor's previous preference from localStorage.
  let motionPaused = window.localStorage.getItem(storageKey) === 'true';

  // Keeps the HTML class, hero video and button labels in the same state.
  //  This JavaScript function handles one small front-end behaviour.
  const syncMotion = () => {
    document.documentElement.classList.toggle('motion-paused', motionPaused);
    window.localStorage.setItem(storageKey, String(motionPaused));

    // Pause/resume the hero video when one exists on the current page.
    if (heroVideo instanceof HTMLVideoElement) {
      if (motionPaused) {
        heroVideo.pause();
      } else {
        //  This JavaScript function handles one small front-end behaviour.
        heroVideo.play().catch(() => {
          // Some browsers block autoplay until the visitor interacts with page.
        });
      }
    }

    // Update every motion button so icon/text matches the current state.
    //  This callback repeats the same UI task for every matching element.
    motionButtons.forEach((button) => {
      const icon = button.querySelector('i');
      const label = button.querySelector('span');
      const text = motionPaused ? uiConfig.motion.playLabel : uiConfig.motion.pauseLabel;

      button.setAttribute('aria-label', text);
      button.setAttribute('title', text);

      if (icon) {
        icon.className = motionPaused ? 'bi bi-play-fill' : 'bi bi-pause-fill';
      }

      if (label) {
        label.textContent = text;
      }
    });
  };

  // Clicking any motion button flips the saved preference.
  //  This callback repeats the same UI task for every matching element.
  motionButtons.forEach((button) => {
    button.addEventListener('click', () => {
      motionPaused = !motionPaused;
      syncMotion();
    });
  });

  // Apply the saved state immediately when the page loads.
  syncMotion();

  // --------------------------------------------------------------------------
  // 6. Animated counters
  // --------------------------------------------------------------------------
  //  This callback repeats the same UI task for every matching element.
  document.querySelectorAll('[data-counter]').forEach((element) => {
    const target = Number(element.dataset.counter || 0);
    let current = 0;
    const step = Math.max(1, Math.ceil(target / 40));

    // Repeatedly increase the displayed number until it reaches its target.
    //  This callback runs after the browser timer reaches the required time.
    const timer = window.setInterval(() => {
      if (motionPaused) {
        return;
      }

      current += step;
      if (current >= target) {
        current = target;
        window.clearInterval(timer);
      }

      element.textContent = current.toLocaleString();
    }, 28);
  });

  // --------------------------------------------------------------------------
  // 7. Form loading state
  // --------------------------------------------------------------------------
  // Prevents repeated clicks by visually changing a submit button to Processing.
  //  This callback repeats the same UI task for every matching element.
  document.querySelectorAll('form[data-loading-form]').forEach((form) => {
    form.addEventListener('submit', () => {
      const button = form.querySelector('[type="submit"]');
      if (!button) {
        return;
      }

      button.classList.add('is-loading', 'btn-loading');
      button.dataset.original = button.innerHTML;
      button.innerHTML = 'Processing...';
    });
  });

  // --------------------------------------------------------------------------
  // 8. Reusable typing animation
  // --------------------------------------------------------------------------
  //  This callback repeats the same UI task for every matching element.
  document.querySelectorAll('[data-typing]').forEach((element) => {
    const phrases = (element.dataset.typing || '').split('|').filter(Boolean);
    if (!phrases.length) {
      return;
    }

    let phraseIndex = 0;
    let characterIndex = 0;
    let deleting = false;

    // Shows one character at a time, waits, deletes it, then moves to next phrase.
    //  This JavaScript function handles one small front-end behaviour.
    const renderTyping = () => {
      if (motionPaused) {
        window.setTimeout(renderTyping, 180);
        return;
      }

      const phrase = phrases[phraseIndex];
      element.innerHTML = `${phrase.slice(0, characterIndex)}<span class="cursor"></span>`;

      if (!deleting && characterIndex < phrase.length) {
        characterIndex += 1;
        window.setTimeout(renderTyping, 55);
        return;
      }

      if (!deleting) {
        deleting = true;
        window.setTimeout(renderTyping, 1100);
        return;
      }

      if (characterIndex > 0) {
        characterIndex -= 1;
        window.setTimeout(renderTyping, 28);
        return;
      }

      deleting = false;
      phraseIndex = (phraseIndex + 1) % phrases.length;
      window.setTimeout(renderTyping, 240);
    };

    renderTyping();
  });

  // --------------------------------------------------------------------------
  // 9. Button hover / ripple interactions
  // --------------------------------------------------------------------------
  //  This callback repeats the same UI task for every matching element.
  document.querySelectorAll('.btn-ripple-expand').forEach((button) => {
    button.addEventListener('mouseenter', (event) => {
      const rect = button.getBoundingClientRect();
      const x = event.clientX - rect.left;
      const y = event.clientY - rect.top;
      const circle = button.querySelector('.btn-ripple-circle');

      if (circle) {
        circle.style.left = `${x}px`;
        circle.style.top = `${y}px`;
      }
    });
  });

  // Add temporary touch state so hover-style buttons also work on mobile.
  //  This callback repeats the same UI task for every matching element.
  document.querySelectorAll('.btn-interactive-hover').forEach((button) => {
    button.addEventListener('touchstart', () => {
      button.classList.add('is-touched');
    }, { passive: true });

    //  This callback runs when this browser event happens.
    button.addEventListener('touchend', () => {
      window.setTimeout(() => button.classList.remove('is-touched'), 350);
    }, { passive: true });
  });

  // --------------------------------------------------------------------------
  // 10. Password visibility toggle
  // --------------------------------------------------------------------------
  //  This callback runs when this browser event happens.
  document.addEventListener('click', (event) => {
    const toggleButton = event.target.closest('.btn-toggle-password, [data-password-toggle]');
    if (!toggleButton) {
      return;
    }

    event.preventDefault();
    const wrapper = toggleButton.closest('.password-input-wrap') || toggleButton.parentElement;
    const input = wrapper ? wrapper.querySelector('input') : null;
    if (!input) {
      return;
    }

    const wasPassword = input.type === 'password';
    input.type = wasPassword ? 'text' : 'password';

    const icon = toggleButton.querySelector('i');
    if (icon) {
      icon.className = wasPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
    }

    const label = wasPassword ? 'Hide password' : 'Show password';
    toggleButton.setAttribute('title', label);
    toggleButton.setAttribute('aria-label', label);
    toggleButton.setAttribute('aria-pressed', wasPassword ? 'true' : 'false');
  });

  // --------------------------------------------------------------------------
  // 11. Authentication & Security Modal Pane Switching
  // --------------------------------------------------------------------------
  const switchAuthPane = (target, modalElement) => {
    const modal = modalElement || document.getElementById('authModal');
    if (!modal) return;

    const panes = {
      signin: modal.querySelector('#authPaneSignin'),
      forgot: modal.querySelector('#authPaneForgot'),
      verify_email: modal.querySelector('#authPaneVerifyEmail'),
      change_password: modal.querySelector('#authPaneChangePassword'),
    };

    // Normalize target alias names
    let normalized = target;
    if (target === 'verify' || target === 'verify-email') normalized = 'verify_email';
    if (target === 'change' || target === 'change-password') normalized = 'change_password';
    if (target === 'reset' || target === 'reset-password') normalized = 'forgot';
    if (target === 'login') normalized = 'signin';

    // Hide all panes
    Object.values(panes).forEach((pane) => {
      if (pane) pane.style.display = 'none';
    });

    // Show target pane (default to signin if unknown)
    const activePane = panes[normalized] || panes.signin;
    if (activePane) {
      activePane.style.display = 'block';
    }

    // Toggle guest tabs bar visibility
    const tabsContainer = modal.querySelector('#authModalTabs');
    const tabs = modal.querySelectorAll('.auth-modal-tab');
    if (tabsContainer) {
      if (normalized === 'signin' || normalized === 'forgot') {
        tabsContainer.style.display = 'flex';
        tabs.forEach((tab) => tab.classList.toggle('active', tab.dataset.authTab === normalized));
      } else {
        tabsContainer.style.display = 'none';
      }
    }

    // Auto-focus relevant field
    window.setTimeout(() => {
      if (!activePane) return;
      const otpInput = activePane.querySelector('input.otp-input');
      const firstInput = activePane.querySelector('input:not([type="hidden"])');
      const passwordInput = activePane.querySelector('input[type="password"]');

      if (otpInput) {
        otpInput.focus();
      } else if (normalized === 'signin') {
        const identityInput = activePane.querySelector('input[name="identity"]');
        if (identityInput && identityInput.value.trim() !== '') {
          passwordInput?.focus();
        } else {
          identityInput?.focus();
        }
      } else if (firstInput) {
        firstInput.focus();
      }
    }, 200);
  };

  // Switch pane on clicking any [data-auth-tab] or [data-auth-switch]
  document.addEventListener('click', (event) => {
    const tabButton = event.target.closest('[data-auth-tab], [data-auth-switch]');
    if (!tabButton) return;

    const target = tabButton.dataset.authTab || tabButton.dataset.authSwitch;
    const modalElement = tabButton.closest('#authModal') || document.getElementById('authModal');
    if (modalElement && target) {
      event.preventDefault();
      switchAuthPane(target, modalElement);
      if (window.bootstrap && window.bootstrap.Modal) {
        const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
        modal.show();
      }
    }
  });

  // --------------------------------------------------------------------------
  // 12. Intercept all authentication links so they pop up on the same page
  // --------------------------------------------------------------------------
  document.addEventListener('click', (event) => {
    const authLink = event.target.closest(
      'a[href*="login.php"], a[href*="forgot-password.php"], a[href*="verify-email.php"], a[href*="change-password.php"]'
    );
    if (!authLink) return;

    // Preserve normal browser behaviour when opening in new tab/window using keys
    if (event.ctrlKey || event.metaKey || event.shiftKey) return;

    const modalElement = document.getElementById('authModal');
    if (!modalElement) return;

    event.preventDefault();

    const href = authLink.getAttribute('href') || '';
    let target = 'signin';
    if (href.includes('forgot-password.php')) {
      target = 'forgot';
    } else if (href.includes('verify-email.php')) {
      target = 'verify_email';
    } else if (href.includes('change-password.php')) {
      target = 'change_password';
    } else if (href.includes('login.php')) {
      target = 'signin';
    }

    switchAuthPane(target, modalElement);

    if (window.bootstrap && window.bootstrap.Modal) {
      const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
      modal.show();
    }
  });

  // --------------------------------------------------------------------------
  // 13. Auto-open authentication modal when requested by state or URL
  // --------------------------------------------------------------------------
  const authModalElement = document.getElementById('authModal');
  if (authModalElement && window.bootstrap && window.bootstrap.Modal) {
    const urlParams = new URLSearchParams(window.location.search);
    const hash = window.location.hash;
    const initialPaneAttr = authModalElement.dataset.initialPane;
    const autoOpenAttr = authModalElement.dataset.autoOpen === '1';

    let openPane = null;

    if (urlParams.has('login') || urlParams.get('auth') === 'signin' || hash === '#authModal' || hash === '#login') {
      openPane = 'signin';
    } else if (urlParams.get('auth') === 'forgot' || hash === '#forgot') {
      openPane = 'forgot';
    } else if (urlParams.get('auth') === 'verify_email' || urlParams.get('auth') === 'verify' || hash === '#verify') {
      openPane = 'verify_email';
    } else if (urlParams.get('auth') === 'change_password' || urlParams.get('auth') === 'change' || hash === '#changePassword') {
      openPane = 'change_password';
    } else if (autoOpenAttr && initialPaneAttr) {
      openPane = initialPaneAttr;
    }

    if (openPane) {
      switchAuthPane(openPane, authModalElement);
      const modal = window.bootstrap.Modal.getOrCreateInstance(authModalElement);
      modal.show();

      if (window.history && window.history.replaceState && (urlParams.has('login') || urlParams.has('auth'))) {
        const cleanUrl = window.location.pathname + (window.location.hash || '');
        window.history.replaceState({}, document.title, cleanUrl);
      }
    }
  }

  // --------------------------------------------------------------------------
  // 13b. Universal live password rules testing
  // --------------------------------------------------------------------------
  const setupPasswordRulesValidation = (inputId, rulesId) => {
    const input = document.getElementById(inputId);
    const box = document.getElementById(rulesId);
    if (!input || !box) return;

    const tests = {
      length: (val) => val.length >= 10,
      upper: (val) => /[A-Z]/.test(val),
      lower: (val) => /[a-z]/.test(val),
      number: (val) => /\d/.test(val),
      special: (val) => /[^A-Za-z0-9]/.test(val),
    };

    const refresh = () => {
      Object.entries(tests).forEach(([name, test]) => {
        const item = box.querySelector(`[data-rule="${name}"]`);
        if (!item) return;
        const ok = test(input.value);
        item.classList.toggle('is-valid', ok);
        const icon = item.querySelector('i');
        if (icon) icon.className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
      });
    };

    input.addEventListener('input', refresh);
    refresh();
  };

  setupPasswordRulesValidation('resetModalNewPassword', 'resetPasswordRules');
  setupPasswordRulesValidation('changeModalNewPassword', 'changePasswordRules');
  setupPasswordRulesValidation('resetNewPassword', 'passwordRules');
  setupPasswordRulesValidation('newPassword', 'passwordRules');

  // --------------------------------------------------------------------------
  // 14. Real-time package image upload preview
  // --------------------------------------------------------------------------
  const packageInput = document.getElementById('packageImageInput');
  const packagePreview = document.getElementById('packagePreviewImg');
  const packageEmptySpace = document.getElementById('packageEmptySpace');

  packageInput?.addEventListener('change', (event) => {
    const file = event.target.files?.[0];
    if (file && file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = (e) => {
        if (packagePreview) {
          packagePreview.src = e.target?.result;
          packagePreview.style.display = 'block';
        }
        if (packageEmptySpace) {
          packageEmptySpace.style.display = 'none';
        }
      };
      reader.readAsDataURL(file);
    }
  });
});
