@extends('site.layouts.app')

@section('title', __('site.registration.title'))

@section('content')
@include('site.partials.page-hero', ['title' => __('site.registration.title'), 'sub' => __('site.registration.sub')])

@php($chosen = (int) old('course_id', $selected))

<section class="ms-section ms-section--soft">
  <div class="kc-container" style="max-width:760px;">
    <div class="ms-form-card">
      <h2 class="ms-section-title" style="font-size:1.3rem;margin-top:0">{{ __('site.registration.form_title') }}</h2>
      @include('site.partials.form-status')
      <form method="POST" action="{{ route('site.students.registration.submit') }}" novalidate>
        @csrf
        @include('site.partials.honeypot')
        <div class="ms-form-grid">
          @include('site.partials.field', ['name' => 'name', 'label' => __('site.form.name'), 'required' => true, 'attrs' => 'autocomplete="name" maxlength="150"'])
          @include('site.partials.field', ['name' => 'phone', 'label' => __('site.form.phone'), 'type' => 'tel', 'required' => true, 'attrs' => 'autocomplete="tel" inputmode="tel" maxlength="20"'])
          @include('site.partials.field', ['name' => 'email', 'label' => __('site.form.email'), 'type' => 'email', 'attrs' => 'autocomplete="email"'])
          <div class="ms-form-group">
            <label class="ms-form-label" for="f-course_id">{{ __('site.form.course') }}</label>
            <select id="f-course_id" name="course_id" class="ms-form-select" data-searchable>
              <option value="">{{ __('site.registration.choose_course') }}</option>
              @foreach ($courses as $course)
                <option value="{{ $course->id }}" @selected($chosen === $course->id)>{{ $course->code }} — {{ $course->localized('name') }}</option>
              @endforeach
            </select>
            @error('course_id')<div class="ms-field-error">{{ $message }}</div>@enderror
          </div>
          @include('site.partials.field', ['name' => 'message', 'label' => __('site.form.message'), 'type' => 'textarea', 'class' => 'ms-span-2', 'rows' => 3])
        </div>
        <button type="submit" class="kc-btn kc-btn-gradient">{{ __('site.common.submit') }}</button>
      </form>
    </div>
  </div>
</section>
@push('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.css">
@endpush
@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
  <script>
    // Type to search the course list; the plain <select> still works if the script cannot load.
    if (window.TomSelect) {
      document.querySelectorAll('select[data-searchable]').forEach(function (el) {
        new TomSelect(el, { create: false, allowEmptyOption: true, maxOptions: null });
      });
    }
  </script>
@endpush
@endsection
