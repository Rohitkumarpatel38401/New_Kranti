{{--
  Sticky right column for form and detail pages.
  $help (bool, default true): the contact card (hidden on the contact page, which already shows contact details).
  $extra (view name, optional): a page-specific card shown first.
--}}
@php($help = $help ?? true)
@php($phone = $site->get('site.phone'))
@php($whatsapp = $site->whatsappUrl(__('site.sidebar.whatsapp_text')))

<aside class="kc-sidebar" aria-label="{{ __('site.sidebar.links_title') }}">
  @isset($extra)
    @include($extra)
  @endisset

  @if ($help && ($phone || $whatsapp || $site->get('site.email')))
    <div class="kc-side-card kc-side-help">
      <div class="kc-side-title">💬 {{ __('site.sidebar.help_title') }}</div>
      <p class="kc-side-sub">{{ __('site.sidebar.help_sub') }}</p>
      <div class="kc-side-actions">
        @if ($phone)
          <a class="kc-side-btn kc-side-btn--call" href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">📞 {{ __('site.sidebar.call') }}</a>
        @endif
        @if ($whatsapp)
          <a class="kc-side-btn kc-side-btn--wa" href="{{ $whatsapp }}" target="_blank" rel="noopener">🟢 {{ __('site.sidebar.whatsapp') }}</a>
        @endif
      </div>
      @if (filled($site->get('site.email')))
        <a class="kc-side-link" href="mailto:{{ $site->get('site.email') }}">✉️ {{ $site->get('site.email') }}</a>
      @endif
      @if (filled($site->get('site.address')))
        <div class="kc-side-address">📍 {!! nl2br(e($site->get('site.address'))) !!}</div>
      @endif
    </div>
  @endif

  <div class="kc-side-card">
    <div class="kc-side-title">📰 {{ __('site.sidebar.news_title') }}</div>
    @forelse ($sidebarNotices as $notice)
      <div class="kc-side-news">
        <div class="kc-side-date">
          <span>{{ $notice->published_at?->format('d') }}</span>
          <small>{{ $notice->published_at?->translatedFormat('M') }}</small>
        </div>
        <div class="kc-side-news-text">
          {{ $notice->localized('title') }}
          @if ($notice->badge)
            <span class="ms-news-badge ms-news-badge--{{ $notice->badge }}">{{ \Illuminate\Support\Str::title($notice->badge) }}</span>
          @endif
        </div>
      </div>
    @empty
      <p class="kc-side-sub">{{ __('site.sidebar.no_news') }}</p>
    @endforelse
  </div>

  <div class="kc-side-card">
    <div class="kc-side-title">⭐ {{ __('site.sidebar.why_title') }}</div>
    <ul class="kc-side-list">
      @foreach (__('site.sidebar.why') as $point)
        <li><span aria-hidden="true">{{ $point['icon'] }}</span> {{ $point['text'] }}</li>
      @endforeach
    </ul>
  </div>

  <div class="kc-side-card">
    <div class="kc-side-title">🔗 {{ __('site.sidebar.links_title') }}</div>
    <div class="kc-side-chips">
      <a href="{{ route('site.courses.index') }}">{{ __('site.nav.courses') }}</a>
      <a href="{{ route('site.students.registration') }}">{{ __('site.nav.new_admission') }}</a>
      <a href="{{ route('site.students.verification') }}">{{ __('site.nav.verify') }}</a>
      <a href="{{ route('site.download') }}">{{ __('site.nav.download') }}</a>
      <a href="{{ route('site.franchise.info') }}">{{ __('site.nav.franchise') }}</a>
      <a href="{{ route('site.contact') }}">{{ __('site.nav.contact') }}</a>
    </div>
  </div>
</aside>
