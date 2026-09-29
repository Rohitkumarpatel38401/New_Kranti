@extends('site.layouts.app')

@section('title', __('site.placement.title'))

@section('content')
@include('site.partials.page-hero', ['title' => __('site.placement.title'), 'sub' => __('site.placement.sub')])

<section class="ms-section ms-section--soft">
  <div class="kc-container">
    @if ($testimonials->isEmpty())
      <div class="ms-empty">{{ __('site.placement.empty') }}</div>
    @else
      <div class="ms-grid-4">
        @foreach ($testimonials as $t)
          <div>
            @include('site.partials.alumni-card')
            @if ($t->localized('quote'))
              <p class="ms-card-text" style="margin:12px 4px 0;font-style:italic;">“{{ $t->localized('quote') }}”</p>
            @endif
          </div>
        @endforeach
      </div>
    @endif
  </div>
</section>

<section class="ms-section ms-section--dark">
  <div class="kc-container ms-text-center">
    <h2 class="ms-section-title" style="color:#fff; margin-bottom:12px;">{{ __('site.home.cta_title') }}</h2>
    <a href="{{ route('site.students.registration') }}" class="kc-btn kc-btn-neon-red">{{ __('site.home.cta_button') }}</a>
  </div>
</section>
@endsection
