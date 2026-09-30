<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="description" content="@yield('meta_desc', $site->text('site.tagline').' — '.$site->get('site.name'))">
  <title>@yield('title', __('site.nav.home')) | {{ $site->get('site.name') }}</title>

  {{-- Apply the saved (or system) theme before first paint so dark mode never flashes white. --}}
  <script>
    (function () {
      var t = null;
      try { t = localStorage.getItem('kcti-theme'); } catch (e) {}
      if (t !== 'light' && t !== 'dark') t = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', t);
      // Cards fade in as they scroll into view (kc-navbar.js); without JS everything stays visible.
      if (!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches)) document.documentElement.classList.add('js-reveal');
    })();
  </script>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/site/kc-main.css') }}">
  <link rel="stylesheet" href="{{ asset('css/site/ms-main.css') }}">
  <link rel="stylesheet" href="{{ asset('css/site/ms-styles.css') }}">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
  <link rel="stylesheet" href="{{ asset('css/site/site-theme.css') }}">
  @stack('styles')
</head>
<body>
  @include('site.partials.header')

  <main id="main">
    @yield('content')
  </main>

  @include('site.partials.footer')

  <script src="{{ asset('js/site/kc-navbar.js') }}"></script>
  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  @stack('scripts')
</body>
</html>
