@extends('site.layouts.app')

@section('title', __('site.home.title'))

@section('content')

{{-- 1. Hero slider: admin sliders, or the built-in bilingual slides until some are added --}}
<section class="ms-hero-slider">
  <div class="swiper ms-swiper-hero" id="msHeroSwiper">
    <div class="swiper-wrapper">
      @forelse ($sliders as $slider)
        <div class="swiper-slide">
          <div class="ms-hero-slide-bg ms-hero-slide-bg--{{ $loop->index % 3 + 1 }}"></div>
          <div class="kc-container ms-hero-slide-inner">
            <div class="ms-hero-slide-text">
              @if ($slider->localized('label'))<span class="ms-hero-label">{{ $slider->localized('label') }}</span>@endif
              <h1 class="ms-hero-title">{{ $slider->localized('title') }}</h1>
              @if ($slider->localized('subtitle'))<p class="ms-hero-sub">{{ $slider->localized('subtitle') }}</p>@endif
              <div class="ms-hero-actions">
                <a href="{{ \App\Support\SafeUrl::or($slider->button_url, route('site.students.registration')) }}" class="kc-btn kc-btn-solid-indigo">{{ $slider->localized('button_text') ?: __('site.common.apply_now') }}</a>
                <a href="{{ route('site.courses.index') }}" class="kc-btn kc-btn-ghost">{{ __('site.common.view_courses') }}</a>
              </div>
            </div>
            <div class="ms-hero-slide-media">
              @if ($slider->mediaUrl())
                <img src="{{ $slider->mediaUrl() }}" alt="{{ $slider->localized('title') }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
              @else
                <div class="ms-hero-slide-placeholder">🎓</div>
              @endif
            </div>
          </div>
        </div>
      @empty
        @foreach (__('site.home.slides') as $i => $slide)
          <div class="swiper-slide">
            <div class="ms-hero-slide-bg ms-hero-slide-bg--{{ $i + 1 }}"></div>
            <div class="kc-container ms-hero-slide-inner">
              <div class="ms-hero-slide-text">
                <span class="ms-hero-label">{{ $slide['label'] }}</span>
                <h1 class="ms-hero-title">{{ $slide['title'] }} <span class="ms-accent">{{ $slide['accent'] }}</span></h1>
                <p class="ms-hero-sub">{{ $slide['sub'] }}</p>
                <div class="ms-hero-actions">
                  <a href="{{ route('site.students.registration') }}" class="kc-btn kc-btn-solid-indigo">{{ __('site.common.apply_now') }}</a>
                  <a href="{{ route('site.courses.index') }}" class="kc-btn kc-btn-ghost">{{ __('site.common.view_courses') }}</a>
                </div>
              </div>
              <div class="ms-hero-slide-media">
                <div class="ms-hero-slide-placeholder">{{ ['🎓', '💼', '📜'][$i] ?? '🎓' }}</div>
              </div>
            </div>
          </div>
        @endforeach
      @endforelse
    </div>
    <div class="swiper-pagination" id="msHeroPagination"></div>
    <div class="swiper-button-prev ms-swiper-prev"></div>
    <div class="swiper-button-next ms-swiper-next"></div>
  </div>
</section>

{{-- 2. Stats (site settings) --}}
@php($stats = (array) $site->get('site.stats'))
<section class="ms-section ms-section--white" style="padding: 24px 0;">
  <div class="kc-container">
    <div class="ms-grid-4">
      @foreach (['students', 'courses', 'placement', 'years'] as $key)
        @if (filled($stats[$key] ?? null))
          <div class="ms-stat">
            <div class="ms-stat-number">{{ $stats[$key] }}</div>
            <div class="ms-stat-label">{{ __('site.home.stats.'.$key) }}</div>
          </div>
        @endif
      @endforeach
    </div>
  </div>
</section>

{{-- 3. Featured courses --}}
@if ($courses->isNotEmpty())
  <section class="ms-section ms-section--soft">
    <div class="kc-container">
      @include('site.partials.section-head', ['label' => __('site.home.courses_label'), 'title' => __('site.home.courses_title'), 'sub' => __('site.home.courses_sub')])
      <div class="ms-grid-3">
        @foreach ($courses as $course)
          @include('site.partials.course-card')
        @endforeach
      </div>
      <div class="ms-text-center" style="margin-top: 36px;">
        <a href="{{ route('site.courses.index') }}" class="kc-btn kc-btn-gradient">{{ __('site.common.view_all_courses') }}</a>
      </div>
    </div>
  </section>
@endif

{{-- 4. Alumni --}}
@if ($testimonials->isNotEmpty())
  <section class="ms-section ms-section--white">
    <div class="kc-container">
      @include('site.partials.section-head', ['label' => __('site.home.alumni_label'), 'title' => __('site.home.alumni_title'), 'sub' => __('site.home.alumni_sub')])
      <div class="ms-grid-4">
        @foreach ($testimonials->take(4) as $t)
          @include('site.partials.alumni-card')
        @endforeach
      </div>
      <div class="ms-text-center" style="margin-top: 40px;">
        <a href="{{ route('site.placement') }}" class="kc-btn kc-btn-outline-indigo">{{ __('site.home.alumni_more') }}</a>
      </div>
    </div>
  </section>
@endif

