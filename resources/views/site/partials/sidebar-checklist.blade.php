<div class="kc-side-card kc-side-accent">
  <div class="kc-side-title">✅ {{ __('site.franchise.needs_title') }}</div>
  <ul class="kc-side-list kc-side-check">
    @foreach (__('site.franchise.needs') as $need)
      <li>{{ $need }}</li>
    @endforeach
  </ul>
</div>
