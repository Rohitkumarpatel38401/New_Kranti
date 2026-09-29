@php($announcement = $site->text('site.announcement'))
@php($coupon = $site->get('site.coupon'))

@if (filled($announcement))
  <div class="kc-announcement-bar" id="kcAnnouncementBar">
    {{ $announcement }}
    @if (filled($coupon))
      <button type="button" class="kc-coupon-badge" data-copy="{{ $coupon }}" data-copied="{{ __('site.nav.copied') }}">{{ $coupon }}</button>
    @endif
    <button type="button" class="kc-banner-close" aria-label="{{ __('site.nav.close') }}">✕</button>
  </div>
@endif

<nav class="kc-main-nav" aria-label="Main navigation">
  <div class="kc-nav-inner">

    <a href="{{ route('site.home') }}" class="kc-nav-logo" aria-label="{{ $site->get('site.name') }}">
      @include('site.partials.logo', ['gradId' => 'kcLogoGrad', 'from' => '#4f46e5', 'to' => '#7c3aed'])
      <span class="kc-logo-text">
        <span class="kc-logo-name">KRANTI</span><span class="kc-logo-sub"> COMPUTER</span>
      </span>
    </a>

    <ul class="kc-nav-links" id="kcNavLinks">
      <li><a href="{{ route('site.home') }}" class="kc-nav-link {{ request()->routeIs('site.home') ? 'active' : '' }}">{{ __('site.nav.home') }}</a></li>
      <li><a href="{{ route('site.courses.index') }}" class="kc-nav-link {{ request()->routeIs('site.courses.*') ? 'active' : '' }}">{{ __('site.nav.courses') }}</a></li>

      <li class="kc-explore-li">
        <button type="button" class="kc-nav-btn {{ request()->routeIs('site.students.*') ? 'active' : '' }}" aria-haspopup="true" aria-expanded="false">
          {{ __('site.nav.students') }} @include('site.partials.chevron')
        </button>
        <div class="kc-dropdown" role="menu">
          <a href="{{ route('site.students.registration') }}" class="kc-dd-item" role="menuitem">
            <div class="kc-dd-row">
              <span class="kc-dd-title">{{ __('site.nav.new_admission') }}</span>
              <span class="kc-badge kc-badge-new">{{ __('site.nav.open') }}</span>
            </div>
            <div class="kc-dd-desc">{{ __('site.nav.new_admission_desc') }}</div>
          </a>
          <div class="kc-dd-divider"></div>
          <a href="{{ route('site.students.verification') }}" class="kc-dd-item" role="menuitem">
            <div class="kc-dd-row"><span class="kc-dd-title">{{ __('site.nav.verify') }}</span></div>
            <div class="kc-dd-desc">{{ __('site.nav.verify_desc') }}</div>
          </a>
        </div>
      </li>

      <li><a href="{{ route('site.placement') }}" class="kc-nav-link {{ request()->routeIs('site.placement') ? 'active' : '' }}">{{ __('site.nav.placement') }}</a></li>
      <li><a href="{{ route('site.gallery') }}" class="kc-nav-link {{ request()->routeIs('site.gallery') ? 'active' : '' }}">{{ __('site.nav.gallery') }}</a></li>

      <li class="kc-explore-li">
        <button type="button" class="kc-nav-btn {{ request()->routeIs('site.franchise.*') ? 'active' : '' }}" aria-haspopup="true" aria-expanded="false">
          {{ __('site.nav.franchise') }} @include('site.partials.chevron')
        </button>
        <div class="kc-dropdown" role="menu">
          <a href="{{ route('site.franchise.info') }}" class="kc-dd-item" role="menuitem">
            <div class="kc-dd-row"><span class="kc-dd-title">{{ __('site.nav.franchise_info') }}</span></div>
            <div class="kc-dd-desc">{{ __('site.nav.franchise_info_desc') }}</div>
          </a>
          <div class="kc-dd-divider"></div>
          <a href="{{ route('site.franchise.apply') }}" class="kc-dd-item" role="menuitem">
            <div class="kc-dd-row">
              <span class="kc-dd-title">{{ __('site.nav.franchise_apply') }}</span>
              <span class="kc-badge kc-badge-popular">{{ __('site.nav.earning') }}</span>
            </div>
            <div class="kc-dd-desc">{{ __('site.nav.franchise_apply_desc') }}</div>
          </a>
        </div>
      </li>

      {{-- Mobile only --}}
      <li class="kc-mobile-login-li kc-mobile-tools">
        @include('site.partials.locale-switch')
        @include('site.partials.theme-toggle')
      </li>
      <li class="kc-mobile-login-li">
        <a href="{{ route('site.contact') }}" class="kc-btn-login kc-btn-outline">{{ __('site.nav.contact') }}</a>
      </li>
      <li class="kc-mobile-login-li kc-explore-li" style="margin-top: 6px;">
        @include('site.partials.login-menu')
      </li>
    </ul>

    <div class="kc-nav-right" style="gap: 10px;">
      @include('site.partials.locale-switch')
      @include('site.partials.theme-toggle')
      <a href="{{ route('site.contact') }}" class="kc-btn-login kc-btn-outline">{{ __('site.nav.contact') }}</a>
      <div class="kc-login-menu kc-explore-li">
        @include('site.partials.login-menu')
      </div>
    </div>

    <button type="button" class="kc-mobile-toggle" id="kcMobileToggle" aria-label="{{ __('site.nav.menu') }}" aria-expanded="false">☰</button>
  </div>
</nav>
