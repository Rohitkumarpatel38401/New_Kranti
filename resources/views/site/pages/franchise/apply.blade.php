@extends('site.layouts.app')

@section('title', __('site.franchise.apply_title'))

@section('content')
@include('site.partials.page-hero', ['title' => __('site.franchise.apply_title'), 'sub' => __('site.franchise.apply_sub')])

<section class="ms-section ms-section--soft">
  <div class="kc-container kc-with-sidebar">
    <div class="kc-main-col">
      <div class="ms-form-card">
        @include('site.partials.form-status')
        <form method="POST" action="{{ route('site.franchise.apply.submit') }}" novalidate>
          @csrf
          @include('site.partials.honeypot')

          <h2 class="ms-section-title" style="font-size:1.15rem;margin-top:0">{{ __('site.franchise.you') }}</h2>
          <div class="ms-form-grid">
            @include('site.partials.field', ['name' => 'applicant_name', 'label' => __('site.form.applicant_name'), 'required' => true, 'attrs' => 'autocomplete="name" maxlength="150"'])
            @include('site.partials.field', ['name' => 'phone', 'label' => __('site.form.phone'), 'type' => 'tel', 'required' => true, 'attrs' => 'autocomplete="tel" inputmode="tel" maxlength="20"'])
            @include('site.partials.field', ['name' => 'email', 'label' => __('site.form.email'), 'type' => 'email', 'required' => true, 'class' => 'ms-span-2', 'attrs' => 'autocomplete="email"'])
          </div>

          <h2 class="ms-section-title" style="font-size:1.15rem;margin-top:24px">{{ __('site.franchise.centre') }}</h2>
          <div class="ms-form-grid">
            @include('site.partials.field', ['name' => 'proposed_center_name', 'label' => __('site.form.proposed_center_name'), 'required' => true, 'class' => 'ms-span-2', 'attrs' => 'maxlength="200"'])
            @include('site.partials.field', ['name' => 'computers_count', 'label' => __('site.form.computers_count'), 'type' => 'number', 'attrs' => 'min="0" max="1000"'])
            @include('site.partials.field', ['name' => 'area_sqft', 'label' => __('site.form.area_sqft'), 'type' => 'number', 'attrs' => 'min="0"'])
            @include('site.partials.field', ['name' => 'experience_years', 'label' => __('site.form.experience_years'), 'type' => 'number', 'attrs' => 'min="0" max="60"'])
            @include('site.partials.field', ['name' => 'message', 'label' => __('site.form.message'), 'type' => 'textarea', 'class' => 'ms-span-2', 'rows' => 3])
          </div>

          <h2 class="ms-section-title" style="font-size:1.15rem;margin-top:24px">{{ __('site.franchise.address') }}</h2>
          <div class="ms-form-grid">
            @include('site.partials.field', ['name' => 'line_1', 'label' => __('site.form.line_1'), 'required' => true, 'class' => 'ms-span-2', 'attrs' => 'autocomplete="address-line1" maxlength="255"'])
            @include('site.partials.field', ['name' => 'city', 'label' => __('site.form.city'), 'required' => true, 'attrs' => 'autocomplete="address-level2" maxlength="100"'])
            @include('site.partials.field', ['name' => 'district', 'label' => __('site.form.district'), 'required' => true, 'attrs' => 'maxlength="100"'])
            @include('site.partials.field', ['name' => 'state', 'label' => __('site.form.state'), 'required' => true, 'attrs' => 'autocomplete="address-level1" maxlength="100"'])
            @include('site.partials.field', ['name' => 'pincode', 'label' => __('site.form.pincode'), 'required' => true, 'attrs' => 'autocomplete="postal-code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}"'])
          </div>

          <button type="submit" class="kc-btn kc-btn-gradient" style="margin-top:8px;">{{ __('site.common.submit') }}</button>
        </form>
      </div>
    </div>
    @include('site.partials.sidebar', ['extra' => 'site.partials.sidebar-checklist'])
  </div>
</section>
@endsection
