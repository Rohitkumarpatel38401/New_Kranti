<div class="ms-course-card">
  <div class="ms-course-card-top">
    @if ($course->is_featured)
      <span class="ms-course-badge ms-course-badge--popular">★</span>
    @endif
    <div class="ms-course-title">{{ $course->localized('name') }}</div>
    <div class="ms-course-desc">{{ $course->localized('short_description') }}</div>
    <div class="ms-course-tags">
      <span class="ms-course-tag">{{ $course->code }}</span>
      @if ($course->category)
        <span class="ms-course-tag">{{ $course->category->localized('name') }}</span>
      @endif
    </div>
  </div>
  <div class="ms-course-card-bottom">
    <span class="ms-course-meta">⏱ {{ $course->durationLabel() }}</span>
    <a href="{{ route('site.courses.show', $course->slug) }}" class="kc-btn kc-btn-outline-indigo" style="height:34px;padding:0 16px;font-size:0.8rem;">{{ __('site.common.details') }}</a>
  </div>
</div>
