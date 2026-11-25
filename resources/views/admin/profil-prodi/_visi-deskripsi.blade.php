{{-- FASE 2A — Deskripsi singkat & Visi (teks biasa). Sementara hanya tampilan baca. --}}
<section id="sec-visi" class="rounded-lg border border-[#EFEFF1] bg-white p-[25px] shadow-[0_1px_3px_rgba(0,0,0,0.07)]">
    <h2 class="text-xl font-semibold text-[#111]">Deskripsi &amp; Visi</h2>
    <div class="mt-4 space-y-4 text-sm">
        <div><p class="text-[#6E6E76]">Deskripsi</p><p class="text-[#111]">{{ $profil['deskripsi'] ?: '-' }}</p></div>
        <div><p class="text-[#6E6E76]">Visi</p><p class="text-[#111]">{{ $profil['visi'] ?: '-' }}</p></div>
    </div>
</section>
