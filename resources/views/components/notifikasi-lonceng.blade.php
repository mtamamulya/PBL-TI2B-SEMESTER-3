@props(['prefix' => 'admin'])

@php
    $jumlah = \App\Support\NotifikasiStaf::jumlahBelumDibaca($prefix);
    $href = \Illuminate\Support\Facades\Route::has($prefix.'.notifikasi')
        ? route($prefix.'.notifikasi')
        : '#';
    $labelAwal = ($jumlah ?? 0) > 0
        ? "Notifikasi, {$jumlah} belum dibaca"
        : 'Notifikasi';
@endphp

<a href="{{ $href }}"
   aria-label="{{ $labelAwal }}"
   :aria-label="n > 0 ? `Notifikasi, ${n} belum dibaca` : 'Notifikasi'"
   x-data="{ n: {{ (int) ($jumlah ?? 0) }} }"
   x-on:notif-count.window="n = $event.detail"
   class="relative ml-auto inline-flex size-9 min-h-[36px] min-w-[36px] shrink-0 items-center justify-center rounded-lg text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" />
        <path d="M9 17v1a3 3 0 0 0 6 0v-1" />
    </svg>

    <span class="sr-only" x-text="n > 0 ? `Notifikasi, ${n} belum dibaca` : 'Notifikasi'">{{ $labelAwal }}</span>

    @if ($jumlah !== null)
        <span data-notif-badge
              x-show="n > 0"
              @if ($jumlah <= 0) style="display: none;" @endif
              x-text="n > 99 ? '99+' : n"
              aria-hidden="true"
              class="pointer-events-none absolute -right-0.5 -top-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-[#EF4444] px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white">{{ $jumlah > 0 ? ($jumlah > 99 ? '99+' : $jumlah) : '' }}</span>
    @endif
</a>