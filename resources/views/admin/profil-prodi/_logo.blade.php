{{-- FASE 2B — Pratinjau Logo Utama (navbar, cadangan) & Logo Footer (boleh kosong -> memakai logo utama). --}}
<section id="sec-logo" class="rounded-lg border border-[#EFEFF1] bg-white p-[25px] shadow-[0_1px_3px_rgba(0,0,0,0.07)]">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 class="text-xl font-semibold text-[#111]">Logo</h2>
            <p class="mt-1 text-sm text-[#6E6E76]">Pilih gambar untuk melihat pratinjau tampilan logo utama dan logo footer di browser.</p>
        </div>
        <span class="rounded-md border border-[#DEDEE3] bg-[#F9F9FC] px-3 py-1 text-xs font-medium text-[#6E6E76]">
            Penyimpanan logo menyusul
        </span>
    </div>

    <div class="mt-5 grid gap-6 text-sm md:grid-cols-2">
        {{-- Logo Utama --}}
        <div class="rounded-lg border border-[#EFEFF1] bg-[#FAFAFB] p-4">
            <div class="flex items-center justify-between gap-2">
                <label for="pp-logo-utama-input" class="font-medium text-[#111]">Logo Utama</label>
                <button type="button" id="pp-logo-utama-reset" class="hidden cursor-pointer text-xs font-medium text-[#226CFF] hover:underline">
                    Reset pratinjau
                </button>
            </div>
            <p class="mt-0.5 text-xs text-[#6E6E76]">Ditampilkan pada navbar dan menjadi cadangan bila logo footer kosong.</p>

            <div class="mt-3 flex h-32 items-center justify-center overflow-hidden rounded-lg border border-dashed border-[#DEDEE3] bg-white p-3">
                <img id="pp-logo-utama-img"
                     src="{{ $profil['logo_url'] ?? '' }}"
                     alt="Pratinjau Logo Utama"
                     class="{{ empty($profil['logo_url']) ? 'hidden' : '' }} max-h-24 max-w-full object-contain">
                <p id="pp-logo-utama-empty" class="{{ !empty($profil['logo_url']) ? 'hidden' : '' }} text-center text-xs text-[#6E6E76]">
                    Belum diunggah (memakai logo bawaan)
                </p>
            </div>

            <div class="mt-3">
                <input id="pp-logo-utama-input" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                       class="block w-full cursor-pointer text-xs text-[#6E6E76] file:mr-3 file:cursor-pointer file:rounded-[7px] file:border-0 file:bg-[#226CFF] file:px-3.5 file:py-2 file:text-xs file:font-medium file:text-white hover:file:bg-[#1b5ae0]">
                <p id="pp-logo-utama-name" class="mt-1.5 truncate text-xs text-[#6E6E76]"></p>
            </div>
        </div>

        {{-- Logo Footer --}}
        <div class="rounded-lg border border-[#EFEFF1] bg-[#FAFAFB] p-4">
            <div class="flex items-center justify-between gap-2">
                <label for="pp-logo-footer-input" class="font-medium text-[#111]">Logo Footer</label>
                <button type="button" id="pp-logo-footer-reset" class="hidden cursor-pointer text-xs font-medium text-[#226CFF] hover:underline">
                    Reset pratinjau
                </button>
            </div>
            <p class="mt-0.5 text-xs text-[#6E6E76]">Boleh kosong. Jika kosong, otomatis memakai logo utama.</p>

            <div class="mt-3 flex h-32 items-center justify-center overflow-hidden rounded-lg border border-dashed border-[#DEDEE3] bg-white p-3">
                <img id="pp-logo-footer-img"
                     src="{{ $profil['logo_footer_url'] ?? ($profil['logo_url'] ?? '') }}"
                     alt="Pratinjau Logo Footer"
                     class="{{ empty($profil['logo_footer_url']) && empty($profil['logo_url']) ? 'hidden' : '' }} max-h-24 max-w-full object-contain">
                <p id="pp-logo-footer-empty" class="{{ !empty($profil['logo_footer_url']) || !empty($profil['logo_url']) ? 'hidden' : '' }} text-center text-xs text-[#6E6E76]">
                    Kosong (memakai logo utama)
                </p>
            </div>

            <div class="mt-3">
                <input id="pp-logo-footer-input" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                       class="block w-full cursor-pointer text-xs text-[#6E6E76] file:mr-3 file:cursor-pointer file:rounded-[7px] file:border-0 file:bg-[#226CFF] file:px-3.5 file:py-2 file:text-xs file:font-medium file:text-white hover:file:bg-[#1b5ae0]">
                <p id="pp-logo-footer-name" class="mt-1.5 truncate text-xs text-[#6E6E76]">
                    {{ empty($profil['logo_footer_url']) ? 'Kosong (memakai logo utama)' : '' }}
                </p>
            </div>
        </div>
    </div>

    <p class="mt-4 text-xs text-[#6E6E76]">
        Catatan: Penyimpanan logo menyusul pada tahap integrasi unggah berkas. Pratinjau di atas hanya tampil sementara di browser.
    </p>
