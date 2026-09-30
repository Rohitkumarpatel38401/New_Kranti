<div class="kc-side-card kc-side-accent">
  <div class="kc-side-title">🚀 {{ __('site.sidebar.apply_title') }}</div>
  <p class="kc-side-sub">{{ __('site.sidebar.apply_sub') }}</p>
  <a href="{{ route('site.students.registration', isset($course) ? ['course' => $course->id] : []) }}" class="kc-btn kc-btn-gradient" style="width:100%;justify-content:center;">{{ __('site.common.apply_now') }}</a>
</div>
