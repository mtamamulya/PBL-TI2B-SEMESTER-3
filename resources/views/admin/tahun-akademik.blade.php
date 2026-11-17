@extends('layouts.admin')

@section('title', 'Tahun Akademik')

@section('content')
<div class="px-6 py-6 sm:px-8 space-y-6" x-data="{
    semester: '{{ old('semester', 'Ganjil') }}',
    tahunAjaran: '{{ old('tahun_ajaran', date('Y') . '/' . (date('Y') + 1)) }}',
    get previewLabel() {
        return `${this.semester} ${this.tahunAjaran}`;
    }
}">
    {{-- Header & Breadcrumb (Selaras dengan halaman Kurikulum TRK) --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900">Tahun Akademik</h1>
            <nav aria-label="Breadcrumb" class="mt-1 flex items-center gap-2 text-base sm:text-lg font-medium text-slate-400">
                <a href="{{ route($isAdmin ? 'admin.dashboard' : 'kaprodi.dashboard') }}" class="transition hover:text-slate-600">
                    Dashboard
                </a>
                <span>/</span>
                <a href="{{ route($isAdmin ? 'admin.kurikulum.trk' : 'kaprodi.kurikulum.trk') }}" class="transition hover:text-slate-600">
                    Kurikulum
                </a>
                <span>/</span>
                <span class="text-slate-400">Tahun Akademik</span>
            </nav>
        </div>

        @if($tahunAkademikAktif)
            <div class="inline-flex items-center gap-2 self-start rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 sm:self-auto">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                <span>Aktif saat ini: <strong>{{ $tahunAkademikAktif['tahun_akademik'] }}</strong></span>
            </div>
        @endif
    </div>

    {{-- Alert Sukses / Error --}}
    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 {{ $isAdmin ? 'lg:grid-cols-12' : '' }}">
        {{-- Form Tambah Tahun Akademik (Khusus Admin; Kaprodi Read-Only) --}}
        @if($isAdmin)
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-4">
                <h2 class="text-lg font-semibold text-slate-900">Tambah Tahun Akademik</h2>
                <p class="mt-0.5 text-sm text-slate-400">
                    Periode baru otomatis aktif dan menonaktifkan periode sebelumnya
                </p>

                <form action="{{ route('admin.kurikulum.tahun-akademik.store') }}" method="POST"
                      onsubmit="return confirm('Tambahkan dan aktifkan tahun akademik ini? Tahun akademik sebelumnya akan otomatis dinonaktifkan dan data ini tidak dapat diubah kembali.')"
                      class="mt-5 space-y-4">
                    @csrf

                    <div>
                        <label for="semester" class="block text-sm font-medium text-slate-700">Semester</label>
                        <select id="semester" name="semester" x-model="semester"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-800 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">
                            <option value="Ganjil">Ganjil</option>
                            <option value="Genap">Genap</option>
                        </select>
                    </div>

                    <div>
                        <label for="tahun_ajaran" class="block text-sm font-medium text-slate-700">Tahun Ajaran</label>
                        <input type="text" id="tahun_ajaran" name="tahun_ajaran" x-model="tahunAjaran"
                               placeholder="2026/2027"
                               class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-800 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
                               required>
                        <p class="mt-1 text-xs text-slate-400">Format: YYYY/YYYY (contoh: 2026/2027 atau 2027/2028)</p>
                    </div>

                    <div class="rounded-xl bg-slate-50 px-3.5 py-2.5 text-xs text-slate-600">
                        Pratinjau: <strong class="text-slate-900" x-text="previewLabel"></strong>
                    </div>

                    <button type="submit"
                            class="w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                        Simpan &amp; Aktifkan
                    </button>
                </form>
            </div>
        @endif

        {{-- Daftar Riwayat Tahun Akademik --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm {{ $isAdmin ? 'lg:col-span-8' : '' }}">
            <div class="mb-5 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Daftar Tahun Akademik</h2>
                    <p class="text-sm text-slate-400">Periode semester berjalan dan riwayat tahun akademik program studi</p>
                </div>
                <span class="text-xs font-medium text-slate-400">Total: {{ count($tahunAkademikList) }} periode</span>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-700">
                        <tr>
                            <th class="w-16 px-5 py-3.5">No</th>
                            <th class="px-5 py-3.5">Tahun Akademik</th>
                            <th class="w-40 px-5 py-3.5">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($tahunAkademikList as $index => $item)
                            @php
                                $rawStatus = strtolower((string) ($item['status'] ?? ''));
                                $isAktif = in_array($rawStatus, ['1', 'true', 'aktif'], true);
                            @endphp
                            <tr class="transition hover:bg-slate-50/70">
                                <td class="px-5 py-4 text-slate-600">{{ $index + 1 }}</td>
                                <td class="px-5 py-4 font-medium text-slate-800">{{ $item['tahun_akademik'] }}</td>
                                <td class="px-5 py-4">
                                    @if ($isAktif)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-600">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-500">
                                            Tidak Aktif
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-10 text-center text-slate-400">
                                    Belum ada data tahun akademik.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection