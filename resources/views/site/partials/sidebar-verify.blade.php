<div class="kc-side-card kc-side-accent">
  <div class="kc-side-title">🔎 {{ __('site.sidebar.verify_title') }}</div>
  <ul class="kc-side-list">
    @foreach (__('site.sidebar.verify_tips') as $tip)
      <li>{{ $tip }}</li>
    @endforeach
  </ul>
</div>
