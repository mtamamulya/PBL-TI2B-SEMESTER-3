{{-- FASE 2A — Kontak & Media Sosial (semua kolom opsional/boleh kosong) --}}
<section id="sec-kontak-sosial" class="rounded-lg border border-[#EFEFF1] bg-white p-[25px] shadow-[0_1px_3px_rgba(0,0,0,0.07)]">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 class="text-xl font-semibold text-[#111]">Kontak &amp; Media Sosial</h2>
            <p class="mt-1 text-sm text-[#6E6E76]">Semua kolom boleh dikosongkan. Ikon media sosial yang kosong otomatis disembunyikan di landing page.</p>
        </div>
        <span id="pp-kontak-toast" class="hidden rounded-md border border-[#B7EB8F] bg-[#F6FFED] px-3 py-1 text-xs font-medium text-[#237804]" role="status"></span>
    </div>

    <form id="pp-kontak-form" class="mt-5 space-y-4" novalidate>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="pp-email" class="mb-1.5 block text-sm font-medium text-[#6E6E76]">Email Prodi</label>
                <input id="pp-email" name="email" type="text" inputmode="email" maxlength="100"
                       value="{{ $profil['email'] ?? '' }}"
                       placeholder="Contoh: trk@polines.ac.id"
                       class="h-10 w-full rounded-[7px] border border-[#DEDEE3] px-3 text-sm text-[#111] outline-none placeholder:text-[#9E9EA6] focus:border-[#226CFF]">
                <p data-error="email" class="mt-1 hidden text-xs text-[#E5484D]"></p>
            </div>

            <div>
                <label for="pp-no-wa" class="mb-1.5 block text-sm font-medium text-[#6E6E76]">Nomor WhatsApp</label>
                <input id="pp-no-wa" name="no_wa" type="text" inputmode="tel" maxlength="25"
                       value="{{ $profil['no_wa'] ?? '' }}"
                       placeholder="Contoh: 081234567890"
                       class="h-10 w-full rounded-[7px] border border-[#DEDEE3] px-3 text-sm text-[#111] outline-none placeholder:text-[#9E9EA6] focus:border-[#226CFF]">
                <p data-error="no_wa" class="mt-1 hidden text-xs text-[#E5484D]"></p>
            </div>
        </div>

        <div>
            <label for="pp-alamat" class="mb-1.5 block text-sm font-medium text-[#6E6E76]">Alamat Kampus / Gedung</label>
            <textarea id="pp-alamat" name="alamat" rows="2" maxlength="500"
                      placeholder="Contoh: Jl. Prof. Soedarto, S.H., Tembalang, Semarang"
                      class="w-full rounded-[7px] border border-[#DEDEE3] p-3 text-sm text-[#111] outline-none placeholder:text-[#9E9EA6] focus:border-[#226CFF]">{{ $profil['alamat'] ?? '' }}</textarea>
            <p data-error="alamat" class="mt-1 hidden text-xs text-[#E5484D]"></p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="pp-instagram" class="mb-1.5 block text-sm font-medium text-[#6E6E76]">Instagram (URL)</label>
                <input id="pp-instagram" name="instagram" type="text" inputmode="url" maxlength="255"
                       value="{{ $profil['instagram'] ?? '' }}"
                       placeholder="https://instagram.com/..."
                       class="h-10 w-full rounded-[7px] border border-[#DEDEE3] px-3 text-sm text-[#111] outline-none placeholder:text-[#9E9EA6] focus:border-[#226CFF]">
                <p data-error="instagram" class="mt-1 hidden text-xs text-[#E5484D]"></p>
            </div>

            <div>
                <label for="pp-youtube" class="mb-1.5 block text-sm font-medium text-[#6E6E76]">YouTube (URL)</label>
                <input id="pp-youtube" name="youtube" type="text" inputmode="url" maxlength="255"
                       value="{{ $profil['youtube'] ?? '' }}"
                       placeholder="https://youtube.com/@..."
                       class="h-10 w-full rounded-[7px] border border-[#DEDEE3] px-3 text-sm text-[#111] outline-none placeholder:text-[#9E9EA6] focus:border-[#226CFF]">
                <p data-error="youtube" class="mt-1 hidden text-xs text-[#E5484D]"></p>
            </div>

            <div>
                <label for="pp-tiktok" class="mb-1.5 block text-sm font-medium text-[#6E6E76]">TikTok (URL)</label>
                <input id="pp-tiktok" name="tiktok" type="text" inputmode="url" maxlength="255"
                       value="{{ $profil['tiktok'] ?? '' }}"
                       placeholder="https://tiktok.com/@..."
                       class="h-10 w-full rounded-[7px] border border-[#DEDEE3] px-3 text-sm text-[#111] outline-none placeholder:text-[#9E9EA6] focus:border-[#226CFF]">
                <p data-error="tiktok" class="mt-1 hidden text-xs text-[#E5484D]"></p>
            </div>

            <div>
                <label for="pp-facebook" class="mb-1.5 block text-sm font-medium text-[#6E6E76]">Facebook (URL)</label>
                <input id="pp-facebook" name="facebook" type="text" inputmode="url" maxlength="255"
                       value="{{ $profil['facebook'] ?? '' }}"
                       placeholder="https://facebook.com/..."
                       class="h-10 w-full rounded-[7px] border border-[#DEDEE3] px-3 text-sm text-[#111] outline-none placeholder:text-[#9E9EA6] focus:border-[#226CFF]">
                <p data-error="facebook" class="mt-1 hidden text-xs text-[#E5484D]"></p>
            </div>
        </div>

        <p id="pp-kontak-general-error" class="hidden text-sm text-[#E5484D]"></p>

        <div class="flex justify-end pt-1">
            <button type="submit" id="pp-kontak-simpan"
                    class="inline-flex h-[39px] cursor-pointer items-center justify-center rounded-[7px] bg-[#226CFF] px-[17px] text-sm font-medium text-white transition-colors hover:bg-[#1b5ae0] disabled:cursor-not-allowed disabled:opacity-60">
                Simpan Kontak &amp; Sosial
            </button>
        </div>
    </form>
