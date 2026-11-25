{{-- FASE 2A — Kontak (email, no_wa, alamat) & sosial media (instagram, youtube, tiktok, facebook).
     no_wa dinormalkan server ke 62xxxxxxxxxx; kolom boleh kosong. Sementara hanya tampilan baca. --}}
<section id="sec-kontak" class="rounded-lg border border-[#EFEFF1] bg-white p-[25px] shadow-[0_1px_3px_rgba(0,0,0,0.07)]">
    <h2 class="text-xl font-semibold text-[#111]">Kontak &amp; Sosial Media</h2>
    <dl class="mt-4 grid gap-4 text-sm md:grid-cols-2">
        @foreach (['email' => 'Email', 'no_wa' => 'No. WhatsApp', 'alamat' => 'Alamat', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'] as $k => $label)
            <div><dt class="text-[#6E6E76]">{{ $label }}</dt><dd class="break-all text-[#111]">{{ $profil[$k] ?: '-' }}</dd></div>
        @endforeach
    </dl>
</section>
