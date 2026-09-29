@extends('site.layouts.app')

@section('title', __('site.courses.title'))

@section('content')
@include('site.partials.page-hero', ['title' => __('site.courses.title'), 'sub' => __('site.courses.sub')])

<section class="ms-section ms-section--soft">
  <div class="kc-container">
    @if ($categories->count() > 1)
      <div class="ms-chip-row" role="navigation" aria-label="{{ __('site.courses.category') }}">
        <a href="{{ route('site.courses.index') }}" class="ms-filter-btn {{ $active ? '' : 'active' }}">{{ __('site.courses.all') }}</a>
        @foreach ($categories as $category)
          <a href="{{ route('site.courses.index', ['category' => $category->slug]) }}" class="ms-filter-btn {{ $active?->is($category) ? 'active' : '' }}">{{ $category->localized('name') }}</a>
        @endforeach
      </div>
    @endif

    @if ($courses->isEmpty())
      <div class="ms-empty">{{ __('site.courses.empty') }}</div>
    @else
      <div class="ms-grid-3">
        @foreach ($courses as $course)
          @include('site.partials.course-card')
        @endforeach
      </div>
    @endif
  </div>
</section>
@endsection
