{{-- FASE 2B — Logo utama (navbar, cadangan) & logo footer (boleh kosong -> pakai logo utama).
     Unggah/hapus: route logo ditambahkan di Fase 2B/4. Sementara hanya tampilan baca. --}}
<section id="sec-logo" class="rounded-lg border border-[#EFEFF1] bg-white p-[25px] shadow-[0_1px_3px_rgba(0,0,0,0.07)]">
    <h2 class="text-xl font-semibold text-[#111]">Logo</h2>
    <div class="mt-4 grid gap-4 text-sm md:grid-cols-2">
        <div><p class="text-[#6E6E76]">Logo utama</p><p class="text-[#111]">{{ $profil['logo_url'] ?: 'Belum diunggah (memakai logo bawaan)' }}</p></div>
        <div><p class="text-[#6E6E76]">Logo footer</p><p class="text-[#111]">{{ $profil['logo_footer_url'] ?: 'Kosong (memakai logo utama)' }}</p></div>
    </div>
</section>
