{{--
    resources/views/admin/mahasiswa-detail.blade.php
    Detail mahasiswa -> mengambil data dari GET /api/v1/mahasiswa/{id}.
--}}
@extends('layouts.admin')

@section('title', 'Detail Mahasiswa')

@php
    $mhsId    = $mahasiswa['id'] ?? request()->route('id');
    $mahasiswa = $mahasiswa ?? [];
    $academic  = $academic ?? [];
    $history   = $history ?? [];
    $kegiatan  = $kegiatan ?? [];

    $tag        = 'inline-flex items-center rounded-lg border border-[#AAC8F8] bg-[#CDDEF9] px-2 py-0.5 text-xs font-medium leading-4 tracking-[-0.12px] text-[#2277FF]';
    $cardShadow = 'shadow-[1px_0_3px_rgba(0,0,0,0.02),5px_2px_6px_rgba(0,0,0,0.02)]';

    $tabs = [
        'enrollment' => ['label' => 'Riwayat enrollment', 'count' => count($history)],
        'kegiatan'   => ['label' => 'Kegiatan diikuti',   'count' => count($kegiatan)],
    ];
    $tabAwal = array_key_exists(request('tab', ''), $tabs) ? request('tab') : 'enrollment';
@endphp

@section('content')
<main class="flex flex-col gap-8 px-4 py-8 sm:px-6">

    {{-- Judul + tombol --}}
    <div class="flex flex-wrap items-center gap-2">
        <div class="flex min-w-[240px] flex-1 flex-col gap-2">
            <h1 class="text-[32px] font-bold leading-[38px] tracking-[-0.2px]">Detail Mahasiswa</h1>
            <div class="text-2xl font-medium leading-[30px] tracking-[-0.15px] text-[#A8A8A8]">Dashboard / Mahasiswa / Detail</div>
        </div>
        <a href="{{ route('admin.mahasiswa', [], false) }}" class="flex items-center justify-center gap-1 rounded-md bg-[#CDDEF9] px-4 py-2 text-lg font-medium tracking-[-0.18px] text-[#2277FF] shadow-[0_1px_2px_rgba(0,0,0,0.12)]">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6l6 6" /></svg>
            Kembali
        </a>
        <a href="{{ route('admin.mahasiswa.edit', ['id' => $mhsId], false) }}" class="flex items-center justify-center gap-1 rounded-md bg-[#2277FF] px-4 py-2 text-lg font-medium tracking-[-0.18px] text-[#FEFEFE] shadow-[0_1px_2px_rgba(0,0,0,0.12)]">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4l10.5 -10.5a2.828 2.828 0 1 0 -4 -4l-10.5 10.5v4" /><path d="M13.5 6.5l4 4" /></svg>
            Edit Mahasiswa
        </a>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-[#D6EDB5] bg-[#E6F3D3] px-4 py-3 text-sm font-medium text-[#4C7D08]" role="status">{{ session('status') }}</div>
    @endif

    <div id="apiError" class="hidden rounded-md border border-[#FFD9D9] bg-[#FFEDED] px-4 py-3 text-sm text-[#E5484D]" role="alert"></div>

    {{-- Profil + informasi --}}
    <section class="flex flex-col gap-[42px] rounded-lg bg-white px-4 pb-6 pt-4">
        <div class="flex items-center gap-6">
            <div id="mhs-avatar-wrap">
                @if(!empty($mahasiswa['foto']))
                    <img src="{{ Storage::disk('public')->url($mahasiswa['foto']) }}" alt="Foto {{ $mahasiswa['nama'] ?? '' }}" class="size-16 shrink-0 rounded-full border border-gray-200 object-cover">
                @else
                    <div class="flex size-16 shrink-0 items-center justify-center rounded-full bg-[#D9D9D9] text-2xl font-semibold text-[#6B6B6B]" aria-hidden="true">{{ mb_strtoupper(mb_substr($mahasiswa['nama'] ?? 'M', 0, 1)) }}</div>
                @endif
            </div>
            <div class="flex flex-col gap-1">
                <div class="flex flex-wrap items-center gap-3">
                    <div id="mhs-nama" class="text-xl font-semibold leading-6">{{ $mahasiswa['nama'] ?? 'Memuat...' }}</div>
                    <span id="mhs-status" @class([
                        'rounded-lg border px-2 py-0.5 text-xs font-medium leading-4 tracking-[-0.12px]',
                        'border-[#D6EDB5] bg-[#E6F3D3] text-[#4C7D08]' => ($mahasiswa['status'] ?? '') === 'Aktif',
                        'border-[#FAD4A8] bg-[#FBE5CB] text-[#854700]' => ($mahasiswa['status'] ?? '') === 'Cuti',
                        'border-[#E4E4E7] bg-[#EDEDF0] text-[#55555C]' => ! in_array($mahasiswa['status'] ?? '', ['Aktif', 'Cuti']),
                    ])>{{ $mahasiswa['status'] ?? '-' }}</span>
                </div>
                <div id="mhs-nim" class="text-base font-medium tracking-[-0.18px] text-[#A8A8A8]">NIM {{ $mahasiswa['nim'] ?? '-' }}</div>
            </div>
        </div>

        <div class="flex w-full flex-col gap-3">
            <div class="text-base font-medium text-[#2E2E2E]">Informasi akademik</div>
            <div id="mhs-academic" class="flex flex-col gap-6 rounded-lg bg-[rgba(34,119,255,0.06)] p-5 sm:flex-row sm:items-center sm:justify-between">
                @foreach ($academic as [$k, $v])
                    <div class="flex flex-col gap-1.5">
                        <div class="text-base font-medium text-[#A8A8A8]">{{ $k }}</div>
                        <div class="whitespace-nowrap text-base font-semibold text-[#2E2E2E]">{{ $v }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Riwayat --}}
    <section class="flex flex-col gap-8 rounded-lg bg-white px-4 pb-6 pt-4 {{ $cardShadow }}">
        <div class="text-xl font-medium leading-6">Riwayat Akademik</div>

        <div role="tablist" class="flex h-8 items-stretch gap-6 overflow-x-auto border-b border-[rgba(10,10,10,0.06)] text-xs">
            @foreach ($tabs as $key => $t)
                <button type="button" role="tab" data-tab="{{ $key }}" aria-selected="{{ $key === $tabAwal ? 'true' : 'false' }}"
                        class="group relative flex shrink-0 items-center gap-2 px-1 tracking-[-0.12px] text-[rgba(10,10,10,0.3)] transition-colors hover:text-[rgba(10,10,10,0.6)] aria-selected:font-medium aria-selected:text-[#0A0A0A]">
                    <span class="p-1">{{ $t['label'] }}</span>
                    <span id="count-{{ $key }}" class="hidden rounded-lg bg-[#2277FF] px-2 py-0.5 leading-4 text-[#FEFEFE] group-aria-selected:inline">{{ $t['count'] }}</span>
                    <span class="absolute inset-x-0 bottom-0 hidden h-0.5 bg-[#2277FF] group-aria-selected:block"></span>
                </button>
            @endforeach
        </div>

        {{-- Panel 1: riwayat enrollment --}}
        <div data-panel="enrollment" @class(['overflow-x-auto rounded-md border border-[rgba(0,0,47,0.15)] bg-white/80', 'hidden' => $tabAwal !== 'enrollment'])>
            <table class="w-full min-w-[800px] border-collapse text-left text-sm text-[#1C2024]">
                <thead class="bg-[rgba(0,0,85,0.02)]">
                    <tr>
                        @foreach (['Tahun akademik', 'Semester', 'Program studi', 'Tingkat', 'Kelas'] as $h)
                            <th class="min-h-9 p-2 text-sm font-medium">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody id="tbody-enrollment">
                    @forelse ($history as $r)
                        <tr class="border-t border-[rgba(0,0,47,0.15)]">
                            <td class="p-2">
                                <div class="flex items-center gap-2">
                                    {{ $r['year'] }}
                                    @if (! empty($r['current']))
                                        <span class="{{ $tag }}">Saat ini</span>
                                    @endif
                                </div>
                            </td>
                            <td class="p-2">{{ $r['sem'] }}</td>
                            <td class="p-2">{{ $r['prodi'] }}</td>
                            <td class="p-2">{{ $r['level'] }}</td>
                            <td class="p-2">{{ $r['cls'] }}</td>
                        </tr>
                    @empty
                        <tr class="border-t border-[rgba(0,0,47,0.15)]">
                            <td colspan="5" class="p-4 text-center text-[#80838D]">Belum ada riwayat enrollment.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Panel 2: kegiatan diikuti --}}
        <div id="panel-kegiatan" data-panel="kegiatan" @class(['flex flex-col', 'hidden' => $tabAwal !== 'kegiatan'])>
            @forelse ($kegiatan as $k)
                <div>
                    @if (! $loop->first)
                        <div class="h-px bg-[rgba(10,10,10,0.06)]"></div>
                    @endif
                    <div class="flex items-center gap-4 p-3">
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="text-sm font-medium tracking-[-0.16px]">{{ $k['title'] }}</span>
                            <div class="flex items-center gap-1 text-xs text-[#7F7F7F]">
                                {{ $k['year'] }}
                                <span class="size-1 rounded-full bg-[#7F7F7F]"></span>
                                {{ $k['type'] }}
                            </div>
                        </div>
                        <span class="{{ $tag }}">{{ $k['role'] }}</span>
                    </div>
                </div>
            @empty
                <p class="p-3 text-sm text-[#80838D]">Belum ada kegiatan yang diikuti.</p>
            @endforelse
        </div>
    </section>
