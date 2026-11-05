<?php
/**
 * Selector de cliente con búsqueda y alta rápida.
 * Variables: $pickerModel (expresión Alpine donde se guarda el cliente: objeto o null)
 */
?>
<div x-data="clientPicker()" class="relative" @click.outside="open = false">
    <template x-if="<?= $pickerModel ?>">
        <div class="flex items-start justify-between gap-3 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
            <div class="text-sm">
                <div class="font-medium" x-text="<?= $pickerModel ?>.name"></div>
                <div class="text-xs text-slate-500 dark:text-slate-400" x-text="[<?= $pickerModel ?>.company_name, <?= $pickerModel ?>.rfc, <?= $pickerModel ?>.email].filter(Boolean).join(' · ')"></div>
            </div>
            <button type="button" class="text-xs text-brand hover:underline dark:text-sky-400" :disabled="readonly" x-show="!readonly" @click="<?= $pickerModel ?> = null; $nextTick(() => $refs.q?.focus())">Cambiar</button>
        </div>
    </template>
    <div x-show="!<?= $pickerModel ?>">
        <input type="search" x-ref="q" x-model="q" @input.debounce.250ms="search()" @focus="open = true; search()" class="input" placeholder="Buscar cliente por nombre, empresa o RFC…" autocomplete="off">
        <div x-cloak x-show="open" class="card absolute z-30 mt-1 max-h-72 w-full overflow-y-auto p-1">
            <template x-for="c in results" :key="c.id">
                <button type="button" class="block w-full rounded px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800" @click="<?= $pickerModel ?> = c; open = false; q = ''">
                    <span class="font-medium" x-text="c.name"></span>
                    <span class="block text-xs text-slate-500" x-text="[c.company_name, c.rfc].filter(Boolean).join(' · ')"></span>
                </button>
            </template>
            <p x-show="!results.length" class="px-3 py-2 text-sm text-slate-500">Sin coincidencias.</p>
            <button type="button" class="mt-1 block w-full rounded border-t border-slate-100 px-3 py-2 text-left text-sm font-medium text-brand hover:bg-slate-100 dark:border-slate-800 dark:text-sky-400 dark:hover:bg-slate-800" @click="openNew()">+ Nuevo cliente<span x-show="q" x-text="' «' + q + '»'"></span></button>
        </div>
    </div>

    <!-- Alta rápida -->
    <div x-cloak x-show="creating" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="creating = false">
        <!-- Sin <form>: este selector vive dentro de otros formularios y HTML no permite anidarlos -->
        <div class="card w-full max-w-lg space-y-3 p-6" @keydown.enter.prevent="create(c => { <?= $pickerModel ?> = c })" @click.outside="creating = false">
            <h2 class="text-lg font-semibold">Nuevo cliente</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <template x-for="f in fields" :key="f.k">
                    <div :class="f.wide && 'sm:col-span-2'">
                        <label class="label" x-text="f.label"></label>
                        <!-- Sin required: estos campos ocultos quedan dentro del formulario de la página y bloquearían su envío -->
                        <input class="input" :type="f.type === 'email' ? 'text' : (f.type || 'text')" :inputmode="f.type === 'email' ? 'email' : null" x-model="form[f.k]" :class="errors[f.k] && 'input-error'">
                        <p class="field-error" x-show="errors[f.k]" x-text="errors[f.k]"></p>
                    </div>
                </template>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="btn btn-secondary" @click="creating = false">Cancelar</button>
                <button type="button" class="btn btn-primary" :disabled="saving" @click="create(c => { <?= $pickerModel ?> = c })">Crear cliente</button>
            </div>
        </div>
    </div>
</div>
<?php if (empty($GLOBALS['__clientPickerJs'])): $GLOBALS['__clientPickerJs'] = true; ?>
<script>
function clientPicker() {
    return {
        q: '', results: [], open: false, creating: false, saving: false, form: {}, errors: {},
        get readonly() { return this.$data.state ? !this.$data.state.editable : false; },
        fields: [
            { k: 'name', label: 'Nombre del contacto', required: true },
            { k: 'company_name', label: 'Empresa' },
            { k: 'rfc', label: 'RFC' },
            { k: 'email', label: 'Correo', type: 'email' },
            { k: 'phone', label: 'Teléfono' },
            { k: 'address', label: 'Dirección', wide: true },
        ],
        async search() {
            const res = await fetch(<?= json_encode(url('/clientes/buscar')) ?> + '?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } });
            this.results = res.ok ? await res.json() : [];
        },
        openNew() { this.form = { name: this.q }; this.errors = {}; this.creating = true; this.open = false; },
        async create(done) {
            if (!(this.form.name || '').trim()) { this.errors = { name: 'El nombre es obligatorio.' }; return; }
            this.saving = true; this.errors = {};
            const body = new URLSearchParams(Object.assign({ _csrf: csrfToken() }, this.form));
            try {
                const res = await fetch(<?= json_encode(url('/clientes')) ?>, { method: 'POST', body, headers: { Accept: 'application/json' } });
                const data = await res.json();
                if (!res.ok) { this.errors = data.errors || {}; return; }
                done(data); this.creating = false; this.q = '';
            } finally { this.saving = false; }
        },
    };
}
</script>
<?php endif; ?>
