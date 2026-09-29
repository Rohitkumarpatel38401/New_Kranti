{{-- Login menu shared by the desktop and mobile navbars --}}
<button type="button" class="kc-btn-login kc-login-trigger" aria-haspopup="true" aria-expanded="false">
  {{ __('site.nav.login') }} @include('site.partials.chevron')
</button>
<div class="kc-dropdown kc-login-dropdown" role="menu">
  <a href="{{ url('/student/login') }}" class="kc-dd-item" role="menuitem">
    <div class="kc-dd-row">
      <svg class="kc-dd-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M4 10.5L12 6l8 4.5-8 4.5-8-4.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
        <path d="M7 13v3.2c0 1 2.2 2.3 5 2.3s5-1.3 5-2.3V13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
      </svg>
      <span class="kc-dd-title">{{ __('site.nav.student_login') }}</span>
    </div>
    <div class="kc-dd-desc">{{ __('site.nav.student_login_desc') }}</div>
  </a>
  <div class="kc-dd-divider"></div>
  <a href="{{ url('/institute/login') }}" class="kc-dd-item" role="menuitem">
    <div class="kc-dd-row">
      <svg class="kc-dd-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M4 20h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        <path d="M6 20V8l6-3 6 3v12" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
        <path d="M9 11h.01M12 11h.01M15 11h.01M9 15h.01M12 15h.01M15 15h.01" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
      </svg>
      <span class="kc-dd-title">{{ __('site.nav.institute_login') }}</span>
    </div>
    <div class="kc-dd-desc">{{ __('site.nav.institute_login_desc') }}</div>
  </a>
  <div class="kc-dd-divider"></div>
  <a href="{{ url('/admin/login') }}" class="kc-dd-item" role="menuitem">
    <div class="kc-dd-row">
      <svg class="kc-dd-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M12 3.5l7 3v5c0 4.4-2.8 7.7-7 9-4.2-1.3-7-4.6-7-9v-5l7-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
        <path d="M9.5 12l1.8 1.8 3.7-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span class="kc-dd-title">{{ __('site.nav.admin_login') }}</span>
    </div>
    <div class="kc-dd-desc">{{ __('site.nav.admin_login_desc') }}</div>
  </a>
</div>