</main>
@endsection

@push('scripts')
<script>
(function () {
    var mhsId = @json($mhsId);
    var TAG_CLASS = @json($tag);

    // ---------- Tab Switcher ----------
    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-tab]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-panel]'));

    function show(key, push) {
        tabs.forEach(function (t) { t.setAttribute('aria-selected', t.dataset.tab === key ? 'true' : 'false'); });
        panels.forEach(function (p) { p.classList.toggle('hidden', p.dataset.panel !== key); });
        if (push) history.replaceState(null, '', '?tab=' + key);
    }

    tabs.forEach(function (t) {
        t.addEventListener('click', function () { show(t.dataset.tab, true); });
    });

    // ---------- Helper Escape HTML ----------
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function statusClass(status) {
        var base = 'rounded-lg border px-2 py-0.5 text-xs font-medium leading-4 tracking-[-0.12px] ';
        if (status === 'Aktif') return base + 'border-[#D6EDB5] bg-[#E6F3D3] text-[#4C7D08]';
        if (status === 'Cuti') return base + 'border-[#FAD4A8] bg-[#FBE5CB] text-[#854700]';
        return base + 'border-[#E4E4E7] bg-[#EDEDF0] text-[#55555C]';
    }

    // ---------- Ambil Data Detail dari REST API ----------
    if (!mhsId) return;

    fetch('/api/v1/mahasiswa/' + encodeURIComponent(mhsId), {
        headers: { 'Accept': 'application/json' }
    })
    .then(function (res) {
        return res.json().then(function (body) {
            if (!res.ok) throw new Error(body.message || 'Gagal memuat detail mahasiswa.');
            return body;
        });
    })
    .then(function (json) {
        var mhs = json.data || {};
        var academic = json.academic || [];
        var history = json.history || [];
        var kegiatan = json.kegiatan || [];

        // 1. Update Profil Utama
        document.getElementById('mhs-nama').textContent = mhs.nama || '-';
        document.getElementById('mhs-nim').textContent = 'NIM ' + (mhs.nim || '-');

        var badgeStatus = document.getElementById('mhs-status');
        badgeStatus.textContent = mhs.status || '-';
        badgeStatus.className = statusClass(mhs.status);

        var avatarWrap = document.getElementById('mhs-avatar-wrap');
        if (json.fotoUrl) {
            avatarWrap.innerHTML = '<img src="' + esc(json.fotoUrl) + '" alt="Foto ' + esc(mhs.nama) + '" class="size-16 shrink-0 rounded-full border border-gray-200 object-cover">';
        } else {
            var inisial = (mhs.nama || 'M').trim().charAt(0).toUpperCase();
            avatarWrap.innerHTML = '<div class="flex size-16 shrink-0 items-center justify-center rounded-full bg-[#D9D9D9] text-2xl font-semibold text-[#6B6B6B]" aria-hidden="true">' + esc(inisial) + '</div>';
        }

        // 2. Update Informasi Akademik
        var academicWrap = document.getElementById('mhs-academic');
        academicWrap.innerHTML = academic.map(function (pair) {
            return '<div class="flex flex-col gap-1.5">' +
                '<div class="text-base font-medium text-[#A8A8A8]">' + esc(pair[0]) + '</div>' +
                '<div class="whitespace-nowrap text-base font-semibold text-[#2E2E2E]">' + esc(pair[1]) + '</div>' +
            '</div>';
        }).join('');

        // 3. Update Badge Jumlah Tab
        document.getElementById('count-enrollment').textContent = history.length;
        document.getElementById('count-kegiatan').textContent = kegiatan.length;

        // 4. Update Tabel Riwayat Enrollment
        var tbodyEnrollment = document.getElementById('tbody-enrollment');
        if (history.length === 0) {
            tbodyEnrollment.innerHTML = '<tr class="border-t border-[rgba(0,0,47,0.15)]"><td colspan="5" class="p-4 text-center text-[#80838D]">Belum ada riwayat enrollment.</td></tr>';
        } else {
            tbodyEnrollment.innerHTML = history.map(function (r) {
                var badgeSaatIni = r.current ? ' <span class="' + TAG_CLASS + '">Saat ini</span>' : '';
                return '<tr class="border-t border-[rgba(0,0,47,0.15)]">' +
                    '<td class="p-2"><div class="flex items-center gap-2">' + esc(r.year) + badgeSaatIni + '</div></td>' +
                    '<td class="p-2">' + esc(r.sem) + '</td>' +
                    '<td class="p-2">' + esc(r.prodi) + '</td>' +
                    '<td class="p-2">' + esc(r.level) + '</td>' +
                    '<td class="p-2">' + esc(r.cls) + '</td>' +
                '</tr>';
            }).join('');
        }

        // 5. Update Daftar Kegiatan Diikuti
        var panelKegiatan = document.getElementById('panel-kegiatan');
        if (kegiatan.length === 0) {
            panelKegiatan.innerHTML = '<p class="p-3 text-sm text-[#80838D]">Belum ada kegiatan yang diikuti.</p>';
        } else {
            panelKegiatan.innerHTML = kegiatan.map(function (k, idx) {
                var divider = idx > 0 ? '<div class="h-px bg-[rgba(10,10,10,0.06)]"></div>' : '';
                return '<div>' + divider +
                    '<div class="flex items-center gap-4 p-3">' +
                        '<div class="flex min-w-0 flex-1 flex-col gap-1">' +
                            '<span class="text-sm font-medium tracking-[-0.16px]">' + esc(k.title) + '</span>' +
                            '<div class="flex items-center gap-1 text-xs text-[#7F7F7F]">' +
                                esc(k.year) + ' <span class="size-1 rounded-full bg-[#7F7F7F]"></span> ' + esc(k.type) +
                            '</div>' +
                        '</div>' +
                        '<span class="' + TAG_CLASS + '">' + esc(k.role) + '</span>' +
                    '</div>' +
                '</div>';
            }).join('');
        }
    })
    .catch(function (err) {
        var box = document.getElementById('apiError');
        box.textContent = err.message;
        box.classList.remove('hidden');
    });
})();
</script>
@endpush