{{-- FASE 2A — Deskripsi Singkat & Visi Program Studi --}}
<section id="sec-visi-deskripsi" class="rounded-lg border border-[#EFEFF1] bg-white p-[25px] shadow-[0_1px_3px_rgba(0,0,0,0.07)]">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 class="text-xl font-semibold text-[#111]">Deskripsi &amp; Visi</h2>
            <p class="mt-1 text-sm text-[#6E6E76]">Ringkasan profil program studi dan pernyataan visi yang ditampilkan di halaman publik.</p>
        </div>
        <span id="pp-visi-toast" class="hidden rounded-md border border-[#B7EB8F] bg-[#F6FFED] px-3 py-1 text-xs font-medium text-[#237804]" role="status"></span>
    </div>

    <form id="pp-visi-form" class="mt-5 space-y-4" novalidate>
        <div>
            <label for="pp-deskripsi" class="mb-1.5 block text-sm font-medium text-[#6E6E76]">Deskripsi Program Studi</label>
            <textarea id="pp-deskripsi" name="deskripsi" rows="4" maxlength="2000"
                      placeholder="Tuliskan gambaran umum program studi (maks. 2000 karakter)..."
                      class="w-full rounded-[7px] border border-[#DEDEE3] p-3 text-sm text-[#111] outline-none placeholder:text-[#9E9EA6] focus:border-[#226CFF]">{{ $profil['deskripsi'] ?? '' }}</textarea>
            <p data-error="deskripsi" class="mt-1 hidden text-xs text-[#E5484D]"></p>
        </div>

        <div>
            <label for="pp-visi" class="mb-1.5 block text-sm font-medium text-[#6E6E76]">Visi</label>
            <textarea id="pp-visi" name="visi" rows="3" maxlength="1000"
                      placeholder="Tuliskan visi program studi (maks. 1000 karakter)..."
                      class="w-full rounded-[7px] border border-[#DEDEE3] p-3 text-sm text-[#111] outline-none placeholder:text-[#9E9EA6] focus:border-[#226CFF]">{{ $profil['visi'] ?? '' }}</textarea>
            <p data-error="visi" class="mt-1 hidden text-xs text-[#E5484D]"></p>
        </div>

        <p id="pp-visi-general-error" class="hidden text-sm text-[#E5484D]"></p>

        <div class="flex justify-end pt-1">
            <button type="submit" id="pp-visi-simpan"
                    class="inline-flex h-[39px] cursor-pointer items-center justify-center rounded-[7px] bg-[#226CFF] px-[17px] text-sm font-medium text-white transition-colors hover:bg-[#1b5ae0] disabled:cursor-not-allowed disabled:opacity-60">
                Simpan Deskripsi &amp; Visi
            </button>
        </div>
    </form>
</section>

<script>
(() => {
    const URL_UPDATE = @json(route(($prefix ?? 'admin').'.prodi.profil.update'));
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());

    const form = document.getElementById('pp-visi-form');
    const btn = document.getElementById('pp-visi-simpan');
    const toast = document.getElementById('pp-visi-toast');
    const generalErr = document.getElementById('pp-visi-general-error');
    if (!form) return;

    let toastTimer = null;

    function resetErrors() {
        generalErr.textContent = '';
        generalErr.classList.add('hidden');
        form.querySelectorAll('[data-error]').forEach((el) => {
            el.textContent = '';
            el.classList.add('hidden');
        });
        form.querySelectorAll('textarea').forEach((el) => {
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

        const payload = {
            deskripsi: form.deskripsi.value,
            visi: form.visi.value,
        };

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
                throw new Error(json.message || 'Gagal menyimpan deskripsi dan visi.');
            }

            if (json.data) {
                form.deskripsi.value = json.data.deskripsi ?? '';
                form.visi.value = json.data.visi ?? '';
            }
            showToast('Deskripsi & visi berhasil disimpan.');
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