</section>

<script>
(() => {
    const URL_UPDATE = @json(route(($prefix ?? 'admin').'.prodi.profil.update'));
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
    const FIELDS = ['email', 'no_wa', 'alamat', 'instagram', 'youtube', 'tiktok', 'facebook'];

    const form = document.getElementById('pp-kontak-form');
    const btn = document.getElementById('pp-kontak-simpan');
    const toast = document.getElementById('pp-kontak-toast');
    const generalErr = document.getElementById('pp-kontak-general-error');
    if (!form) return;

    let toastTimer = null;

    function resetErrors() {
        generalErr.textContent = '';
        generalErr.classList.add('hidden');
        form.querySelectorAll('[data-error]').forEach((el) => {
            el.textContent = '';
            el.classList.add('hidden');
        });
        form.querySelectorAll('input, textarea').forEach((el) => {
            el.classList.remove('border-[#E5484D]');
            el.classList.add('border-[#DEDEE3]');
        });
    }

    function showToast(msg) {
        toast.textContent = msg;
        toast.classList.remove('hidden');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.add('hidden'), 3500);
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        resetErrors();

        const payload = {};
        FIELDS.forEach((f) => { payload[f] = form[f].value; });

        btn.disabled = true;
        const labelAsli = btn.textContent;
        btn.textContent = 'Menyimpan...';

        try {
            const res = await fetch(URL_UPDATE, {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify(payload),
            });
            const json = await res.json().catch(() => ({}));

            if (res.status === 422 && json.errors) {
                Object.entries(json.errors).forEach(([field, messages]) => {
                    const errEl = form.querySelector(`[data-error="${field}"]`);
                    const inputEl = form.querySelector(`[name="${field}"]`);
                    if (errEl) {
                        errEl.textContent = messages[0] ?? 'Input tidak valid.';
                        errEl.classList.remove('hidden');
                    }
                    if (inputEl) {
                        inputEl.classList.remove('border-[#DEDEE3]');
                        inputEl.classList.add('border-[#E5484D]');
                    }
                });
                return;
            }

            if (!res.ok || !json.ok) {
                throw new Error(json.message || 'Gagal menyimpan kontak & media sosial.');
            }

            if (json.data) {
                FIELDS.forEach((f) => {
                    form[f].value = json.data[f] ?? '';
                });
            }
            showToast('Kontak & media sosial berhasil disimpan.');
        } catch (err) {
            generalErr.textContent = err.message || 'Terjadi kesalahan saat menyimpan.';
            generalErr.classList.remove('hidden');
        } finally {
            btn.disabled = false;
            btn.textContent = labelAsli;
        }
    });
})();
</script>