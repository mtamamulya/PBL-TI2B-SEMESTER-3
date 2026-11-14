@extends('layouts.admin')

@section('title', 'Detail Dosen')

@php
    $id         = $dosenId ?? ($dosen['id'] ?? request()->route('id'));
    $tag        = 'inline-flex items-center rounded-lg border border-[#AAC8F8] bg-[#CDDEF9] px-2 py-0.5 text-xs font-medium text-[#2277FF]';
    $cardShadow = 'shadow-[1px_0_3px_rgba(0,0,0,0.02),5px_2px_6px_rgba(0,0,0,0.02),12px_4px_8px_rgba(0,0,0,0.01)]';
@endphp

@section('content')
<main class="flex flex-col gap-8 px-4 py-8 sm:px-6">

    @if (session('status'))
        <div class="rounded-md border border-[#D6EDB5] bg-[#E6F3D3] px-4 py-3 text-sm font-medium text-[#4C7D08]" role="status">{{ session('status') }}</div>
    @endif

    <div id="api-error" class="hidden rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert"></div>

    {{-- Judul + tombol --}}
    <div class="flex flex-wrap items-center gap-2">
        <div class="flex min-w-[240px] flex-1 flex-col gap-2">
            <h1 class="text-[32px] font-bold leading-[38px] tracking-[-0.2px]">Detail Dosen</h1>
            <p class="text-xl font-medium text-[#A8A8A8] sm:text-2xl">Dashboard / Data Dosen / Detail</p>
        </div>
        <a href="{{ route('admin.dosen') }}" class="flex items-center gap-1 rounded-md bg-[#CDDEF9] px-4 py-2 text-lg font-medium text-[#2277FF] shadow-sm">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6l6 6" /></svg>
            Kembali
        </a>
        <a href="{{ route('admin.dosen.edit', $id) }}" class="flex items-center gap-1 rounded-md bg-[#2277FF] px-4 py-2 text-lg font-medium text-[#FEFEFE] shadow-sm">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" /><path d="M16 5l3 3" /></svg>
            Edit Dosen
        </a>
    </div>

    {{-- Profil singkat + info akun --}}
    <section class="flex flex-col gap-6 rounded-lg bg-white px-4 pb-6 pt-4">
        <div class="flex items-center gap-6">
            <div id="profil-avatar" class="flex size-16 shrink-0 items-center justify-center rounded-full bg-[#D9D9D9] text-2xl font-semibold text-[#6B6B6B]" aria-hidden="true">D</div>
            <div class="flex flex-col gap-1">
                <div class="flex flex-wrap items-center gap-x-12 gap-y-1">
                    <span id="profil-nama" class="text-xl font-semibold text-[#2E2E2E]">Memuat data...</span>
                    <span id="profil-status" class="hidden rounded-lg border px-2 py-0.5 text-xs font-medium"></span>
                </div>
                <span id="profil-nip" class="text-base font-medium tracking-[-0.18px] text-[#A8A8A8]">NIP -</span>
            </div>
        </div>

        <div id="info-grid" class="grid gap-4 rounded bg-[#2277FF]/[0.06] p-4 sm:grid-cols-2 xl:grid-cols-4 xl:gap-11">
            <div class="col-span-full text-sm text-[#80838D]">Memuat informasi akun...</div>
        </div>
    </section>

    {{-- Riwayat pangkat dan golongan --}}
    <section class="flex flex-col gap-8 rounded-lg bg-white px-4 pb-6 pt-4 {{ $cardShadow }}">
        <h2 class="text-xl font-medium">Riwayat Pangkat dan Golongan</h2>
        <div class="overflow-x-auto rounded-md border border-[rgba(0,0,47,0.15)] bg-white/80">
            <table class="w-full min-w-[640px] text-sm text-[#1C2024]">
                <thead>
                    <tr class="bg-[rgba(0,0,85,0.02)] text-left font-medium">
                        <th class="min-h-9 p-2 font-medium">Pangkat</th>
                        <th class="min-h-9 p-2 font-medium">Golongan</th>
                        <th class="min-h-9 p-2 font-medium">Nomor SK</th>
                        <th class="min-h-9 p-2 font-medium">Tanggal SK</th>
                    </tr>
                </thead>
                <tbody id="riwayat-tbody">
                    <tr class="border-t border-[rgba(0,0,47,0.15)]">
                        <td colspan="4" class="p-4 text-center text-[#80838D]">Memuat riwayat pangkat...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    {{-- Aktivitas dosen (tab dinamis) --}}
    <section class="flex flex-col gap-8 rounded-lg bg-white px-4 pb-6 pt-4 {{ $cardShadow }}">
        <h2 class="text-xl font-medium">Aktivitas Dosen</h2>
        <div id="tabs" role="tablist" class="flex h-8 items-center gap-6 overflow-x-auto border-b border-[rgba(10,10,10,0.06)]"></div>
        <div id="aktivitas" role="tabpanel" class="flex flex-col">
            <p class="p-3 text-sm text-[#80838D]">Memuat aktivitas...</p>
        </div>
    </section>
