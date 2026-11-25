{{-- FASE 2B — Daftar poin yang bisa ditambah/hapus: misi[], tujuan[] (string) dan
     profil_lulusan[] ({judul, deskripsi}). Sementara hanya tampilan baca. --}}
<section id="sec-daftar-poin" class="rounded-lg border border-[#EFEFF1] bg-white p-[25px] shadow-[0_1px_3px_rgba(0,0,0,0.07)]">
    <h2 class="text-xl font-semibold text-[#111]">Misi, Tujuan &amp; Profil Lulusan</h2>
    <div class="mt-4 grid gap-6 text-sm md:grid-cols-3">
        <div>
            <p class="mb-1 text-[#6E6E76]">Misi</p>
            <ul class="list-disc space-y-1 pl-5 text-[#111]">
                @forelse ($profil['misi'] as $poin)<li>{{ $poin }}</li>@empty<li class="list-none text-[#B9B9BF]">Belum diisi</li>@endforelse
            </ul>
        </div>
        <div>
            <p class="mb-1 text-[#6E6E76]">Tujuan</p>
            <ul class="list-disc space-y-1 pl-5 text-[#111]">
                @forelse ($profil['tujuan'] as $poin)<li>{{ $poin }}</li>@empty<li class="list-none text-[#B9B9BF]">Belum diisi</li>@endforelse
            </ul>
        </div>
        <div>
            <p class="mb-1 text-[#6E6E76]">Profil Lulusan</p>
            <ul class="space-y-2 text-[#111]">
                @forelse ($profil['profil_lulusan'] as $p)
                    <li><span class="font-medium">{{ $p['judul'] }}</span><br><span class="text-[#6E6E76]">{{ $p['deskripsi'] }}</span></li>
                @empty<li class="text-[#B9B9BF]">Belum diisi</li>@endforelse
            </ul>
        </div>
    </div>
</section>
