@php($type = $type ?? 'text')
@php($required = $required ?? false)
<div class="ms-form-group {{ $class ?? '' }}">
  <label class="ms-form-label" for="f-{{ $name }}">{{ $label }}@if ($required) <span aria-hidden="true" style="color:#e11d48">*</span>@endif</label>
  @if ($type === 'textarea')
    <textarea id="f-{{ $name }}" name="{{ $name }}" class="ms-form-textarea" rows="{{ $rows ?? 4 }}" @required($required)>{{ old($name) }}</textarea>
  @else
    <input id="f-{{ $name }}" name="{{ $name }}" type="{{ $type }}" class="ms-form-input" value="{{ old($name) }}" @required($required) {!! $attrs ?? '' !!}>
  @endif
  @error($name)<div class="ms-field-error">{{ $message }}</div>@enderror
</div>
