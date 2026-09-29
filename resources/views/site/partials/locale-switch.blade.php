<div class="kc-locale-switch" role="group" aria-label="{{ __('site.nav.language') }}">
  <a href="{{ route('site.locale', 'en') }}" class="{{ app()->getLocale() === 'en' ? 'is-active' : '' }}" lang="en" hreflang="en">EN</a>
  <span aria-hidden="true">|</span>
  <a href="{{ route('site.locale', 'hi') }}" class="{{ app()->getLocale() === 'hi' ? 'is-active' : '' }}" lang="hi" hreflang="hi">हिं</a>
</div>
