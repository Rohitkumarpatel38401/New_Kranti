@extends('site.layouts.app')

@section('title', __('site.download.title'))

@section('content')
@include('site.partials.page-hero', ['title' => __('site.download.title'), 'sub' => __('site.download.sub')])

<section class="ms-section ms-section--soft">
  <div class="kc-container">
    @if ($downloads->isEmpty())
      <div class="ms-empty">{{ __('site.download.empty') }}</div>
    @else
      <div class="ms-form-card ms-table-wrap">
        <table class="ms-table">
          <thead>
            <tr><th>{{ __('site.download.file') }}</th><th>{{ __('site.download.category') }}</th><th></th></tr>
          </thead>
          <tbody>
            @foreach ($downloads as $download)
              <tr>
                <td>📄 {{ $download->localized('title') }}</td>
                <td>{{ $download->category ? \Illuminate\Support\Str::title($download->category) : '—' }}</td>
                <td style="text-align:right;">
                  <a href="{{ $download->mediaUrl('path') }}" class="kc-btn kc-btn-outline-indigo" style="height:34px;padding:0 16px;font-size:0.8rem;" download>{{ __('site.download.get') }}</a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</section>
@endsection
