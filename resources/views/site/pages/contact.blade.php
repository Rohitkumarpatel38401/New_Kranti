@extends('site.layouts.app')

@section('title', __('site.contact.title'))

@section('content')
@include('site.partials.page-hero', ['title' => __('site.contact.title'), 'sub' => __('site.contact.sub')])

@php($map = $site->get('site.map_embed_url'))
@php($phones = array_filter([$site->get('site.phone'), $site->get('site.phone_alt')]))

<section class="ms-section ms-section--soft">
  <div class="kc-container">
    <div class="ms-grid-2" style="align-items:start;">
      <div class="ms-form-card">
        <h2 class="ms-section-title" style="font-size:1.3rem;margin-top:0">{{ __('site.contact.reach') }}</h2>
        <dl class="ms-dl">
          @if (filled($site->get('site.address')))
            <dt>{{ __('site.contact.address') }}</dt><dd>{!! nl2br(e($site->get('site.address'))) !!}</dd>
          @endif
          @if ($phones)
            <dt>{{ __('site.contact.phone') }}</dt>
            <dd>@foreach ($phones as $phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a>@if (! $loop->last), @endif @endforeach</dd>
          @endif
          @if (filled($site->get('site.email')))
            <dt>{{ __('site.contact.email') }}</dt><dd><a href="mailto:{{ $site->get('site.email') }}">{{ $site->get('site.email') }}</a></dd>
          @endif
        </dl>
        {{-- Only Google Maps embeds are framed; anything else in the setting is ignored. --}}
        @if (is_string($map) && \Illuminate\Support\Str::startsWith($map, 'https://www.google.com/maps/embed'))
          <iframe class="ms-map" src="{{ $map }}" style="margin-top:20px;" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="{{ __('site.contact.address') }}"></iframe>
        @endif
      </div>

      <div class="ms-form-card">
        <h2 class="ms-section-title" style="font-size:1.3rem;margin-top:0">{{ __('site.contact.form_title') }}</h2>
        @include('site.partials.form-status')
        <form method="POST" action="{{ route('site.contact.submit') }}" novalidate>
          @csrf
          @include('site.partials.honeypot')
          <div class="ms-form-grid">
            @include('site.partials.field', ['name' => 'name', 'label' => __('site.form.name'), 'required' => true, 'attrs' => 'autocomplete="name" maxlength="150"'])
            @include('site.partials.field', ['name' => 'phone', 'label' => __('site.form.phone'), 'type' => 'tel', 'required' => true, 'attrs' => 'autocomplete="tel" inputmode="tel" maxlength="20"'])
            @include('site.partials.field', ['name' => 'email', 'label' => __('site.form.email'), 'type' => 'email', 'class' => 'ms-span-2', 'attrs' => 'autocomplete="email"'])
            @include('site.partials.field', ['name' => 'message', 'label' => __('site.form.message'), 'type' => 'textarea', 'required' => true, 'class' => 'ms-span-2'])
          </div>
          <button type="submit" class="kc-btn kc-btn-gradient">{{ __('site.form.send') }}</button>
        </form>
      </div>
    </div>
  </div>
</section>
@endsection
