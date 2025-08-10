<x-filament-panels::page>
    <div>
        <form wire:submit="submit">
            {{ $this->form }}

            <x-filament::button
                type="button"
                color="gray" 
                wire:click="resetImport"
                class="mt-4"
            >
                Reset Import
            </x-filament::button>
        </form>

        <x-filament-actions::modals />
    </div>
</x-filament-panels::page>