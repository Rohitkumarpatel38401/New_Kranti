@extends('site.layouts.app')

@section('title', __('site.verify.title'))

@section('content')
@include('site.partials.page-hero', ['title' => __('site.verify.title')])

<section class="ms-section ms-section--soft">
  <div class="kc-container" style="max-width:680px;">
    <div class="ms-form-card">
      <h2 class="ms-section-title" style="font-size:1.3rem;margin-top:0">{{ __('site.verify.result_title') }}</h2>
      @if (! $data)
        <div class="ms-alert ms-alert--bad" role="status"><strong>{{ __('site.verify.not_found') }}</strong> {{ __('site.verify.not_found_hint') }}</div>
      @else
        <div class="ms-alert {{ $data['valid'] === 'yes' ? 'ms-alert--ok' : 'ms-alert--bad' }}" role="status">
          <strong>{{ $data['valid'] === 'yes' ? '✔ '.__('site.verify.genuine') : '✖ '.__('site.verify.revoked') }}</strong>
        </div>
        {{-- Only the fields printed on the certificate itself (spec §6.7). --}}
        <dl class="ms-dl">
          <dt>{{ __('site.verify.certificate_no') }}</dt><dd>{{ $data['certificate_no'] }}</dd>
          <dt>{{ __('site.verify.student') }}</dt><dd>{{ $data['student'] }}</dd>
          <dt>{{ __('site.verify.father') }}</dt><dd>{{ $data['father'] ?? '—' }}</dd>
          <dt>{{ __('site.verify.course') }}</dt><dd>{{ $data['course'] }}</dd>
          <dt>{{ __('site.verify.grade') }}</dt><dd>{{ $data['grade'] ?? '—' }}</dd>
          <dt>{{ __('site.verify.centre') }}</dt><dd>{{ $data['institute'] }}</dd>
          <dt>{{ __('site.verify.issued_on') }}</dt><dd>{{ $data['issued_on'] }}</dd>
        </dl>
      @endif
      <p style="margin:22px 0 0;"><a href="{{ route('site.students.verification') }}" class="kc-btn kc-btn-outline-indigo">{{ __('site.verify.again') }}</a></p>
    </div>
  </div>
</section>
@endsection
