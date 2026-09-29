@php($phones = array_filter([$site->get('site.phone'), $site->get('site.phone_alt')]))
@php($socials = array_filter((array) $site->get('site.socials', [])))

<footer class="kc-footer">
  <div class="kc-container">
    <div class="kc-footer-top">

      <div class="kc-footer-col">
        <a href="{{ route('site.home') }}" class="kc-nav-logo kc-footer-logo">
          @include('site.partials.logo', ['gradId' => 'kcFooterGrad', 'from' => '#818cf8', 'to' => '#a78bfa'])
          <span class="kc-logo-text" style="color:#fff;">KRANTI <span style="color:#a5b4fc;">COMPUTER</span></span>
        </a>
        <p class="kc-footer-about-text">
          {{ \Illuminate\Support\Str::limit($site->text('site.about') ?: $site->get('site.name').' — '.$site->text('site.tagline'), 240) }}
        </p>
        <a href="{{ route('site.about') }}" class="kc-footer-read-more">{{ __('site.footer.about_more') }}</a>
      </div>

      <div class="kc-footer-col">
        <div class="kc-footer-col-title">{{ __('site.footer.quick_links') }}</div>
        <ul class="kc-query-links">
          <li><a href="{{ route('site.courses.index') }}">{{ __('site.nav.courses') }}</a></li>
          <li><a href="{{ route('site.placement') }}">{{ __('site.nav.placement') }}</a></li>
          <li><a href="{{ route('site.gallery') }}">{{ __('site.nav.gallery') }}</a></li>
          <li><a href="{{ route('site.download') }}">{{ __('site.nav.download') }}</a></li>
          <li><a href="{{ route('site.students.registration') }}">{{ __('site.footer.admissions') }}</a></li>
          <li><a href="{{ route('site.students.verification') }}">{{ __('site.nav.verify') }}</a></li>
          <li><a href="{{ route('site.franchise.info') }}">{{ __('site.nav.franchise') }}</a></li>
        </ul>
      </div>

      <div class="kc-footer-col">
        <div class="kc-footer-col-title">{{ __('site.footer.contact') }}</div>
        <ul class="kc-contact-list">
          @if (filled($site->get('site.address')))
            <li class="kc-contact-item">
              <div class="kc-contact-icon" aria-hidden="true">📍</div>
              <span class="kc-contact-text">{!! nl2br(e($site->get('site.address'))) !!}</span>
            </li>
          @endif
          @if (filled($site->get('site.email')))
            <li class="kc-contact-item">
              <div class="kc-contact-icon" aria-hidden="true">✉️</div>
              <span class="kc-contact-text"><a href="mailto:{{ $site->get('site.email') }}" style="color:inherit;">{{ $site->get('site.email') }}</a></span>
            </li>
          @endif
          @if ($phones)
            <li class="kc-contact-item">
              <div class="kc-contact-icon" aria-hidden="true">📞</div>
              <span class="kc-contact-text">
                @foreach ($phones as $phone)
                  <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" style="color:inherit;">{{ $phone }}</a>@if (! $loop->last), @endif
                @endforeach
              </span>
            </li>
          @endif
        </ul>
        @if ($socials)
          <div class="kc-footer-socials" aria-label="{{ __('site.footer.follow') }}">
            @foreach ($socials as $network => $link)
              @if (\Illuminate\Support\Str::startsWith($link, ['https://', 'http://']))
                <a href="{{ $link }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::title($network) }}</a>
              @endif
            @endforeach
          </div>
        @endif
      </div>

    </div>

    <div class="kc-footer-bottom">
      <span class="kc-footer-copy">© {{ date('Y') }}, {{ $site->get('site.name') }}. {{ __('site.footer.rights') }}</span>
      <span class="kc-footer-tagline">{{ __('site.footer.tagline') }}</span>
    </div>
  </div>
</footer>
