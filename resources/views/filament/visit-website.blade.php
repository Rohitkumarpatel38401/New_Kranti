{{-- Topbar: open the public website in a new tab (next to the notification bell and profile menu). --}}
<x-filament::icon-button
    tag="a"
    :href="url('/')"
    target="_blank"
    rel="noopener"
    icon="heroicon-o-globe-alt"
    color="gray"
    size="lg"
    label="Visit website"
    tooltip="Visit website"
/>