</section>

<script>
(() => {
    const SERVER_UTAMA = @json($profil['logo_url'] ?? '');
    const SERVER_FOOTER = @json($profil['logo_footer_url'] ?? '');

    const utamaInput = document.getElementById('pp-logo-utama-input');
    const utamaImg = document.getElementById('pp-logo-utama-img');
    const utamaEmpty = document.getElementById('pp-logo-utama-empty');
    const utamaName = document.getElementById('pp-logo-utama-name');
    const utamaReset = document.getElementById('pp-logo-utama-reset');

    const footerInput = document.getElementById('pp-logo-footer-input');
    const footerImg = document.getElementById('pp-logo-footer-img');
    const footerEmpty = document.getElementById('pp-logo-footer-empty');
    const footerName = document.getElementById('pp-logo-footer-name');
    const footerReset = document.getElementById('pp-logo-footer-reset');

    if (!utamaInput || !footerInput) return;

    let previewUtamaUrl = '';
    let previewFooterUrl = '';

    function syncPreview() {
        const aktifUtama = previewUtamaUrl || SERVER_UTAMA;
        if (aktifUtama) {
            utamaImg.src = aktifUtama;
            utamaImg.classList.remove('hidden');
            utamaEmpty.classList.add('hidden');
        } else {
            utamaImg.removeAttribute('src');
            utamaImg.classList.add('hidden');
            utamaEmpty.classList.remove('hidden');
        }
        utamaReset.classList.toggle('hidden', !previewUtamaUrl);

        const aktifFooter = previewFooterUrl || SERVER_FOOTER || aktifUtama;
        if (aktifFooter) {
            footerImg.src = aktifFooter;
            footerImg.classList.remove('hidden');
            footerEmpty.classList.add('hidden');
        } else {
            footerImg.removeAttribute('src');
            footerImg.classList.add('hidden');
            footerEmpty.classList.remove('hidden');
        }
        footerReset.classList.toggle('hidden', !previewFooterUrl);
    }

    utamaInput.addEventListener('change', () => {
        if (previewUtamaUrl) URL.revokeObjectURL(previewUtamaUrl);
        const file = utamaInput.files?.[0];
        if (!file) {
            previewUtamaUrl = '';
            utamaName.textContent = '';
        } else {
            previewUtamaUrl = URL.createObjectURL(file);
            utamaName.textContent = `Pratinjau lokal: ${file.name}`;
        }
        if (!previewFooterUrl && !SERVER_FOOTER) {
            footerName.textContent = 'Kosong (memakai logo utama)';
        }
        syncPreview();
    });

    utamaReset.addEventListener('click', () => {
        if (previewUtamaUrl) URL.revokeObjectURL(previewUtamaUrl);
        previewUtamaUrl = '';
        utamaInput.value = '';
        utamaName.textContent = '';
        syncPreview();
    });

    footerInput.addEventListener('change', () => {
        if (previewFooterUrl) URL.revokeObjectURL(previewFooterUrl);
        const file = footerInput.files?.[0];
        if (!file) {
            previewFooterUrl = '';
            footerName.textContent = SERVER_FOOTER ? '' : 'Kosong (memakai logo utama)';
        } else {
            previewFooterUrl = URL.createObjectURL(file);
            footerName.textContent = `Pratinjau lokal: ${file.name}`;
        }
        syncPreview();
    });

    footerReset.addEventListener('click', () => {
        if (previewFooterUrl) URL.revokeObjectURL(previewFooterUrl);
        previewFooterUrl = '';
        footerInput.value = '';
        footerName.textContent = SERVER_FOOTER ? '' : 'Kosong (memakai logo utama)';
        syncPreview();
    });
})();
</script>