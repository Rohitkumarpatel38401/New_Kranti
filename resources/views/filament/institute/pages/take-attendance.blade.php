<x-filament-panels::page>
    <x-filament::section>
        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:end">
            <label style="display:flex;flex-direction:column;gap:4px;min-width:240px">
                <span class="fi-fo-field-label-content">Batch</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="batchId">
                        <option value="">Choose a batch</option>
                        @foreach ($this->getBatchOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label style="display:flex;flex-direction:column;gap:4px">
                <span class="fi-fo-field-label-content">Date</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="date" max="{{ now()->toDateString() }}" />
                </x-filament::input.wrapper>
            </label>
        </div>
    </x-filament::section>

    @if (count($roster))
        <x-filament::section>
            <x-slot name="heading">{{ count($roster) }} students</x-slot>
            <x-slot name="headerEnd">
                <x-filament::button size="sm" color="gray" wire:click="markAll('present')">All present</x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="markAll('absent')">All absent</x-filament::button>
            </x-slot>

            <table style="width:100%;border-collapse:collapse">
                <tbody>
                @foreach ($roster as $row)
                    <tr wire:key="row-{{ $row['id'] }}" style="border-bottom:1px solid rgba(127,127,127,.2)">
                        <td style="padding:8px 4px">
                            <div style="font-weight:600">{{ $row['name'] }}</div>
                            <div style="font-size:12px;opacity:.7">{{ $row['reg'] }}</div>
                        </td>
                        <td style="padding:8px 4px;text-align:right">
                            <div style="display:inline-flex;gap:12px;flex-wrap:wrap">
                                @foreach ($this->getStatuses() as $value => $label)
                                    <label style="display:inline-flex;gap:4px;align-items:center;cursor:pointer">
                                        <input type="radio" value="{{ $value }}" wire:model="marks.{{ $row['id'] }}">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div style="margin-top:16px">
                <x-filament::button wire:click="save">Save attendance</x-filament::button>
            </div>
        </x-filament::section>
    @elseif ($batchId)
        <x-filament::section>No active students in this batch.</x-filament::section>
    @endif
</x-filament-panels::page>
