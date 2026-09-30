<div class="kc-side-card kc-side-accent">
  <div class="kc-side-title">🧭 {{ __('site.sidebar.steps_title') }}</div>
  <ol class="kc-side-steps">
    @foreach (__('site.sidebar.steps') as $step)
      <li>{{ $step }}</li>
    @endforeach
  </ol>
</div>
