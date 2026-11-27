{{-- FASE 5B — Unggah & Hapus Logo Utama (navbar) dan Logo Footer via API. --}}
@php
    $logoUtamaServer = $profil['logo_url'] ?? null;
    $logoFooterServer = $profil['logo_footer_url'] ?? null;
    $defaultUtama = asset('images/logo_trk.jpeg');
    $defaultFooter = asset('images/logo_trk remove.png');
    $tampilFooterAwal = $logoFooterServer ?: ($logoUtamaServer ?: $defaultFooter);
@endphp

<style>
    #pp-logo-modal-hapus {
        position: fixed;
        inset: 0;
        margin: auto;
        height: fit-content;
        width: 380px;
        max-width: calc(100vw - 32px);
        padding: 0;
        border: 0;
        background: transparent;
        overflow: visible;
    }
    #pp-logo-modal-hapus::backdrop {
        background: rgb(10 10 10 / .45);
    }
    #pp-logo-modal-hapus[open] {
        animation: ppLogoModalMasuk .15s ease-out;
    }
    @keyframes ppLogoModalMasuk {
        from { opacity: 0; transform: translateY(8px) scale(.98); }
        to { opacity: 1; transform: none; }
    }
</style>

<section id="sec-logo" class="rounded-lg border border-[#EFEFF1] bg-white p-[25px] shadow-[0_1px_3px_rgba(0,0,0,0.07)]">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 class="text-xl font-semibold text-[#111]">Logo</h2>
            <p class="mt-1 text-sm text-[#6E6E76]">Unggah logo utama untuk navbar dan logo footer untuk bagian bawah halaman publik (PNG, JPG, WEBP, maks. 2 MB).</p>
        </div>
    </div>

    <div id="pp-logo-toast" role="status" aria-live="polite"
         class="mt-4 hidden items-center gap-2 rounded-lg border border-[#BBF7D0] bg-[#F0FDF4] px-4 py-2.5 text-xs font-medium text-[#15803D]">
        <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M5 12l5 5l10 -10" />
        </svg>
        <span id="pp-logo-toast-text"></span>
    </div>

    <div class="mt-5 grid gap-6 text-sm md:grid-cols-2">
        {{-- Logo Utama --}}
        <div class="flex flex-col justify-between rounded-lg border border-[#EFEFF1] bg-[#FAFAFB] p-4">
            <div>
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium text-[#111]">Logo Utama</span>
                    <span id="pp-logo-utama-status" class="text-xs text-[#6E6E76]">
                        {{ !empty($logoUtamaServer) ? 'Logo aktif' : 'Logo bawaan' }}
                    </span>
                </div>
                <p class="mt-0.5 text-xs text-[#6E6E76]">Ditampilkan pada navbar publik dan menjadi cadangan bila logo footer kosong.</p>

                <div class="mt-3 flex h-32 items-center justify-center overflow-hidden rounded-lg border border-dashed border-[#DEDEE3] bg-white p-3">
                    <img id="pp-logo-utama-img"
                         src="{{ $logoUtamaServer ?: $defaultUtama }}"
                         alt="Pratinjau Logo Utama"
                         onerror="this.onerror=null;this.src='{{ $defaultUtama }}';"
                         class="max-h-24 max-w-full object-contain">
                </div>

                <div class="mt-3">
                    <input id="pp-logo-utama-input" type="file" accept="image/png,image/jpeg,image/webp"
                           class="block w-full cursor-pointer text-xs text-[#6E6E76] file:mr-3 file:cursor-pointer file:rounded-[7px] file:border-0 file:bg-[#EEF4FF] file:px-3.5 file:py-2 file:text-xs file:font-medium file:text-[#226CFF] hover:file:bg-[#DCE8FF] disabled:cursor-not-allowed disabled:opacity-60">
                    <p id="pp-logo-utama-name" class="mt-1.5 truncate text-xs text-[#6E6E76]"></p>
                    <p id="pp-logo-utama-error" role="alert" class="mt-1.5 hidden text-xs font-medium text-[#DC2626]"></p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-[#EFEFF1] pt-3">
                <button type="button" id="pp-logo-utama-upload"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-[7px] bg-[#226CFF] px-4 py-2 text-xs font-medium text-white shadow-xs transition-colors hover:bg-[#1b5ae0] disabled:cursor-not-allowed disabled:opacity-60">
                    Unggah
                </button>
                <button type="button" id="pp-logo-utama-delete"
                        class="{{ empty($logoUtamaServer) ? 'hidden' : '' }} inline-flex cursor-pointer items-center gap-1.5 rounded-[7px] border border-[#FECACA] bg-[#FEF2F2] px-3.5 py-2 text-xs font-medium text-[#DC2626] transition-colors hover:bg-[#FEE2E2] disabled:cursor-not-allowed disabled:opacity-60">
                    Hapus
                </button>
            </div>
        </div>

        {{-- Logo Footer --}}
        <div class="flex flex-col justify-between rounded-lg border border-[#EFEFF1] bg-[#FAFAFB] p-4">
            <div>
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium text-[#111]">Logo Footer</span>
                    <span id="pp-logo-footer-status" class="text-xs text-[#6E6E76]">
                        {{ !empty($logoFooterServer) ? 'Logo footer aktif' : 'Memakai logo utama' }}
                    </span>
                </div>
                <p class="mt-0.5 text-xs text-[#6E6E76]">Boleh kosong. Jika kosong, footer otomatis memakai logo utama.</p>

                <div class="mt-3 flex h-32 items-center justify-center overflow-hidden rounded-lg border border-dashed border-[#DEDEE3] bg-white p-3">
                    <img id="pp-logo-footer-img"
                         src="{{ $tampilFooterAwal }}"
                         alt="Pratinjau Logo Footer"
                         onerror="this.onerror=null;this.src='{{ $defaultFooter }}';"
                         class="max-h-24 max-w-full object-contain">
                </div>

                <div class="mt-3">
                    <input id="pp-logo-footer-input" type="file" accept="image/png,image/jpeg,image/webp"
                           class="block w-full cursor-pointer text-xs text-[#6E6E76] file:mr-3 file:cursor-pointer file:rounded-[7px] file:border-0 file:bg-[#EEF4FF] file:px-3.5 file:py-2 file:text-xs file:font-medium file:text-[#226CFF] hover:file:bg-[#DCE8FF] disabled:cursor-not-allowed disabled:opacity-60">
                    <p id="pp-logo-footer-name" class="mt-1.5 truncate text-xs text-[#6E6E76]"></p>
                    <p id="pp-logo-footer-error" role="alert" class="mt-1.5 hidden text-xs font-medium text-[#DC2626]"></p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-[#EFEFF1] pt-3">
                <button type="button" id="pp-logo-footer-upload"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-[7px] bg-[#226CFF] px-4 py-2 text-xs font-medium text-white shadow-xs transition-colors hover:bg-[#1b5ae0] disabled:cursor-not-allowed disabled:opacity-60">
                    Unggah
                </button>
                <button type="button" id="pp-logo-footer-delete"
                        class="{{ empty($logoFooterServer) ? 'hidden' : '' }} inline-flex cursor-pointer items-center gap-1.5 rounded-[7px] border border-[#FECACA] bg-[#FEF2F2] px-3.5 py-2 text-xs font-medium text-[#DC2626] transition-colors hover:bg-[#FEE2E2] disabled:cursor-not-allowed disabled:opacity-60">
                    Hapus
                </button>
            </div>
        </div>
    </div>
</section>

{{-- Modal konfirmasi hapus logo --}}
<dialog id="pp-logo-modal-hapus" aria-labelledby="pp-logo-modal-judul" aria-describedby="pp-logo-modal-teks">
    <div class="rounded-xl bg-white p-6 text-center shadow-[0_20px_50px_rgba(0,0,0,0.2)]">
        <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-[#FFD9D9] text-[#E5484D]">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 7l16 0" />
                <path d="M10 11l0 6" />
                <path d="M14 11l0 6" />
                <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                <path d="M9 7v-3a1 1 0 0 1 1 -1h6a1 1 0 0 1 1 1v3" />
            </svg>
        </div>
        <h2 id="pp-logo-modal-judul" class="text-lg font-bold leading-6 text-[#0A0A0A]">Hapus logo?</h2>
        <p id="pp-logo-modal-teks" class="mt-2 text-sm leading-5 text-[#55555C]">
            <strong id="pp-logo-modal-nama" class="font-semibold text-[#0A0A0A]"></strong> akan dihapus permanen dan dikembalikan ke bawaan.
        </p>
        <p id="pp-logo-modal-error" class="mt-3 hidden text-[13px] font-medium text-[#E5484D]"></p>
        <div class="mt-6 flex gap-3">
            <button type="button" id="pp-logo-modal-batal"
                    class="h-10 flex-1 cursor-pointer rounded-lg border border-[#E8E8EC] bg-white text-sm font-medium text-[#2E2E2E] transition-colors hover:bg-[#F9F9FB] disabled:cursor-not-allowed disabled:opacity-60">
                Batal
            </button>
            <button type="button" id="pp-logo-modal-ya"
                    class="h-10 flex-1 cursor-pointer rounded-lg bg-[#E5484D] text-sm font-medium text-white transition-colors hover:bg-[#D13A3F] disabled:cursor-not-allowed disabled:opacity-70">
                Hapus
            </button>
        </div>
    </div>
</dialog>

<script>
(() => {
    const STORE_URL = @json(route($prefix . '.prodi.profil.logo.store'));
    const DESTROY_URL_TEMPLATE = @json(route($prefix . '.prodi.profil.logo.destroy', ['jenis' => '__JENIS__']));
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());

    const DEFAULT_UTAMA = @json($defaultUtama);
    const DEFAULT_FOOTER = @json($defaultFooter);
    const MAX_BYTES = 2 * 1024 * 1024;
    const ALLOWED_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

    let serverUtama = @json($logoUtamaServer ?? '');
    let serverFooter = @json($logoFooterServer ?? '');
    let toastTimer = null;
    let jenisHapusAktif = null;

    const toastEl = document.getElementById('pp-logo-toast');
    const toastTextEl = document.getElementById('pp-logo-toast-text');

    const modalHapus = document.getElementById('pp-logo-modal-hapus');
    const modalJudul = document.getElementById('pp-logo-modal-judul');
    const modalNama = document.getElementById('pp-logo-modal-nama');
    const modalError = document.getElementById('pp-logo-modal-error');
    const modalBatal = document.getElementById('pp-logo-modal-batal');
    const modalYa = document.getElementById('pp-logo-modal-ya');

    const el = {
        utama: {
            input: document.getElementById('pp-logo-utama-input'),
            img: document.getElementById('pp-logo-utama-img'),
            status: document.getElementById('pp-logo-utama-status'),
            name: document.getElementById('pp-logo-utama-name'),
            error: document.getElementById('pp-logo-utama-error'),
            uploadBtn: document.getElementById('pp-logo-utama-upload'),
            deleteBtn: document.getElementById('pp-logo-utama-delete'),
            previewUrl: '',
        },
        footer: {
            input: document.getElementById('pp-logo-footer-input'),
            img: document.getElementById('pp-logo-footer-img'),
            status: document.getElementById('pp-logo-footer-status'),
            name: document.getElementById('pp-logo-footer-name'),
            error: document.getElementById('pp-logo-footer-error'),
            uploadBtn: document.getElementById('pp-logo-footer-upload'),
            deleteBtn: document.getElementById('pp-logo-footer-delete'),
            previewUrl: '',
        },
    };

    if (!el.utama.input || !el.footer.input) return;

    function showToast(message) {
        if (!toastEl || !toastTextEl) return;
        toastTextEl.textContent = message;
        toastEl.classList.remove('hidden');
        toastEl.classList.add('flex');
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            toastEl.classList.add('hidden');
            toastEl.classList.remove('flex');
        }, 3500);
    }

    function setError(jenis, message) {
        const target = el[jenis].error;
        if (!message) {
            target.textContent = '';
            target.classList.add('hidden');
            return;
        }
        target.textContent = message;
        target.classList.remove('hidden');
    }

    function formatErrorServer(status, body) {
        const rawMsg = typeof body?.message === 'string' ? body.message.trim() : '';

        if (status === 422) {
            return body?.errors?.file?.[0]
                || body?.errors?.jenis?.[0]
                || rawMsg
                || 'Berkas logo tidak valid.';
        }

        if (status >= 500 || rawMsg === '') {
            return 'Gagal menghubungi server';
        }

        if (/data prodi belum ada/i.test(rawMsg)) {
            return 'Tolong isi dan simpan identitas profil prodi terlebih dahulu.';
        }

        return rawMsg;
    }

    function setBusy(busy) {
        ['utama', 'footer'].forEach((jenis) => {
            el[jenis].input.disabled = busy;
            el[jenis].uploadBtn.disabled = busy;
            el[jenis].deleteBtn.disabled = busy;
        });
        if (modalBatal) modalBatal.disabled = busy;
        if (modalYa) modalYa.disabled = busy;
    }

    function clearLocalPreview(jenis) {
        if (el[jenis].previewUrl) {
            URL.revokeObjectURL(el[jenis].previewUrl);
            el[jenis].previewUrl = '';
        }
    }

    function renderState() {
        const aktifUtama = el.utama.previewUrl || serverUtama || DEFAULT_UTAMA;
        el.utama.img.src = aktifUtama;
        el.utama.status.textContent = serverUtama ? 'Logo aktif' : 'Logo bawaan';
        el.utama.deleteBtn.classList.toggle('hidden', !serverUtama);

        const aktifFooter = el.footer.previewUrl || serverFooter || serverUtama || DEFAULT_FOOTER;
        el.footer.img.src = aktifFooter;
        el.footer.status.textContent = serverFooter ? 'Logo footer aktif' : 'Memakai logo utama';
        el.footer.deleteBtn.classList.toggle('hidden', !serverFooter);
    }

    function validateClientFile(file) {
        if (!file) {
            return 'Pilih berkas logo terlebih dahulu.';
        }
        const extOk = /\.(png|jpe?g|webp)$/i.test(file.name);
        if (!ALLOWED_TYPES.includes(file.type) && !extOk) {
            return 'Format logo harus PNG, JPG, JPEG, atau WEBP.';
        }
        if (file.size > MAX_BYTES) {
            return 'Ukuran logo maksimal 2 MB.';
        }
        return '';
    }

    function bukaModalHapus(jenis) {
        jenisHapusAktif = jenis;
        const isUtama = jenis === 'utama';
        modalJudul.textContent = isUtama ? 'Hapus logo utama?' : 'Hapus logo footer?';
        modalNama.textContent = isUtama ? 'Logo Utama' : 'Logo Footer';
        modalError.textContent = '';
        modalError.classList.add('hidden');
        modalYa.textContent = 'Hapus';
        modalHapus.showModal();
    }

    async function eksekusiHapus() {
        if (!jenisHapusAktif) return;
        const jenis = jenisHapusAktif;

        setError(jenis, '');
        modalError.classList.add('hidden');
        setBusy(true);
        modalYa.textContent = 'Menghapus...';

        try {
            const url = DESTROY_URL_TEMPLATE.replace('__JENIS__', jenis);
            const res = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json',
                },
            });

            const body = await res.json().catch(() => ({}));

            if (!res.ok || !body?.ok) {
                modalError.textContent = formatErrorServer(res.status, body);
                modalError.classList.remove('hidden');
                return;
            }

            serverUtama = body.data?.logo_url || '';
            serverFooter = body.data?.logo_footer_url || '';
            clearLocalPreview(jenis);
            el[jenis].input.value = '';
            el[jenis].name.textContent = '';
            renderState();
            if (modalHapus.open) modalHapus.close();
            showToast(jenis === 'utama' ? 'Logo utama berhasil dihapus.' : 'Logo footer berhasil dihapus.');
        } catch {
            modalError.textContent = 'Gagal menghubungi server';
            modalError.classList.remove('hidden');
        } finally {
            modalYa.textContent = 'Hapus';
            setBusy(false);
        }
    }

    modalBatal?.addEventListener('click', () => modalHapus.close());
    modalHapus?.addEventListener('click', (e) => {
        if (e.target === modalHapus) modalHapus.close();
    });
    modalHapus?.addEventListener('close', () => {
        jenisHapusAktif = null;
        modalYa.textContent = 'Hapus';
    });
    modalYa?.addEventListener('click', eksekusiHapus);

    ['utama', 'footer'].forEach((jenis) => {
        el[jenis].input.addEventListener('change', () => {
            clearLocalPreview(jenis);
            const file = el[jenis].input.files?.[0];
            if (!file) {
                el[jenis].name.textContent = '';
                setError(jenis, '');
                renderState();
                return;
            }

            el[jenis].name.textContent = file.name;
            const err = validateClientFile(file);
            if (err) {
                setError(jenis, err);
                renderState();
                return;
            }

            setError(jenis, '');
            el[jenis].previewUrl = URL.createObjectURL(file);
            renderState();
        });

        el[jenis].uploadBtn.addEventListener('click', async () => {
            const file = el[jenis].input.files?.[0];
            const err = validateClientFile(file);
            if (err) {
                setError(jenis, err);
                return;
            }

            setError(jenis, '');
            setBusy(true);
            const prevText = el[jenis].uploadBtn.textContent;
            el[jenis].uploadBtn.textContent = 'Mengunggah...';

            try {
                const formData = new FormData();
                formData.append('jenis', jenis);
                formData.append('file', file);

                const res = await fetch(STORE_URL, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const body = await res.json().catch(() => ({}));

                if (!res.ok || !body?.ok) {
                    setError(jenis, formatErrorServer(res.status, body));
                    return;
                }

                serverUtama = body.data?.logo_url || '';
                serverFooter = body.data?.logo_footer_url || '';
                clearLocalPreview(jenis);
                el[jenis].input.value = '';
                el[jenis].name.textContent = '';
                renderState();
                showToast(jenis === 'utama' ? 'Logo utama berhasil diperbarui.' : 'Logo footer berhasil diperbarui.');
            } catch {
                setError(jenis, 'Gagal menghubungi server');
            } finally {
                el[jenis].uploadBtn.textContent = prevText;
                setBusy(false);
            }
        });

        el[jenis].deleteBtn.addEventListener('click', () => bukaModalHapus(jenis));
    });
})();
</script>