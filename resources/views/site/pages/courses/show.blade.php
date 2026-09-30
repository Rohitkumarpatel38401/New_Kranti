@extends('site.layouts.app')

@section('title', $course->localized('name'))
@section('meta_desc', $course->localized('short_description') ?: $course->localized('name'))

@php($subjects = $course->courseSubjects)
@php($terms = $subjects->groupBy('term_no'))
@php($applyUrl = route('site.students.registration', ['course' => $course->id]))
@php($whatsapp = $site->whatsappUrl(__('site.sidebar.whatsapp_text').' '.$course->code))
@php($phone = $site->get('site.phone'))
@php($includes = collect(__('site.course.includes'))->map(fn ($line) => str_replace(':hours', (string) ($course->total_hours ?: '—'), $line)))

@section('content')

{{-- ── Hero: badges, title, stats and calls to action ── --}}
<section class="kc-course-hero">
  <div class="kc-container">
    <div class="ms-breadcrumb">
      <a href="{{ route('site.home') }}">{{ __('site.nav.home') }}</a> /
      <a href="{{ route('site.courses.index') }}">{{ __('site.nav.courses') }}</a> / {{ $course->code }}
    </div>
    <div class="kc-course-hero-body">
      <div class="kc-course-badges">
        @if ($course->category)<span class="kc-pill">{{ $course->category->localized('name') }}</span>@endif
        <span class="kc-pill kc-pill--ghost">{{ $course->code }}</span>
        @if ($course->is_featured)<span class="kc-pill kc-pill--hot">★ {{ __('site.home.courses_label') }}</span>@endif
      </div>
      <h1 class="kc-course-title">{{ $course->localized('name') }}</h1>
      @if ($course->localized('short_description'))
        <p class="kc-course-lead">{{ $course->localized('short_description') }}</p>
      @endif
      <div class="kc-course-stats">
        <div><span>⏱</span><strong>{{ $course->durationLabel() }}</strong><small>{{ __('site.course.stat_duration') }}</small></div>
        <div><span>📚</span><strong>{{ $subjects->count() }}</strong><small>{{ __('site.course.stat_subjects') }}</small></div>
        @if ($course->total_hours)
          <div><span>🕒</span><strong>{{ $course->total_hours }}</strong><small>{{ __('site.course.stat_hours') }}</small></div>
        @endif
        <div><span>🌐</span><strong>{{ __('site.course.language') }}</strong><small>{{ __('site.course.stat_language') }}</small></div>
        <div><span>📜</span><strong>{{ __('site.course.certificate_short') }}</strong><small>{{ __('site.course.stat_certificate') }}</small></div>
      </div>
      <div class="kc-course-cta">
        <a href="{{ $applyUrl }}" class="kc-btn kc-btn-neon-red">{{ __('site.course.apply') }}</a>
        @if ($whatsapp)
          <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="kc-side-btn kc-side-btn--wa" style="padding:0 18px;">🟢 {{ __('site.course.enquire') }}</a>
        @endif
      </div>
    </div>
  </div>
</section>

