{{-- Spam trap: hidden from people and screen readers; the name avoids anything browsers autofill. --}}
<div class="ms-hp" aria-hidden="true">
  <label for="kc-hp">{{ __('site.form.honeypot') }}</label>
  <input id="kc-hp" type="text" name="{{ \App\Http\Controllers\SiteController::HONEYPOT }}" value="" tabindex="-1" autocomplete="off" data-lpignore="true" data-1p-ignore data-form-type="other">
</div>
