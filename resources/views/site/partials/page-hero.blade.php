<section class="ms-page-hero">
  <div class="kc-container">
    <div class="ms-breadcrumb"><a href="{{ route('site.home') }}">{{ __('site.nav.home') }}</a> / {{ $title }}</div>
    <h1>{{ $title }}</h1>
    @isset($sub)<p>{{ $sub }}</p>@endisset
  </div>
</section>
