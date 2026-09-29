document.addEventListener('DOMContentLoaded', function () {

  const isMobile = () => window.innerWidth <= 900;

  // ── Mobile menu ──
  const navLinks     = document.getElementById('kcNavLinks');
  const mobileToggle = document.getElementById('kcMobileToggle');
  const navbar       = document.querySelector('.kc-main-nav');

  mobileToggle?.addEventListener('click', function () {
    const isOpen = navLinks.classList.toggle('kc-mobile-open');
    mobileToggle.textContent = isOpen ? '✕' : '☰';
    mobileToggle.setAttribute('aria-expanded', String(isOpen));
    if (isOpen && navbar) {
      navLinks.style.top = navbar.getBoundingClientRect().bottom + 'px';
    }
  });

  // ── All dropdowns — querySelectorAll so all work ──
  const allDropdowns = document.querySelectorAll('.kc-explore-li');

  allDropdowns.forEach(function (li) {
    const btn = li.querySelector('.kc-nav-btn, .kc-login-trigger');

    // Desktop: hover open/close
    li.addEventListener('mouseenter', () => {
      if (!isMobile()) {
        allDropdowns.forEach(other => { if (other !== li) other.classList.remove('open'); });
        li.classList.add('open');
      }
    });
    li.addEventListener('mouseleave', () => {
      if (!isMobile()) li.classList.remove('open');
    });

    // Mobile: click toggle
    btn?.addEventListener('click', (e) => {
      if (isMobile()) {
        e.stopPropagation();
        const wasOpen = li.classList.contains('open');
        // Close all first
        allDropdowns.forEach(other => other.classList.remove('open'));
        // Toggle current
        if (!wasOpen) li.classList.add('open');
        btn.setAttribute('aria-expanded', String(!wasOpen));
      }
    });
  });

  // Outside click — close all dropdowns
  document.addEventListener('click', (e) => {
    allDropdowns.forEach(li => {
      if (!li.contains(e.target)) li.classList.remove('open');
    });
  });

  // ── Banner close ──
  document.querySelector('.kc-banner-close')?.addEventListener('click', () => {
    document.getElementById('kcAnnouncementBar')?.remove();
  });

  // ── Copy coupon (code comes from site settings) ──
  const couponBadge = document.querySelector('.kc-coupon-badge');
  couponBadge?.addEventListener('click', function () {
    navigator.clipboard?.writeText(couponBadge.dataset.copy || couponBadge.textContent).catch(() => {});
    const orig = couponBadge.textContent;
    couponBadge.textContent = '✓ ' + (couponBadge.dataset.copied || 'Copied!');
    setTimeout(() => (couponBadge.textContent = orig), 1600);
  });

  // ── Light / dark theme (initial value is set inline in <head> before paint) ──
  document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const root = document.documentElement;
      const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('kcti-theme', next); } catch (e) {}
    });
  });

});
