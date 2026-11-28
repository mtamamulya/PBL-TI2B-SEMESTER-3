@extends('layouts.publik')

@section('title', $berita['judul'] . ' — TRK')

@section('content')
    {{-- Styling khusus konten CKEditor pada halaman baca berita publik --}}
    <style>
        /* ── Tipografi konten artikel ─────────────────────────────── */
        .berita-konten { color: #1f2937; line-height: 1.85; font-size: 1.125rem; word-break: break-word; }
        .berita-konten p { margin-bottom: 1.25rem; text-align: justify; text-justify: inter-word; }
        .berita-konten h2 { font-size: 1.5rem; font-weight: 700; color: #071e38; margin: 2rem 0 0.75rem; }
        .berita-konten h3 { font-size: 1.25rem; font-weight: 700; color: #071e38; margin: 1.5rem 0 0.5rem; }
        .berita-konten h4 { font-size: 1.1rem; font-weight: 600; color: #071e38; margin: 1.25rem 0 0.5rem; }
        .berita-konten strong, .berita-konten b { font-weight: 700; }
        .berita-konten em, .berita-konten i { font-style: italic; }
        .berita-konten ul { list-style-type: disc; padding-left: 1.5rem; margin: 1rem 0; }
        .berita-konten ol { list-style-type: decimal; padding-left: 1.5rem; margin: 1rem 0; }
        .berita-konten li { margin-bottom: 0.35rem; }
        .berita-konten blockquote {
            border-left: 4px solid #2277FF; background: #F8FAFC;
            padding: 0.875rem 1.25rem; border-radius: 0 0.5rem 0.5rem 0;
            color: #334155; margin: 1.25rem 0; font-style: italic;
        }
        .berita-konten a { color: #2277FF; text-decoration: underline; }
        .berita-konten a:hover { color: #1A66E0; }

        /* ── Gambar dokumentasi di dalam artikel ──────────────────── */
        .berita-konten figure.image { margin: 1.5rem auto; text-align: center; max-width: 100%; }
        .berita-konten figure.image:not(.image_resized) { max-width: 680px; }
        .berita-konten figure.image.image-style-side { float: right; margin: 0.25rem 0 1.25rem 1.5rem; max-width: 50%; }
        .berita-konten figure.image img {
            max-width: 100%; height: auto; border-radius: 0.625rem;
            margin: 0 auto; display: block; border: 1px solid #E5E7EB;
        }
        .berita-konten figure.image:not(.image_resized) img { max-height: 440px; object-fit: contain; }
        .berita-konten figure.image figcaption { margin-top: 0.5rem; font-size: 0.875rem; color: #64758A; font-style: italic; }
        .berita-konten img:not(figure img) { max-width: 100%; height: auto; border-radius: 0.5rem; display: block; margin: 1rem auto; border: 1px solid #E5E7EB; }

        /* ── Tabel di dalam konten ────────────────────────────────── */
        .berita-konten table { width: 100%; border-collapse: collapse; margin: 1.25rem 0; font-size: 0.95rem; }
        .berita-konten th, .berita-konten td { border: 1px solid #E5E7EB; padding: 0.5rem 0.75rem; text-align: left; }
        .berita-konten th { background: #F8FAFC; font-weight: 600; color: #071E38; }

        /* ── Clearfix setelah float image ─────────────────────────── */
        .berita-konten::after { content: ''; display: table; clear: both; }

        /* ── Share button hover ───────────────────────────────────── */
        .share-btn { transition: background-color 0.15s ease, color 0.15s ease; }

        /* ── Sticky Sidebar Desktop ───────────────────────────────── */
        html, body {
            overflow-x: clip !important;
        }

        @media (min-width: 1024px) {
            .sidebar-sticky {
                position: -webkit-sticky !important;
                position: sticky !important;
                top: 90px !important;
                align-self: flex-start !important;
                max-height: calc(100vh - 105px);
                overflow-y: auto;
                scrollbar-width: thin;
                scrollbar-color: #E2E8F0 transparent;
            }
            .sidebar-sticky::-webkit-scrollbar {
                width: 4px;
            }
            .sidebar-sticky::-webkit-scrollbar-thumb {
                background: #E2E8F0;
                border-radius: 4px;
            }
        }

        /* ── Responsive: mobile ≤ 640px ──────────────────────────── */
        @media (max-width: 640px) {
            .berita-konten { font-size: 1rem; line-height: 1.75; }
            .berita-konten p { text-align: justify; text-justify: inter-word; }
            .berita-konten h2 { font-size: 1.25rem; margin: 1.5rem 0 0.5rem; }
            .berita-konten h3 { font-size: 1.125rem; margin: 1.25rem 0 0.4rem; }
            .berita-konten figure.image.image-style-side {
                float: none; max-width: 100%; margin: 1rem auto;
            }
            .berita-konten table { font-size: 0.85rem; display: block; overflow-x: auto; }
        }
    </style>

    <div class="min-h-screen bg-white text-black" style="font-family: 'Plus Jakarta Sans', sans-serif;">

        {{-- Navbar --}}
        <x-navbar-publik />

        <main class="mx-auto max-w-[1200px] px-4 pb-12 pt-20 sm:px-6 md:pt-24 lg:px-8">

            {{-- Breadcrumb --}}
            <nav class="mb-3 text-sm font-normal tracking-[-0.18px] text-[#737C8D]">
                <a href="{{ route('home') }}" class="hover:text-[#2277FF] hover:underline">Beranda</a>
                <span class="mx-1">/</span>
                <a href="{{ route('publik.berita.index') }}" class="hover:text-[#2277FF] hover:underline">Berita</a>
                <span class="mx-1">/</span>
                <span class="text-[#071E38]">Detail</span>
            </nav>

            {{-- ═══ Judul Berita ═══ --}}
            <header class="mb-5 max-w-[900px]">
                {{-- Badge kategori --}}
                <span class="mb-2 inline-block rounded-full border border-[#AAC8F8] bg-[#EFF6FF] px-3 py-0.5 text-xs font-semibold tracking-wide text-[#2277FF]">
                    {{ $berita['kategori'] }}
                </span>
                <h1 class="text-xl font-bold leading-[30px] tracking-[-0.3px] text-[#071E38] sm:text-[26px] sm:leading-[38px] md:text-[30px] md:leading-[44px]">
                    {{ $berita['judul'] }}
                </h1>
            </header>

            {{-- ═══ Meta: penulis, tanggal, views (desktop) / + waktu baca (mobile) ═══ --}}
            <div class="mb-6 flex flex-col gap-3.5 border-b border-[#E6E9EE] pb-5 sm:flex-row sm:items-center sm:gap-6">
                {{-- Grup 1: Penulis & Tanggal --}}
                <div class="flex items-center sm:gap-6">
                    {{-- Penulis (lebar tetap di mobile agar kolom sejajar sempurna) --}}
                    <div class="flex w-[110px] shrink-0 items-center gap-2 sm:w-auto sm:gap-2.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#EFF6FF] sm:h-10 sm:w-10">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                                 stroke="#2277FF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="7" r="4" />
                                <path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2" />
                            </svg>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[12.5px] font-semibold text-[#071E38] sm:text-sm">{{ $berita['penulis'] }}</span>
                            <span class="text-[10.5px] text-[#7D8999] sm:text-xs">Penulis</span>
                        </div>
                    </div>

                    {{-- Divider vertikal antara Penulis dan Tanggal --}}
                    <div class="mx-3 h-6 w-px bg-[#E6E9EE] sm:mx-0 sm:h-7"></div>

                    {{-- Tanggal --}}
                    <div class="flex min-w-0 flex-1 items-center gap-2 sm:flex-none sm:gap-2.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#EFF6FF] sm:h-10 sm:w-10">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                                 stroke="#2277FF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="4" y="5" width="16" height="16" rx="2" />
                                <path d="M16 3v4" /><path d="M8 3v4" /><path d="M4 11h16" />
                            </svg>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[12px] font-semibold leading-tight text-[#071E38] sm:text-sm">{{ $berita['tanggal_panjang'] }}</span>
                            <span class="text-[10.5px] text-[#7D8999] sm:text-xs">{{ $berita['jam'] }} WIB</span>
                        </div>
                    </div>
                </div>

                {{-- Divider vertikal untuk desktop (antara Tanggal dan Dilihat) --}}
                <div class="hidden h-7 w-px bg-[#E6E9EE] sm:block"></div>

                {{-- Grup 2: Dilihat & Waktu Baca (khusus mobile) --}}
                <div class="flex items-center sm:gap-6">
                    {{-- Jumlah dilihat (lebar tetap di mobile sejajar dengan Penulis) --}}
                    <div class="flex w-[110px] shrink-0 items-center gap-2 sm:w-auto sm:gap-2.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#EFF6FF] sm:h-10 sm:w-10">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                                 stroke="#2277FF" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3" />
                                <path d="M2 12s3.636-7 10-7 10 7 10 7-3.636 7-10 7-10-7-10-7Z" />
                            </svg>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[12.5px] font-semibold text-[#071E38] sm:text-sm">{{ number_format($berita['dilihat'] ?? 0, 0, ',', '.') }} kali</span>
                            <span class="text-[10.5px] text-[#7D8999] sm:text-xs">Dilihat</span>
                        </div>
                    </div>

                    {{-- Divider vertikal KHUSUS mobile (antara Dilihat dan Waktu Baca) --}}
                    <div class="mx-3 h-6 w-px bg-[#E6E9EE] sm:hidden"></div>

                    {{-- Waktu Baca: KHUSUS MOBILE (di desktop tersembunyi karena sudah ada di panel kanan/informasi) --}}
                    <div class="flex min-w-0 flex-1 items-center gap-2 sm:hidden">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#EFF6FF]">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                                 stroke="#2277FF" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 6v6l4 2M12 2a10 10 0 100 20 10 10 0 000-20z"/>
                            </svg>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[12.5px] font-semibold text-[#071E38]">{{ $berita['waktu_baca'] }}</span>
                            <span class="text-[10.5px] text-[#7D8999]">Waktu Baca</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══ Layout utama: Artikel + Sidebar ═══ --}}
            <div class="flex flex-col gap-8 lg:flex-row lg:items-start lg:gap-10">

                {{-- ── Kolom Artikel ─────────────────────────── --}}
                <article class="min-w-0 flex-1">
                    {{-- Foto sampul --}}
                    <div class="mb-6 w-full overflow-hidden rounded-lg bg-[#F3F4F6] sm:rounded-xl">
                        <img src="{{ $berita['gambar'] }}" alt="{{ $berita['judul'] }}"
                             class="aspect-[16/9] h-auto w-full object-cover">
                    </div>

                    {{-- Konten berita --}}
                    @if (!empty($berita['isi_html']))
                        <div class="berita-konten">
                            {!! $berita['isi_html'] !!}
                        </div>
                    @else
                        <div class="berita-konten">
                            @foreach ($berita['paragraf'] as $p)
                                <p>{{ $p }}</p>
                            @endforeach
                        </div>
                    @endif

                    {{-- Tombol bagikan --}}
                    <div class="mt-8 border-t border-[#E6E9EE] pt-5">
                        <p class="mb-2.5 text-sm font-semibold text-[#64758A]">Bagikan Berita</p>
                        <div class="flex flex-wrap gap-2">
                            <a href="https://wa.me/?text={{ urlencode($berita['judul'] . ' — ' . request()->url()) }}"
                               target="_blank" rel="noopener"
                               class="share-btn inline-flex items-center gap-2 rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2 text-sm font-medium text-[#071E38] hover:border-[#25D366] hover:bg-[#25D366] hover:text-white">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                WhatsApp
                            </a>
                            <button onclick="navigator.clipboard.writeText(window.location.href).then(function(){var b=document.getElementById('copy-ok');b.classList.remove('hidden');setTimeout(function(){b.classList.add('hidden')},2000)})"
                                    class="share-btn inline-flex cursor-pointer items-center gap-2 rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2 text-sm font-medium text-[#071E38] hover:border-[#2277FF] hover:bg-[#EFF6FF] hover:text-[#2277FF]">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                                Salin Tautan
                            </button>
                            <span id="copy-ok" class="hidden items-center text-xs font-medium text-green-600">✓ Tersalin!</span>
                        </div>
                    </div>
                </article>

                {{-- ── Sidebar ───────────────────────────────── --}}
                <aside class="sidebar-sticky flex w-full shrink-0 flex-col gap-4 lg:self-start lg:sticky lg:top-[90px] lg:w-[350px] xl:w-[380px]">

                    {{-- Kartu Informasi (2x2 Grid) --}}
                    <div class="rounded-xl border border-[#E3E3E3] bg-white p-4 shadow-xs sm:p-4.5">
                        <h2 class="mb-3 text-base font-bold tracking-[-0.2px] text-[#071E38] lg:text-lg">Informasi</h2>
                        <div class="grid grid-cols-[auto_1fr] gap-x-3.5 gap-y-3">
                            {{-- 1. Kategori --}}
                            <div class="flex items-start gap-2">
                                <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-[#EFF6FF]">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2277FF" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 7h16M4 12h16M4 17h10"/>
                                    </svg>
                                </div>
                                <div class="flex min-w-0 flex-col">
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-[#7D8999]">Kategori</span>
                                    <span class="truncate max-w-[115px] text-[13px] font-semibold leading-snug text-[#071E38]" title="{{ $berita['kategori'] }}">{{ $berita['kategori'] }}</span>
                                </div>
                            </div>

                            {{-- 2. Hari / Tanggal --}}
                            <div class="flex items-start gap-2 min-w-0">
                                <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-[#EFF6FF]">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2277FF" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 5h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V7a2 2 0 012-2zM16 3v4M8 3v4M2 11h20"/>
                                    </svg>
                                </div>
                                <div class="flex min-w-0 flex-col">
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-[#7D8999]">Hari / Tanggal</span>
                                    <span class="whitespace-nowrap text-[12px] font-semibold leading-snug text-[#071E38] sm:text-[12.5px]">{{ $berita['tanggal_panjang'] }}</span>
                                </div>
                            </div>

                            {{-- 3. Total Dilihat --}}
                            <div class="flex items-start gap-2">
                                <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-[#EFF6FF]">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2277FF" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M2 12s3.636-7 10-7 10 7 10 7-3.636 7-10 7-10-7-10-7Z M12 9a3 3 0 100 6 3 3 0 000-6z"/>
                                    </svg>
                                </div>
                                <div class="flex min-w-0 flex-col">
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-[#7D8999]">Total Dilihat</span>
                                    <span class="text-[13px] font-semibold leading-snug text-[#071E38]">{{ number_format($berita['dilihat'] ?? 0, 0, ',', '.') }} kali</span>
                                </div>
                            </div>

                            {{-- 4. Waktu Baca --}}
                            <div class="flex items-start gap-2 min-w-0">
                                <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-[#EFF6FF]">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2277FF" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 6v6l4 2M12 2a10 10 0 100 20 10 10 0 000-20z"/>
                                    </svg>
                                </div>
                                <div class="flex min-w-0 flex-col">
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-[#7D8999]">Waktu Baca</span>
                                    <span class="text-[13px] font-semibold leading-snug text-[#071E38]">{{ $berita['waktu_baca'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Berita Terkait --}}
                    <div>
                        <h2 class="text-base font-bold leading-[26px] tracking-[-0.15px] text-[#071E38] lg:text-lg">Berita Terkait</h2>
                        <div class="mt-1 h-0.5 bg-gradient-to-r from-[#3979BA] to-[#9CB7D5]"></div>

                        <div class="mt-3 flex flex-col gap-2.5">
                            @forelse (collect($terkait)->take(5) as $item)
                                <a href="{{ route('publik.berita.show', $item['id'] ?? $item['slug'] ?? '') }}"
                                   class="group flex items-center gap-2.5 rounded-lg p-1 transition-colors hover:bg-[#F8FAFC]">
                                    <div class="h-[52px] w-[78px] shrink-0 overflow-hidden rounded-md bg-[#F3F4F6]">
                                        <img src="{{ $item['gambar'] }}" alt="{{ $item['judul'] }}"
                                             class="h-full w-full object-cover transition-transform group-hover:scale-105" loading="lazy">
                                    </div>
                                    <div class="flex min-w-0 flex-col gap-0.5">
                                        <span class="line-clamp-2 text-[12px] font-bold leading-[17px] tracking-[-0.15px] text-[#071E38] group-hover:text-[#2277FF]">
                                            {{ $item['judul'] }}
                                        </span>
                                        <span class="text-[11px] text-[#7D8999]">{{ $item['tanggal_ringkas'] }}</span>
                                    </div>
                                </a>
                            @empty
                                <p class="py-2.5 text-center text-xs text-[#7D8999]">Belum ada berita terkait.</p>
                            @endforelse
                        </div>
                        <div class="mt-2.5 h-px bg-[#E6E9EE]"></div>
                    </div>

                    {{-- Tombol kembali --}}
                    <a href="{{ route('publik.berita.index') }}"
                       class="flex items-center justify-center gap-2 rounded-lg border border-[#E5E7EB] bg-white px-4 py-2 text-xs font-semibold text-[#071E38] transition-colors hover:border-[#2277FF] hover:bg-[#EFF6FF] hover:text-[#2277FF]">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 12H5" /><path d="M12 19l-7-7 7-7" />
                        </svg>
                        Lihat Semua Berita
                    </a>
                </aside>
            </div>
        </main>

        {{-- Footer --}}
        <x-footer />
    </div>
@endsection
