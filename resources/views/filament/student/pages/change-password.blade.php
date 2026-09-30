<x-filament-panels::page>
    <form wire:submit="save" style="display:flex;flex-direction:column;gap:24px;max-width:480px">
        {{ $this->form }}
        <div>
            <x-filament::button type="submit">{{ __('portal.password.save') }}</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
