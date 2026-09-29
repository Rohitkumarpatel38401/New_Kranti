@extends('site.layouts.app')

@section('title', __('site.gallery.title'))

@section('content')
@include('site.partials.page-hero', ['title' => __('site.gallery.title'), 'sub' => __('site.gallery.sub')])

<section class="ms-section ms-section--white">
  <div class="kc-container">
    @if ($items->isEmpty())
      <div class="ms-empty">{{ __('site.gallery.empty') }}</div>
    @else
      @php($categories = $items->pluck('category')->filter()->unique()->values())
      @if ($categories->count() > 1)
        <div class="ms-chip-row" id="msGalleryFilters">
          <button type="button" class="ms-filter-btn active" data-filter="">{{ __('site.courses.all') }}</button>
          @foreach ($categories as $category)
            <button type="button" class="ms-filter-btn" data-filter="{{ $category }}">{{ \Illuminate\Support\Str::title($category) }}</button>
          @endforeach
        </div>
      @endif
      <div class="ms-grid-3" id="msGallery">
        @foreach ($items as $item)
          <figure class="ms-gallery-item" data-category="{{ $item->category }}" style="margin:0;aspect-ratio:4/3;">
            <a href="{{ $item->mediaUrl() }}" target="_blank" rel="noopener">
              <img class="ms-media-img" src="{{ $item->mediaUrl() }}" alt="{{ $item->localized('title') }}" loading="lazy">
            </a>
            <figcaption class="ms-card-text" style="margin-top:6px;">{{ $item->localized('title') }}</figcaption>
          </figure>
        @endforeach
      </div>
    @endif
  </div>
</section>

@push('scripts')
<script>
  document.querySelectorAll('#msGalleryFilters [data-filter]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('#msGalleryFilters [data-filter]').forEach(b => b.classList.toggle('active', b === btn));
      document.querySelectorAll('#msGallery [data-category]').forEach(function (item) {
        item.style.display = !btn.dataset.filter || item.dataset.category === btn.dataset.filter ? '' : 'none';
      });
    });
  });
</script>
@endpush
@endsection
