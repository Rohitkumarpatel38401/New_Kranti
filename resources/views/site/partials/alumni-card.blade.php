<div class="ms-alumni-card">
  <div class="ms-alumni-photo-wrap">
    <img src="{{ $t->mediaUrl('photo_path') ?: asset('images/site/photo.jpg') }}" alt="{{ $t->name }}" class="ms-alumni-photo" loading="lazy">
    <div class="ms-alumni-shine"></div>
  </div>
  <div class="ms-alumni-footer">
    <div class="ms-alumni-footer-name">{{ $t->name }}</div>
    <div class="ms-alumni-footer-course">{{ collect([$t->course_name, collect([$t->position, $t->company])->filter()->implode(', ')])->filter()->implode(' · ') }}</div>
  </div>
</div>
