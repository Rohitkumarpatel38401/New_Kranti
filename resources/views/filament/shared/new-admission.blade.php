<x-filament-panels::page>
    <form wire:submit="save" style="display:flex;flex-direction:column;gap:24px">
        {{ $this->form }}
        <div>
            <x-filament::button type="submit" icon="heroicon-o-check">Admit student</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