{{-- 5. Faculty --}}
@if ($faculty->isNotEmpty())
  <section class="ms-section ms-section--soft">
    <div class="kc-container">
      @include('site.partials.section-head', ['label' => __('site.home.faculty_label'), 'title' => __('site.home.faculty_title'), 'sub' => __('site.home.faculty_sub')])
      <div class="ms-grid-3">
        @foreach ($faculty as $member)
          @include('site.partials.teacher-card')
        @endforeach
      </div>
    </div>
  </section>
@endif

{{-- 6. Gallery preview --}}
@if ($gallery->isNotEmpty())
  <section class="ms-section ms-section--white">
    <div class="kc-container">
      @include('site.partials.section-head', ['label' => __('site.home.gallery_label'), 'title' => __('site.home.gallery_title'), 'sub' => __('site.home.gallery_sub')])
      <div class="ms-gallery-grid">
        @foreach ($gallery as $item)
          <div class="ms-gallery-item {{ $loop->first ? 'ms-gallery-item--large' : '' }}">
            <img class="ms-media-img" src="{{ $item->mediaUrl() }}" alt="{{ $item->localized('title') }}" loading="lazy">
          </div>
        @endforeach
      </div>
      <div class="ms-text-center" style="margin-top: 32px;">
        <a href="{{ route('site.gallery') }}" class="kc-btn kc-btn-outline-indigo">{{ __('site.home.gallery_more') }}</a>
      </div>
    </div>
  </section>
@endif

{{-- 7. News ticker --}}
@if ($notices->isNotEmpty())
  <section class="ms-section ms-section--soft">
    <div class="kc-container">
      @include('site.partials.section-head', ['label' => __('site.home.news_label'), 'title' => __('site.home.news_title')])
      <div class="ms-news-list">
        <div class="ms-news-track" id="msNewsTrack">
          @foreach ($notices as $notice)
            <div class="ms-news-item">
              <div class="ms-news-date">{{ $notice->published_at?->translatedFormat('d M Y') }}</div>
              <div class="ms-news-content">
                <div class="ms-news-title">{{ $notice->localized('title') }}</div>
                @if ($notice->localized('body'))
                  <div class="ms-news-desc">{{ \Illuminate\Support\Str::limit($notice->localized('body'), 140) }}</div>
                @endif
              </div>
              @if ($notice->badge)
                <span class="ms-news-badge ms-news-badge--{{ $notice->badge }}">{{ \Illuminate\Support\Str::title($notice->badge) }}</span>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </section>
  @if ($notices->count() > 3)
    @push('scripts')
      <script>
        (function () { var t = document.getElementById('msNewsTrack'); if (t) t.innerHTML += t.innerHTML; })();
      </script>
    @endpush
  @endif
@endif

{{-- 8. Key points --}}
<section class="ms-section ms-section--white">
  <div class="kc-container">
    @include('site.partials.section-head', ['label' => __('site.home.points_label'), 'title' => __('site.home.points_title')])
    <div class="ms-grid-3">
      @foreach (__('site.home.points') as $point)
        <div class="ms-card">
          <div class="ms-card-icon">{{ $point['icon'] }}</div>
          <div class="ms-card-title">{{ $point['title'] }}</div>
          <div class="ms-card-text">{{ $point['text'] }}</div>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- 9. Online courses (only when a link is configured) --}}
@if (\App\Support\SafeUrl::isSafe($site->get('site.online_courses_url')))
  <section class="ms-section ms-section--soft">
    <div class="kc-container">
      @include('site.partials.section-head', ['label' => __('site.home.online_label'), 'title' => __('site.home.online_title'), 'sub' => __('site.home.online_sub')])
      <div class="ms-video-banner">
        <div class="ms-video-icon">▶</div>
        <div class="ms-video-text">
          <div class="ms-video-title">{{ __('site.home.online_banner') }}</div>
          <div class="ms-video-sub">{{ __('site.home.online_banner_sub') }}</div>
        </div>
        <a href="{{ $site->get('site.online_courses_url') }}" target="_blank" rel="noopener" class="kc-btn kc-btn-solid-indigo">{{ __('site.home.online_cta') }}</a>
      </div>
    </div>
  </section>
@endif

{{-- 10. CTA --}}
<section class="ms-section ms-section--dark">
  <div class="kc-container ms-text-center">
    <h2 class="ms-section-title" style="color:#fff; margin-bottom:12px;">{{ __('site.home.cta_title') }}</h2>
    <p style="color:#a5b4fc; margin-bottom:28px; max-width:480px; margin-inline:auto; line-height:1.65;">{{ __('site.home.cta_sub') }}</p>
    <a href="{{ route('site.students.registration') }}" class="kc-btn kc-btn-neon-red">{{ __('site.home.cta_button') }}</a>
  </div>
</section>

@push('scripts')
<script>
  if (window.Swiper) {
    new Swiper('#msHeroSwiper', {
      loop: document.querySelectorAll('#msHeroSwiper .swiper-slide').length > 1,
      speed: 700,
      autoplay: { delay: 4500, disableOnInteraction: false, pauseOnMouseEnter: true },
      pagination: { el: '#msHeroPagination', clickable: true },
      navigation: { prevEl: '.ms-swiper-prev', nextEl: '.ms-swiper-next' },
      grabCursor: true,
      keyboard: { enabled: true },
    });
  }
</script>
@endpush

@endsection
