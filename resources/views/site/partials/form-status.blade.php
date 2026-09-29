@if (session('status'))
  <div class="ms-alert ms-alert--ok" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
  <div class="ms-alert ms-alert--bad" role="alert">{{ __('site.form.errors') }}</div>
@endif
