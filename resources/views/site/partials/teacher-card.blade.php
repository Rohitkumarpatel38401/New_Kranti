<div class="ms-teacher-card">
  <div class="ms-teacher-avatar">
    @if ($member->mediaUrl('photo_path'))
      <img src="{{ $member->mediaUrl('photo_path') }}" alt="{{ $member->name }}" loading="lazy">
    @else
      {{ \Illuminate\Support\Str::of($member->name)->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
    @endif
  </div>
  <div class="ms-teacher-info">
    <div class="ms-teacher-name">{{ $member->name }}</div>
    <div class="ms-teacher-subject">{{ $member->localized('designation') }}@if ($member->qualification) · {{ $member->qualification }}@endif</div>
    @if ($member->experience_years)
      <div class="ms-teacher-exp">⭐ {{ __('site.common.years_exp', ['years' => $member->experience_years]) }}</div>
    @endif
  </div>
</div>
