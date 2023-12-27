<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center gap-x-3">
            <div class="flex-1">
                @if ($cabang)
                    <h1 class="font-bold">BWI Cabang {{ $cabang->nama_cabang }}</h1>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $cabang->lokasi }}
                    </p>
                @else
                    <h1 class="font-bold">BWI Pusat</h1>
                @endif  
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
