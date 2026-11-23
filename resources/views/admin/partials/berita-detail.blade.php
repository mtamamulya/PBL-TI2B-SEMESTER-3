{{--
    resources/views/admin/partials/berita-detail.blade.php
    Isi detail berita, dipakai admin DAN kaprodi (bedanya hanya $prefix: 'admin' | 'kaprodi').
--}}
@php
    $badgeMap = [
        'Publish'   => 'border-[#AAC8F8] bg-[#CDDEF9] text-[#2277FF]',
        'Draft'     => 'border-[#E4E4E7] bg-[#EDEDF0] text-[#55555C]',
        'Pengajuan' => 'border-[#FAD4A8] bg-[#FBE5CB] text-[#854700]',
        'Revisi'    => 'border-[#FDE047] bg-[#FEF9C3] text-[#854D0E]',
        'Ditolak'   => 'border-[#FECACA] bg-[#FEE2E2] text-[#B91C1C]',
    ];
    $statusCls = $badgeMap[$berita['status']] ?? 'border-[#E4E4E7] bg-[#EDEDF0] text-[#55555C]';

    $info = [
        ['Kategori', $berita['kegiatan']],
        ['Hari / Tanggal', $hariTanggal],
        ['Waktu Baca', $waktuBaca],
    ];
    $urlEdit = \Illuminate\Support\Facades\Route::has($prefix.'.berita.edit')
        ? route($prefix.'.berita.edit', $berita['id']) : null;
    $urlHapus = \Illuminate\Support\Facades\Route::has($prefix.'.berita.destroy')
        ? route($prefix.'.berita.destroy', $berita['id']) : null;
    $urlTinjau = ($prefix === 'admin' && \Illuminate\Support\Facades\Route::has('admin.berita.tinjau'))
        ? route('admin.berita.tinjau', $berita['id']) : null;
    $showRoute = $prefix.'.berita.show';
    $catatanRevisi = $berita['catatan_revisi'] ?? null;
    $oldAksi = old('aksi');
@endphp

