@extends('site.layouts.app')

@section('title', __('site.about.title'))

@section('content')
@include('site.partials.page-hero', ['title' => __('site.about.title'), 'sub' => __('site.about.sub', ['year' => $site->get('site.established')])])

<section class="ms-section ms-section--white">
  <div class="kc-container">
    <div class="ms-grid-2" style="align-items:start;">
      <div>
        @include('site.partials.section-head', ['label' => $site->get('site.short_name'), 'title' => __('site.about.who')])
        <div class="ms-prose">
          @foreach (preg_split('/\R{2,}/', $site->text('site.about') ?: __('site.about.default', ['name' => $site->get('site.name')])) as $para)
            <p>{{ $para }}</p>
          @endforeach
        </div>
      </div>
      <div class="ms-grid-2" style="gap:16px;">
        @php($stats = (array) $site->get('site.stats'))
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
  </div>
</section>

<section class="ms-section ms-section--soft">
  <div class="kc-container">
    <div class="ms-grid-2">
      <div class="ms-card">
        <div class="ms-card-icon">🎯</div>
        <div class="ms-card-title">{{ __('site.about.mission_title') }}</div>
        <div class="ms-card-text">{{ __('site.about.mission') }}</div>
      </div>
      <div class="ms-card">
        <div class="ms-card-icon">🏫</div>
        <div class="ms-card-title">{{ __('site.about.network_title') }}</div>
        <div class="ms-card-text">{{ __('site.about.network') }}</div>
      </div>
    </div>
  </div>
</section>

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

@if ($faculty->isNotEmpty())
  <section class="ms-section ms-section--soft">
    <div class="kc-container">
      @include('site.partials.section-head', ['label' => __('site.home.faculty_label'), 'title' => __('site.home.faculty_title')])
      <div class="ms-grid-3">
        @foreach ($faculty as $member)
          @include('site.partials.teacher-card')
        @endforeach
      </div>
    </div>
  </section>
@endif
@endsection
