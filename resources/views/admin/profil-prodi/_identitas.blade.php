{{-- FASE 2A — Identitas prodi (kode_prodi, nama_prodi, jenjang). Sementara hanya tampilan baca.
     Tugas: ubah jadi form; simpan lewat PUT route($prefix.'.prodi.profil.update') (fetch, JSON). --}}
<section id="sec-identitas" class="rounded-lg border border-[#EFEFF1] bg-white p-[25px] shadow-[0_1px_3px_rgba(0,0,0,0.07)]">
    <h2 class="text-xl font-semibold text-[#111]">Identitas Prodi</h2>
    <dl class="mt-4 grid gap-4 text-sm md:grid-cols-3">
        <div><dt class="text-[#6E6E76]">Kode Prodi</dt><dd class="font-medium text-[#111]">{{ $profil['kode_prodi'] ?: '-' }}</dd></div>
        <div><dt class="text-[#6E6E76]">Nama Prodi</dt><dd class="font-medium text-[#111]">{{ $profil['nama_prodi'] ?: '-' }}</dd></div>
        <div><dt class="text-[#6E6E76]">Jenjang</dt><dd class="font-medium text-[#111]">{{ $profil['jenjang'] ?: '-' }}</dd></div>
    </dl>
</section>