<style>
    .berita-konten p { margin-bottom: 1.25rem; text-align: justify; }
    .berita-konten h2 { font-size: 1.5rem; font-weight: 700; color: #071E38; margin: 1.5rem 0 0.75rem; }
    .berita-konten h3 { font-size: 1.25rem; font-weight: 700; color: #071E38; margin: 1.25rem 0 0.5rem; }
    .berita-konten h4 { font-size: 1.1rem; font-weight: 600; color: #071E38; margin: 1rem 0 0.5rem; }
    .berita-konten ul { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 1.25rem; }
    .berita-konten ol { list-style-type: decimal; padding-left: 1.5rem; margin-bottom: 1.25rem; }
    .berita-konten li { margin-bottom: 0.35rem; }
    .berita-konten blockquote { border-left: 4px solid #2277FF; background: #F8FAFC; padding: 0.875rem 1.25rem; border-radius: 0 0.5rem 0.5rem 0; color: #334155; margin: 1.25rem 0; }
    .berita-konten a { color: #2277FF; text-decoration: underline; }
    .berita-konten figure.image { margin: 1.5rem auto; text-align: center; }
    .berita-konten figure.image img,
    .berita-konten img { max-width: 100%; height: auto; border-radius: 0.75rem; margin: 1rem auto; display: block; border: 1px solid #E5E7EB; }
    .berita-konten figure.image figcaption { margin-top: 0.5rem; font-size: 0.875rem; color: #64758A; font-style: italic; }
</style>

<div class="flex flex-col gap-9">
    @if (session('status'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700" role="status">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Judul + aksi --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <h1 class="max-w-[900px] text-[26px] font-semibold leading-[38px] tracking-[-0.2px]">{{ $berita['judul'] }}</h1>
        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex rounded-md border px-2.5 py-1 text-sm font-medium {{ $statusCls }}">{{ $berita['status'] }}</span>
            @if ($urlEdit)
                <a href="{{ $urlEdit }}" class="flex items-center gap-1.5 rounded-md bg-[#FBE5CB] px-3 py-1.5 text-sm font-medium text-[#C77700] hover:bg-[#F8D9B5]">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L18.5 9.5a2.828 2.828 0 10-4-4L4 16v4M13.5 6.5l4 4"/></svg>
                    Ubah
                </a>
            @endif
            @if ($urlHapus)
                <button type="button" id="btn-buka-hapus-detail" class="flex cursor-pointer items-center gap-1.5 rounded-md bg-[#FEE2E2] px-3 py-1.5 text-sm font-medium text-[#DC2626] hover:bg-[#FECACA]">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7l16 0"/><path d="M10 11l0 6"/><path d="M14 11l0 6"/><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"/><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"/></svg>
                    Hapus
                </button>
            @endif
        </div>
    </div>

    {{-- Catatan revisi / alasan penolakan --}}
    @if (in_array($berita['status'], ['Revisi', 'Ditolak'], true) && $catatanRevisi)
        <div class="rounded-[10px] border {{ $berita['status'] === 'Ditolak' ? 'border-[#FECACA] bg-[#FEF2F2] text-[#991B1B]' : 'border-[#FDE047] bg-[#FEFCE8] text-[#854D0E]' }} px-5 py-4">
            <div class="text-sm font-semibold">
                {{ $berita['status'] === 'Ditolak' ? 'Alasan Penolakan Admin' : 'Catatan Revisi Admin' }}
            </div>
            <p class="mt-1 text-sm leading-relaxed">{{ $catatanRevisi }}</p>
        </div>
    @endif

    {{-- Panel Tinjau & Verifikasi (khusus Admin ketika status Pengajuan) --}}
    @if ($urlTinjau && $berita['status'] === 'Pengajuan')
        <div class="rounded-[10px] border border-[#AAC8F8] bg-[#F5F9FF] p-5">
            <div class="flex flex-col gap-1">
                <h2 class="text-lg font-bold text-[#071E38]">Tinjau &amp; Verifikasi Berita</h2>
                <p class="text-sm text-[#64758A]">Pilih keputusan verifikasi untuk pengajuan berita ini.</p>
            </div>

            <form id="form-tinjau-berita" method="POST" action="{{ $urlTinjau }}" class="mt-4 flex flex-col gap-4">
                @csrf
                @method('PATCH')
                <input type="hidden" name="aksi" id="tinjau-aksi" value="{{ $oldAksi }}">

                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" data-tinjau-btn="terima"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-md bg-[#2277FF] px-4 py-2 text-sm font-medium text-white shadow-xs hover:bg-[#1A66E0]">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5l10 -10"/></svg>
                        Terima &amp; Publish
                    </button>

                    <button type="button" data-tinjau-btn="revisi"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-[#FACC15] bg-[#FEF9C3] px-4 py-2 text-sm font-medium text-[#854D0E] hover:bg-[#FEF08A]">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L18.5 9.5a2.828 2.828 0 10-4-4L4 16v4M13.5 6.5l4 4"/></svg>
                        Minta Revisi
                    </button>

                    <button type="button" data-tinjau-btn="tolak"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-[#FCA5A5] bg-[#FEE2E2] px-4 py-2 text-sm font-medium text-[#B91C1C] hover:bg-[#FECACA]">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6l-12 12"/><path d="M6 6l12 12"/></svg>
                        Tolak
                    </button>
                </div>

                <div id="tinjau-catatan-wrap" class="{{ in_array($oldAksi, ['revisi', 'tolak'], true) ? 'flex' : 'hidden' }} flex-col gap-2 rounded-lg border border-[#E3E3E3] bg-white p-4">
                    <label for="catatan_revisi" id="tinjau-catatan-label" class="text-sm font-semibold text-[#071E38]">
                        {{ $oldAksi === 'tolak' ? 'Alasan Penolakan *' : 'Catatan Revisi *' }}
                    </label>
                    <textarea id="catatan_revisi" name="catatan_revisi" rows="3"
                              placeholder="{{ $oldAksi === 'tolak' ? 'Tuliskan alasan mengapa berita ini ditolak...' : 'Tuliskan bagian berita yang perlu diperbaiki...' }}"
                              class="w-full rounded-lg border border-[#E8E8EC] px-3 py-2 text-sm text-[#1C2024] outline-none focus:border-[#2277FF]">{{ old('catatan_revisi') }}</textarea>
                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" id="tinjau-batal" class="cursor-pointer rounded-md border border-[#E8E8EC] bg-white px-3.5 py-1.5 text-sm font-medium text-[#55555C] hover:bg-[#F9F9FC]">
                            Batal
                        </button>
                        <button type="submit" id="tinjau-submit"
                                class="cursor-pointer rounded-md {{ $oldAksi === 'tolak' ? 'bg-[#DC2626] hover:bg-[#B91C1C]' : 'bg-[#CA8A04] hover:bg-[#A16207]' }} px-4 py-1.5 text-sm font-medium text-white">
                            {{ $oldAksi === 'tolak' ? 'Konfirmasi Tolak Berita' : 'Kirim Permintaan Revisi' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    {{-- Meta penulis --}}
    <div class="flex flex-wrap items-center gap-x-5 gap-y-3 py-1">
        <div class="flex size-11 items-center justify-center rounded-full bg-[#E3E3E3]">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2B3F6C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"/><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/></svg>
        </div>
        <div class="w-40">
            <div class="text-lg font-medium leading-7 text-[#0A0A0A]">Humas TRK</div>
            <div class="text-sm text-[#7F7F7F]">Penulis</div>
        </div>
        <div class="w-40">
            <div class="text-lg font-medium leading-7 text-[#0A0A0A]">{{ $tanggalPendek }}</div>
            <div class="text-sm text-[#7F7F7F]">Tanggal publikasi</div>
        </div>
    </div>

    <div class="flex flex-col items-start gap-9 xl:flex-row">

        {{-- Artikel (Rich Text + Foto Dokumentasi) --}}
        <article class="flex min-w-0 flex-1 flex-col gap-[25px]">
            <div class="flex h-[240px] w-full items-center justify-center overflow-hidden rounded-lg bg-[#F3F4F6] md:h-[420px]">
                @if ($foto)
                    <img src="{{ $foto }}" alt="Foto sampul berita" class="h-full w-full object-cover">
                @else
                    <span class="px-6 text-center text-sm text-[#7D8999]">{{ $berita['foto'] ? 'Sampul berupa dokumen PDF (tidak dapat ditampilkan sebagai gambar).' : 'Belum ada foto sampul.' }}</span>
                @endif
            </div>

            <div class="berita-konten text-lg font-normal leading-[30px] text-[#1C2024]">
                @if (! empty($isiHtml))
                    {!! $isiHtml !!}
                @else
                    @foreach ($paragraf as $p)
                        <p>{{ $p }}</p>
                    @endforeach
                @endif
            </div>
        </article>

        {{-- Sidebar --}}
        <aside class="flex w-full shrink-0 flex-col gap-10 xl:w-[380px]">
            <div class="flex flex-col gap-3 rounded-[10px] border border-[#E3E3E3] bg-white px-[22px] pb-[22px] pt-[28px]">
                <h2 class="text-[28px] font-bold leading-[34px] tracking-[-0.2px] text-[#071E38]">Informasi</h2>
                @foreach ($info as [$label, $value])
                    <div class="flex flex-col gap-1.5">
                        <div class="text-base font-semibold tracking-[-0.18px] text-[#64758A]">{{ $label }}</div>
                        <div class="text-xl font-medium leading-6 text-[#071E38]">{{ $value }}</div>
                    </div>
                @endforeach
            </div>

            <div class="px-1">
                <h2 class="text-2xl font-bold leading-[30px] tracking-[-0.15px] text-[#071E38]">Informasi Terkait</h2>
                <div class="h-1 bg-gradient-to-r from-[#3979BA] to-[#9CB7D5]"></div>

                <div class="flex flex-col gap-[18px] pt-[18px]">
                    @forelse ($terkait as $t)
                        <a href="{{ route($showRoute, $t['id']) }}" class="flex items-center gap-[11px] overflow-hidden">
                            <div class="flex h-[72px] w-[110px] shrink-0 items-center justify-center overflow-hidden rounded-lg bg-[#F3F4F6] text-[#A8A8A8]">
                                @if (! empty($t['foto']))
                                    <img src="{{ $t['foto'] }}" alt="{{ $t['judul'] }}" class="h-full w-full object-cover">
                                @else
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 6h3a1 1 0 0 1 1 1v11a2 2 0 0 1 -4 0v-13a1 1 0 0 0 -1 -1h-10a1 1 0 0 0 -1 1v12a3 3 0 0 0 3 3h11"/><path d="M8 8l4 0"/><path d="M8 12l4 0"/><path d="M8 16l4 0"/></svg>
                                @endif
                            </div>
                            <div class="flex min-w-0 flex-col gap-1">
                                <div class="line-clamp-2 text-base font-bold leading-[22px] tracking-[-0.18px] text-[#071E38]">{{ $t['judul'] }}</div>
                                <div class="text-xs text-[#7D8999]">{{ $t['tanggal'] }}</div>
                            </div>
                        </a>
                    @empty
                        <p class="text-sm text-[#7D8999]">Belum ada berita terkait.</p>
                    @endforelse
                    <div class="h-px bg-[#E6E9EE]"></div>
                </div>
            </div>
        </aside>
    </div>
</div>

{{-- Modal Konfirmasi Hapus Berita --}}
@if ($urlHapus)
    <div id="modal-hapus-detail" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h3 class="text-lg font-bold text-[#071E38]">Hapus Berita?</h3>
            <p class="mt-2 text-sm text-[#64758A]">
                Berita <strong class="text-[#111827]">"{{ $berita['judul'] }}"</strong> akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.
            </p>
            <form method="POST" action="{{ $urlHapus }}" class="mt-5 flex items-center justify-end gap-3">
                @csrf
                @method('DELETE')
                <button type="button" id="btn-batal-hapus-detail" class="cursor-pointer rounded-lg border border-[#E5E7EB] bg-white px-4 py-2 text-sm font-medium text-[#55555C] hover:bg-[#F9FAFB]">
                    Batal
                </button>
                <button type="submit" class="cursor-pointer rounded-lg bg-[#DC2626] px-4 py-2 text-sm font-medium text-white hover:bg-[#B91C1C]">
                    Ya, Hapus
                </button>
            </form>
        </div>
    </div>
@endif

<script>
(function () {
    // Modal hapus detail
    var btnHapus = document.getElementById('btn-buka-hapus-detail');
    var modalHapus = document.getElementById('modal-hapus-detail');
    var btnBatalHapus = document.getElementById('btn-batal-hapus-detail');

    if (btnHapus && modalHapus && btnBatalHapus) {
        btnHapus.addEventListener('click', function () {
            modalHapus.classList.remove('hidden');
            modalHapus.classList.add('flex');
        });
        btnBatalHapus.addEventListener('click', function () {
            modalHapus.classList.add('hidden');
            modalHapus.classList.remove('flex');
        });
    }

    // Panel tinjau admin
    var form = document.getElementById('form-tinjau-berita');
    if (!form) return;

    var aksiInput = document.getElementById('tinjau-aksi');
    var wrap = document.getElementById('tinjau-catatan-wrap');
    var label = document.getElementById('tinjau-catatan-label');
    var textarea = document.getElementById('catatan_revisi');
    var submitBtn = document.getElementById('tinjau-submit');
    var cancelBtn = document.getElementById('tinjau-batal');

    form.querySelectorAll('[data-tinjau-btn]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var aksi = btn.getAttribute('data-tinjau-btn');
            aksiInput.value = aksi;

            if (aksi === 'terima') {
                textarea.removeAttribute('required');
                form.submit();
                return;
            }

            wrap.classList.remove('hidden');
            wrap.classList.add('flex');
            textarea.setAttribute('required', 'required');

            if (aksi === 'tolak') {
                label.textContent = 'Alasan Penolakan *';
                textarea.placeholder = 'Tuliskan alasan mengapa berita ini ditolak...';
                submitBtn.textContent = 'Konfirmasi Tolak Berita';
                submitBtn.className = 'cursor-pointer rounded-md bg-[#DC2626] px-4 py-1.5 text-sm font-medium text-white hover:bg-[#B91C1C]';
            } else {
                label.textContent = 'Catatan Revisi *';
                textarea.placeholder = 'Tuliskan bagian berita yang perlu diperbaiki...';
                submitBtn.textContent = 'Kirim Permintaan Revisi';
                submitBtn.className = 'cursor-pointer rounded-md bg-[#CA8A04] px-4 py-1.5 text-sm font-medium text-white hover:bg-[#A16207]';
            }

            textarea.focus();
        });
    });

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            aksiInput.value = '';
            textarea.removeAttribute('required');
            wrap.classList.add('hidden');
            wrap.classList.remove('flex');
        });
    }
})();
</script>