</main>
@endsection

@push('scripts')
<script>
(async function () {
    var API_URL = @js(route('api.dosen.show', $id));
    var TAG = @js($tag);
    var tabAwal = @js(request('tab', ''));

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    try {
        var res = await fetch(API_URL, {
            headers: { 'Accept': 'application/json' }
        });
        var json = await res.json();

        if (!res.ok || !json.data) {
            var errBox = document.getElementById('api-error');
            errBox.textContent = json.message || 'Gagal memuat detail dosen.';
            errBox.classList.remove('hidden');
            return;
        }

        var profil = json.data.profil;
        var riwayat = json.data.riwayat || [];
        var aktivitas = json.data.aktivitas || {};

        // 1. Render Profil Singkat
        var avatar = document.getElementById('profil-avatar');
        if (profil.foto) {
            avatar.outerHTML = '<img id="profil-avatar" src="' + esc(profil.foto) + '" alt="Foto ' + esc(profil.nama) + '" class="size-16 shrink-0 rounded-full object-cover">';
        } else {
            avatar.textContent = (profil.nama || 'D').trim().charAt(0).toUpperCase();
        }

        document.getElementById('profil-nama').textContent = profil.nama || '-';
        document.getElementById('profil-nip').textContent = 'NIP ' + (profil.nip || '-');

        var badge = document.getElementById('profil-status');
        var isAktif = profil.status === 'aktif';
        badge.textContent = isAktif ? 'Aktif' : 'Nonaktif';
        badge.className = 'rounded-lg border px-2 py-0.5 text-xs font-medium ' +
            (isAktif ? 'border-[#D6EDB5] bg-[#E6F3D3] text-[#4C7D08]' : 'border-[#E4E4E7] bg-[#EDEDF0] text-[#55555C]');

        // 2. Render 4 Kotak Info Akun
        var infoList = [
            ['Email akun', profil.email || '-'],
            ['Role', profil.role || 'Dosen'],
            ['Pangkat / golongan saat ini', (profil.jabatan || '-') + ', ' + (profil.golongan || '-')],
            ['Akun login', profil.status_akun === 'aktif' ? 'Aktif' : 'Nonaktif']
        ];

        document.getElementById('info-grid').innerHTML = infoList.map(function (item) {
            return '<div class="flex flex-col gap-1.5 text-base tracking-[-0.18px]">' +
                '<span class="font-medium text-[#A8A8A8]">' + esc(item[0]) + '</span>' +
                '<span class="break-words font-semibold text-[#2E2E2E]">' + esc(item[1]) + '</span>' +
            '</div>';
        }).join('');

        // 3. Render Tabel Riwayat Pangkat
        var tbody = document.getElementById('riwayat-tbody');
        tbody.innerHTML = riwayat.length ? riwayat.map(function (r) {
            return '<tr class="border-t border-[rgba(0,0,47,0.15)]">' +
                '<td class="p-2"><div class="flex items-center gap-2">' + esc(r.pangkat) +
                    (r.now ? '<span class="' + TAG + '">Saat ini</span>' : '') +
                '</div></td>' +
                '<td class="p-2">' + esc(r.golongan) + '</td>' +
                '<td class="p-2">' + esc(r.sk) + '</td>' +
                '<td class="p-2">' + esc(r.tanggal_formatted || r.tanggal) + '</td>' +
            '</tr>';
        }).join('') : '<tr class="border-t border-[rgba(0,0,47,0.15)]"><td colspan="4" class="p-4 text-center text-[#80838D]">Belum ada riwayat pangkat.</td></tr>';

        // 4. Render Tab & Panel Aktivitas
        var keys = Object.keys(aktivitas);
        var activeKey = aktivitas[tabAwal] ? tabAwal : (keys[0] || '');
        var tabsWrap = document.getElementById('tabs');
        var panel = document.getElementById('aktivitas');

        tabsWrap.innerHTML = keys.map(function (k) {
            var a = aktivitas[k];
            var selected = k === activeKey ? 'true' : 'false';
            return '<button type="button" role="tab" data-tab="' + esc(k) + '" aria-selected="' + selected + '" ' +
                'class="group relative flex h-full shrink-0 items-center gap-2 px-1 text-xs text-[#0A0A0A]/30 transition-colors hover:text-[#0A0A0A]/60 aria-selected:font-medium aria-selected:text-[#0A0A0A]">' +
                '<span class="p-1">' + esc(a.label) + '</span>' +
                '<span class="hidden rounded-lg bg-[#2277FF] px-2 py-0.5 text-xs font-medium text-[#FEFEFE] group-aria-selected:inline">' + (a.items ? a.items.length : 0) + '</span>' +
                '<span class="absolute inset-x-0 bottom-0 hidden h-0.5 bg-[#2277FF] group-aria-selected:block"></span>' +
            '</button>';
        }).join('');

        var tabBtns = Array.prototype.slice.call(tabsWrap.querySelectorAll('[data-tab]'));

        function showTab(key, push) {
            if (!aktivitas[key]) return;
            tabBtns.forEach(function (b) { b.setAttribute('aria-selected', b.dataset.tab === key ? 'true' : 'false'); });

            var items = aktivitas[key].items || [];
            panel.innerHTML = items.length ? items.map(function (p, i) {
                return '<div>' + (i ? '<div class="h-px bg-[rgba(10,10,10,0.06)]"></div>' : '') +
                    '<div class="flex items-center gap-4 p-3">' +
                        '<div class="flex min-w-0 flex-1 flex-col gap-1">' +
                            '<span class="text-sm font-medium tracking-[-0.16px]">' + esc(p.title) + '</span>' +
                            '<div class="flex items-center gap-1 text-xs text-[#7F7F7F]">' + esc(p.year) +
                                '<span class="size-1 rounded-full bg-[#7F7F7F]"></span>' + esc(p.type) + '</div>' +
                        '</div>' +
                        (p.role ? '<span class="' + TAG + '">' + esc(p.role) + '</span>' : '') +
                    '</div></div>';
            }).join('') : '<p class="p-3 text-sm text-[#80838D]">Belum ada data ' + esc(aktivitas[key].label.toLowerCase()) + '.</p>';

            if (push) history.replaceState(null, '', '?tab=' + key);
        }

        tabBtns.forEach(function (b) {
            b.addEventListener('click', function () { showTab(b.dataset.tab, true); });
        });

        if (activeKey) showTab(activeKey, false);

    } catch (e) {
        var errBox = document.getElementById('api-error');
        errBox.textContent = 'Gagal terhubung ke server API.';
        errBox.classList.remove('hidden');
    }
})();
</script>
@endpush