<section class="ms-section ms-section--soft" style="padding-top:36px;">
  <div class="kc-container kc-with-sidebar">
    <div class="kc-main-col">

      @if ($subjects->isNotEmpty())
        <div class="kc-block">
          <h2 class="kc-block-title">{{ __('site.course.learn_title') }}</h2>
          <ul class="kc-learn-grid">
            @foreach ($subjects as $cs)
              <li>{{ $cs->subject?->localized('name') }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      @if ($course->localized('description'))
        <div class="kc-block">
          <h2 class="kc-block-title">{{ __('site.course.about_title') }}</h2>
          <div class="ms-prose">
            @foreach (preg_split('/\R{2,}/', $course->localized('description')) as $para)
              <p>{!! nl2br(e($para)) !!}</p>
            @endforeach
          </div>
        </div>
      @endif

      @if ($subjects->isNotEmpty())
        <div class="kc-block">
          <h2 class="kc-block-title">{{ __('site.course.curriculum_title') }}</h2>
          <p class="kc-block-sub">{{ __('site.course.curriculum_sub', ['count' => $subjects->count()]) }}</p>
          <div class="kc-accordion">
            @php($n = 0)
            @foreach ($terms as $termNo => $termSubjects)
              @foreach ($termSubjects as $cs)
                @php($n++)
                <details class="kc-acc-item" @if ($n === 1) open @endif>
                  <summary>
                    <span class="kc-acc-num">{{ str_pad((string) $n, 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="kc-acc-name">{{ $cs->subject?->localized('name') }}</span>
                    @if ($terms->count() > 1)<span class="kc-pill kc-pill--ghost">{{ __('site.course.term', ['no' => $termNo]) }}</span>@endif
                  </summary>
                  <div class="kc-acc-body">
                    {{ __('site.course.marks', ['theory' => $cs->theory_max ?: '—', 'practical' => $cs->practical_max ?: '—']) }}
                  </div>
                </details>
              @endforeach
            @endforeach
          </div>
        </div>
      @endif

      <div class="kc-two">
        @if ($course->localized('eligibility'))
          <div class="kc-block kc-block--card">
            <h2 class="kc-block-title">🎯 {{ __('site.course.eligibility_title') }}</h2>
            <p class="ms-prose" style="margin:0">{{ $course->localized('eligibility') }}</p>
          </div>
        @endif
        <div class="kc-block kc-block--card kc-cert-card">
          <h2 class="kc-block-title">📜 {{ __('site.course.certificate_title') }}</h2>
          <p class="kc-block-sub" style="margin-bottom:14px">{{ __('site.course.certificate_text') }}</p>
          <a href="{{ route('site.students.verification') }}" class="kc-side-link">{{ __('site.course.certificate_cta') }} →</a>
        </div>
      </div>

      @if ($faculty->isNotEmpty())
        <div class="kc-block">
          <h2 class="kc-block-title">{{ __('site.course.faculty_title') }}</h2>
          <div class="ms-grid-3">
            @foreach ($faculty as $member)
              @include('site.partials.teacher-card')
            @endforeach
          </div>
        </div>
      @endif

      @if ($stories->isNotEmpty())
        <div class="kc-block">
          <h2 class="kc-block-title">{{ __('site.course.stories_title') }}</h2>
          <div class="ms-grid-3">
            @foreach ($stories as $t)
              <div>
                @include('site.partials.alumni-card')
                @if ($t->localized('quote'))
                  <p class="ms-card-text" style="margin:10px 4px 0;font-style:italic;">“{{ $t->localized('quote') }}”</p>
                @endif
              </div>
            @endforeach
          </div>
        </div>
      @endif

      <div class="kc-block">
        <h2 class="kc-block-title">{{ __('site.course.faq_title') }}</h2>
        <div class="kc-accordion">
          @foreach (__('site.course.faqs') as $faq)
            <details class="kc-acc-item">
              <summary><span class="kc-acc-name">{{ $faq['q'] }}</span></summary>
              <div class="kc-acc-body">{{ $faq['a'] }}</div>
            </details>
          @endforeach
        </div>
      </div>

      @if ($related->isNotEmpty())
        <div class="kc-block">
          <h2 class="kc-block-title">{{ __('site.course.related_title') }}</h2>
          <div class="ms-grid-3">
            @foreach ($related as $relatedCourse)
              @include('site.partials.course-card', ['course' => $relatedCourse])
            @endforeach
          </div>
        </div>
      @endif
    </div>

    {{-- Sticky enrol card --}}
    <aside class="kc-sidebar kc-enrol">
      <div class="kc-side-card kc-enrol-card">
        <div class="kc-enrol-media">
          @if ($course->thumbnailUrl())
            <img src="{{ $course->thumbnailUrl() }}" alt="{{ $course->localized('name') }}">
          @else
            <div class="kc-enrol-placeholder">{{ $course->code }}</div>
          @endif
        </div>
        @if ((float) $course->suggested_fee > 0)
          <div class="kc-enrol-price">
            <small>{{ __('site.course.fee_from') }}</small>
            <strong>₹{{ number_format((float) $course->suggested_fee) }}</strong>
          </div>
          <p class="kc-side-sub">{{ __('site.course.fee_note') }}</p>
        @endif
        <a href="{{ $applyUrl }}" class="kc-btn kc-btn-gradient" style="width:100%;justify-content:center;">{{ __('site.course.apply') }}</a>
        <div class="kc-side-actions" style="margin-top:10px;">
          @if ($phone)
            <a class="kc-side-btn kc-side-btn--call" href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">📞 {{ __('site.course.call') }}</a>
          @endif
          @if ($whatsapp)
            <a class="kc-side-btn kc-side-btn--wa" href="{{ $whatsapp }}" target="_blank" rel="noopener">🟢 {{ __('site.sidebar.whatsapp') }}</a>
          @endif
        </div>
        <div class="kc-side-title" style="margin-top:8px;">{{ __('site.course.card_includes') }}</div>
        <ul class="kc-side-list kc-side-check">
          @foreach ($includes as $item)
            <li>{{ $item }}</li>
          @endforeach
        </ul>
      </div>
    </aside>
  </div>
</section>
@endsection
