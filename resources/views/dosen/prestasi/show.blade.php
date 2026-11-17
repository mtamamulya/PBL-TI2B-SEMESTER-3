@extends('layouts.app')

@section('title', 'Detail Prestasi')

@section('content')
    <h1 class="text-3xl font-bold tracking-tight">Detail Prestasi</h1>
    <a class="text-brand" href="{{ route('prestasi.index') }}">Kembali ke daftar prestasi</a>
    <section class="rounded-lg bg-white p-5" x-data="detailPrestasi(@js((int) request()->route('id')))">
        <p x-show="error" x-text="error" role="alert" class="text-red-600"></p>
        <p x-show="!item && !error">Memuat detail prestasi...</p>
        <template x-if="item">
            <div class="space-y-4">
                <h2 class="text-xl font-semibold" x-text="item.nama_lomba"></h2>
                <dl class="grid gap-3 sm:grid-cols-2">
                    @foreach (['Penghargaan' => 'capaian', 'Penyelenggara' => 'penyelenggara', 'Tingkat' => 'tingkat_kejuaraan',
                        'Jenis' => 'jenis_prestasi', 'Bentuk' => 'bentuk_partisipasi', 'Lokasi' => 'lokasi', 'Kategori' => 'bidang_lomba',
                        'Deskripsi' => 'deskripsi'] as $label => $field)
                        <div><dt class="font-medium">{{ $label }}</dt><dd x-text="item.{{ $field }} || '—'"></dd></div>
                    @endforeach
                    <div><dt class="font-medium">Tanggal</dt><dd x-text="[item.tanggal_mulai, item.tanggal_selesai].map(t => t?.slice(0, 10)).join(' – ')"></dd></div>
                    <div><dt class="font-medium">Sumber</dt><dd x-text="item.kegiatan?.nama_kegiatan || 'Mandiri'"></dd></div>
                </dl>
                <div><h3 class="font-medium">Penerima prestasi</h3><p x-text="penerima.join(', ')"></p></div>
                <h3 class="font-medium">Dokumen bukti</h3>
                <ul><template x-for="b in item.bukti_prestasi" :key="b.id_bukti_prestasi">
                    <li><a class="text-brand underline" :href="/^https?:\\/\\//i.test(b.link) ? b.link : null" target="_blank" rel="noopener noreferrer" x-text="b.nama_file"></a></li>
                </template></ul>
            </div>
        </template>
    </section>
@endsection

@push('scripts')
@include('dosen.prestasi._api')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('detailPrestasi', id => ({
            item: null, penerima: [], error: '',
            async init() {
                try {
                    const result = await apiPrestasi('/' + id);
                    this.item = result.data;
                    this.penerima = result.penerima;
                }
                catch (e) { this.error = e.message; }
            }
        }));
    });
</script>
@endpush
