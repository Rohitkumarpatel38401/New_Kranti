{{-- Course card: banner (photo or gradient + code), badges, title, meta chips, fee, Details / Apply. --}}
@php($subjectCount = $course->course_subjects_count ?? $course->courseSubjects()->count())
@php($detailsUrl = route('site.courses.show', $course->slug))

<article class="kc-ccard">
  <a href="{{ $detailsUrl }}" class="kc-ccard-media" tabindex="-1" aria-hidden="true">
    @if ($course->thumbnailUrl())
      <img src="{{ $course->thumbnailUrl() }}" alt="" loading="lazy">
    @else
      <span class="kc-ccard-code">{{ $course->code }}</span>
    @endif
    <span class="kc-ccard-badges">
      @if ($course->category)<span class="kc-ccard-badge">{{ $course->category->localized('name') }}</span>@endif
      @if ($course->is_featured)<span class="kc-ccard-badge kc-ccard-badge--hot">★</span>@endif
    </span>
  </a>

  <div class="kc-ccard-body">
    <h3 class="kc-ccard-title"><a href="{{ $detailsUrl }}">{{ $course->localized('name') }}</a></h3>
    @if ($course->localized('short_description'))
      <p class="kc-ccard-desc">{{ $course->localized('short_description') }}</p>
    @endif
    <div class="kc-ccard-meta">
      <span>⏱ {{ $course->durationLabel() }}</span>
      @if ($subjectCount)<span>📚 {{ $subjectCount }} {{ __('site.course.stat_subjects') }}</span>@endif
      @if ($course->total_hours)<span>🕒 {{ $course->total_hours }} {{ __('site.course.stat_hours') }}</span>@endif
    </div>
  </div>

  <div class="kc-ccard-foot">
    @if ((float) $course->suggested_fee > 0)
      <div class="kc-ccard-price">
        <small>{{ __('site.course.fee_from') }}</small>
        <strong>₹{{ number_format((float) $course->suggested_fee) }}</strong>
      </div>
    @endif
    <div class="kc-ccard-actions">
      <a href="{{ $detailsUrl }}" class="kc-ccard-btn">{{ __('site.common.details') }}</a>
      <a href="{{ route('site.students.registration', ['course' => $course->id]) }}" class="kc-ccard-btn kc-ccard-btn--primary">{{ __('site.course.apply') }}</a>
    </div>
  </div>
</article>
