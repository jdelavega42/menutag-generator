@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="MenuTag Studio" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-surface-2">
            <x-app-logo-icon class="size-5 text-text-primary" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="MenuTag Studio" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-surface-2">
            <x-app-logo-icon class="size-5 text-text-primary" />
        </x-slot>
    </flux:brand>
@endif
