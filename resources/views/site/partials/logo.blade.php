<svg class="kc-logo-icon" viewBox="0 0 36 36" fill="none" aria-hidden="true">
  <defs>
    <linearGradient id="{{ $gradId }}" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{{ $from }}"/>
      <stop offset="100%" stop-color="{{ $to }}"/>
    </linearGradient>
  </defs>
  <polygon points="18,3 33,12 33,24 18,33 3,24 3,12" fill="url(#{{ $gradId }})" opacity="0.13"/>
  <polygon points="18,7 29,13.5 29,24 18,29 7,24 7,13.5" fill="none" stroke="url(#{{ $gradId }})" stroke-width="1.8"/>
  <polyline points="12,18.5 16,22.5 24,14" stroke="url(#{{ $gradId }})" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
