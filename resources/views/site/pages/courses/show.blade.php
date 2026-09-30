@extends('site.layouts.app')

@section('title', $course->localized('name'))
@section('meta_desc', $course->localized('short_description') ?: $course->localized('name'))

@section('content')
@include('site.partials.page-hero', ['title' => $course->localized('name'), 'sub' => $course->localized('short_description')])

<section class="ms-section ms-section--white">
  <div class="kc-container kc-with-sidebar">
    <div class="kc-main-col">
      <p style="margin-top:0"><a href="{{ route('site.courses.index') }}" class="kc-btn kc-btn-ghost">{{ __('site.courses.back') }}</a></p>

      <div class="ms-grid-2" style="align-items:start;">
        <div>
          @if ($course->thumbnailUrl())
            <img src="{{ $course->thumbnailUrl() }}" alt="{{ $course->localized('name') }}" style="width:100%;border-radius:16px;margin-bottom:20px;">
          @endif
          @if ($course->localized('description'))
            <h2 class="ms-section-title" style="font-size:1.3rem">{{ __('site.courses.about_course') }}</h2>
            <div class="ms-prose">
              @foreach (preg_split('/\R{2,}/', $course->localized('description')) as $para)
                <p>{!! nl2br(e($para)) !!}</p>
              @endforeach
            </div>
          @endif
          @if ($course->localized('eligibility'))
            <h2 class="ms-section-title" style="font-size:1.3rem">{{ __('site.courses.eligibility') }}</h2>
            <div class="ms-prose"><p>{{ $course->localized('eligibility') }}</p></div>
          @endif
        </div>

        <div class="ms-form-card">
          <dl class="ms-dl">
            <dt>{{ __('site.courses.code') }}</dt><dd>{{ $course->code }}</dd>
            @if ($course->category)
              <dt>{{ __('site.courses.category') }}</dt><dd>{{ $course->category->localized('name') }}</dd>
            @endif
            <dt>{{ __('site.common.duration') }}</dt><dd>{{ $course->durationLabel() }}</dd>
            @if ($course->total_hours)
              <dt>{{ __('site.courses.hours') }}</dt><dd>{{ $course->total_hours }}</dd>
            @endif
          </dl>
          <a href="{{ route('site.students.registration', ['course' => $course->id]) }}" class="kc-btn kc-btn-gradient" style="margin-top:22px;width:100%;justify-content:center;">{{ __('site.courses.apply') }}</a>
        </div>
      </div>

      @if ($course->courseSubjects->isNotEmpty())
        <h2 class="ms-section-title" style="font-size:1.3rem;margin-top:40px">{{ __('site.courses.syllabus') }}</h2>
        @php($multiTerm = $course->courseSubjects->pluck('term_no')->unique()->count() > 1)
        <div class="ms-table-wrap">
          <table class="ms-table">
            <thead>
              <tr>
                @if ($multiTerm)<th>{{ __('site.courses.term') }}</th>@endif
                <th>{{ __('site.courses.subject') }}</th>
                <th>{{ __('site.courses.theory') }}</th>
                <th>{{ __('site.courses.practical') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($course->courseSubjects as $cs)
                <tr>
                  @if ($multiTerm)<td>{{ $cs->term_no }}</td>@endif
                  <td>{{ $cs->subject?->localized('name') }}</td>
                  <td>{{ $cs->theory_max ?: '—' }}</td>
                  <td>{{ $cs->practical_max ?: '—' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
    @include('site.partials.sidebar', ['extra' => 'site.partials.sidebar-apply'])
  </div>
</section>
@endsection
