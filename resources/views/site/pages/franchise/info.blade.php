@extends('site.layouts.app')

@section('title', __('site.franchise.info_title'))

@section('content')
@include('site.partials.page-hero', ['title' => __('site.franchise.info_title'), 'sub' => __('site.franchise.info_sub')])

<section class="ms-section ms-section--white">
  <div class="kc-container">
    @include('site.partials.section-head', ['title' => __('site.franchise.why_title')])
    <div class="ms-grid-4">
      @foreach (__('site.franchise.why') as $point)
        <div class="ms-card">
          <div class="ms-card-icon">{{ $point['icon'] }}</div>
          <div class="ms-card-title">{{ $point['title'] }}</div>
          <div class="ms-card-text">{{ $point['text'] }}</div>
        </div>
      @endforeach
    </div>
  </div>
</section>

<section class="ms-section ms-section--soft">
  <div class="kc-container">
    <div class="ms-grid-2" style="align-items:start;">
      <div class="ms-form-card">
        <h2 class="ms-section-title" style="font-size:1.3rem;margin-top:0">{{ __('site.franchise.needs_title') }}</h2>
        <ul class="ms-prose" style="padding-left:20px;margin:0;">
          @foreach (__('site.franchise.needs') as $need)
            <li>{{ $need }}</li>
          @endforeach
        </ul>
      </div>
      <div class="ms-form-card">
        <h2 class="ms-section-title" style="font-size:1.3rem;margin-top:0">{{ __('site.franchise.steps_title') }}</h2>
        <ol class="ms-prose" style="padding-left:20px;margin:0;">
          @foreach (__('site.franchise.steps') as $step)
            <li>{{ $step }}</li>
          @endforeach
        </ol>
      </div>
    </div>
  </div>
</section>

<section class="ms-section ms-section--dark">
  <div class="kc-container ms-text-center">
    <h2 class="ms-section-title" style="color:#fff; margin-bottom:24px;">{{ __('site.franchise.info_sub') }}</h2>
    <a href="{{ route('site.franchise.apply') }}" class="kc-btn kc-btn-neon-red">{{ __('site.franchise.apply_cta') }}</a>
  </div>
</section>
@endsection
