{{-- FASE 2A — Identitas Prodi (kode_prodi, nama_prodi, jenjang) --}}
<section id="sec-identitas" class="rounded-lg border border-[#EFEFF1] bg-white p-[25px] shadow-[0_1px_3px_rgba(0,0,0,0.07)]">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 class="text-xl font-semibold text-[#111]">Identitas Program Studi</h2>
            <p class="mt-1 text-sm text-[#6E6E76]">Kode, nama resmi, dan jenjang pendidikan program studi.</p>
        </div>
        <span id="pp-identitas-toast" class="hidden rounded-md border border-[#B7EB8F] bg-[#F6FFED] px-3 py-1 text-xs font-medium text-[#237804]" role="status"></span>
    </div>

    <form id="pp-identitas-form" class="mt-5 space-y-4" novalidate>
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label for="pp-kode-prodi" class="mb-1.5 block text-sm font-medium text-[#6E6E76]">
                    Kode Prodi <span class="text-[#E5484D]">*</span>
                </label>
                <input id="pp-kode-prodi" name="kode_prodi" type="text" maxlength="20"
                       value="{{ $profil['kode_prodi'] ?? '' }}"
                       placeholder="Contoh: TRK"
                       class="h-10 w-full rounded-[7px] border border-[#DEDEE3] px-3 text-sm text-[#111] outline-none placeholder:text-[#9E9EA6] focus:border-[#226CFF]">
                <p data-error="kode_prodi" class="mt-1 hidden text-xs text-[#E5484D]"></p>
            </div>

            <div class="md:col-span-2">
                <label for="pp-nama-prodi" class="mb-1.5 block text-sm font-medium text-[#6E6E76]">
                    Nama Program Studi <span class="text-[#E5484D]">*</span>
                </label>
                <input id="pp-nama-prodi" name="nama_prodi" type="text" maxlength="100"
                       value="{{ $profil['nama_prodi'] ?? '' }}"
                       placeholder="Contoh: Teknologi Rekayasa Komputer"
                       class="h-10 w-full rounded-[7px] border border-[#DEDEE3] px-3 text-sm text-[#111] outline-none placeholder:text-[#9E9EA6] focus:border-[#226CFF]">
                <p data-error="nama_prodi" class="mt-1 hidden text-xs text-[#E5484D]"></p>
            </div>
        </div>

        <div class="max-w-xs">
            <label for="pp-jenjang" class="mb-1.5 block text-sm font-medium text-[#6E6E76]">
                Jenjang <span class="text-[#E5484D]">*</span>
            </label>
            <select id="pp-jenjang" name="jenjang"
                    class="h-10 w-full rounded-[7px] border border-[#DEDEE3] bg-white px-3 text-sm text-[#111] outline-none focus:border-[#226CFF]">
                <option value="D3" @selected(($profil['jenjang'] ?? 'D4') === 'D3')>D3 (Diploma III)</option>
                <option value="D4" @selected(($profil['jenjang'] ?? 'D4') === 'D4')>D4 (Sarjana Terapan)</option>
            </select>
            <p data-error="jenjang" class="mt-1 hidden text-xs text-[#E5484D]"></p>
        </div>

        <p id="pp-identitas-general-error" class="hidden text-sm text-[#E5484D]"></p>

        <div class="flex justify-end pt-1">
            <button type="submit" id="pp-identitas-simpan"
                    class="inline-flex h-[39px] cursor-pointer items-center justify-center rounded-[7px] bg-[#226CFF] px-[17px] text-sm font-medium text-white transition-colors hover:bg-[#1b5ae0] disabled:cursor-not-allowed disabled:opacity-60">
                Simpan Identitas
            </button>
        </div>
    </form>
</section>

<script>
(() => {
    const URL_UPDATE = @json(route(($prefix ?? 'admin').'.prodi.profil.update'));
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());

    const form = document.getElementById('pp-identitas-form');
    const btn = document.getElementById('pp-identitas-simpan');
    const toast = document.getElementById('pp-identitas-toast');
    const generalErr = document.getElementById('pp-identitas-general-error');
    if (!form) return;

    let toastTimer = null;

    function resetErrors() {
        generalErr.textContent = '';
        generalErr.classList.add('hidden');
        form.querySelectorAll('[data-error]').forEach((el) => {
            el.textContent = '';
            el.classList.add('hidden');
        });
        form.querySelectorAll('input, select').forEach((el) => {
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
            kode_prodi: form.kode_prodi.value,
            nama_prodi: form.nama_prodi.value,
            jenjang: form.jenjang.value,
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
                throw new Error(json.message || 'Gagal menyimpan identitas prodi.');
            }

            if (json.data) {
                form.kode_prodi.value = json.data.kode_prodi ?? '';
                form.nama_prodi.value = json.data.nama_prodi ?? '';
                form.jenjang.value = json.data.jenjang ?? 'D4';
            }
            showToast('Identitas prodi berhasil disimpan.');
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