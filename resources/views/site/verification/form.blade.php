@extends('site.layouts.app')

@section('title', __('site.verify.title'))

@section('content')
@include('site.partials.page-hero', ['title' => __('site.verify.title'), 'sub' => __('site.verify.sub')])

<section class="ms-section ms-section--soft">
  <div class="kc-container" style="max-width:620px;">
    <div class="ms-form-card">
      <h2 class="ms-section-title" style="font-size:1.3rem;margin-top:0">{{ __('site.verify.form_title') }}</h2>
      @if ($errors->any())
        <div class="ms-alert ms-alert--bad" role="alert">{{ $errors->first() }}</div>
      @endif
      <form method="POST" action="{{ route('site.students.verification.lookup') }}">
        @csrf
        @include('site.partials.field', ['name' => 'certificate_no', 'label' => __('site.verify.certificate_no'), 'attrs' => 'placeholder="KCTI/C/2026/000123" maxlength="40"'])
        <p class="ms-text-center" style="color:var(--kc-text-muted);margin:4px 0 16px;">{{ __('site.verify.or') }}</p>
        <div class="ms-form-grid">
          @include('site.partials.field', ['name' => 'enrollment_no', 'label' => __('site.verify.enrollment_no'), 'attrs' => 'placeholder="KCTI/2026/000123" maxlength="40"'])
          @include('site.partials.field', ['name' => 'dob', 'label' => __('site.verify.dob'), 'type' => 'date'])
        </div>
        <button type="submit" class="kc-btn kc-btn-gradient">{{ __('site.verify.submit') }}</button>
      </form>
    </div>
  </div>
</section>
@endsection
