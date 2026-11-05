<?php /** Panel de sincronización de catálogo local. Requiere $code y $sync */ ?>
<div x-data="syncPanel(<?= e(json_encode(['code' => $code, 'sync' => $sync])) ?>)" x-init="poll()" class="rounded-lg border border-slate-200 p-4 text-sm dark:border-slate-700">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="font-medium">Catálogo local</div>
            <div class="text-slate-500 dark:text-slate-400">
                <span x-text="s.count ? Number(s.count).toLocaleString('es-MX') + ' productos' : 'Sin sincronizar'"></span>
                <span x-show="s.synced_at" x-text="' · actualizado ' + s.synced_at"></span>
            </div>
        </div>
        <button type="button" class="btn btn-secondary" @click="start()" :disabled="busy || s.status === 'running' || s.status === 'queued'">
            <span x-text="s.status === 'running' ? 'Sincronizando…' : (s.status === 'queued' ? 'En cola…' : 'Sincronizar ahora')"></span>
        </button>
    </div>
    <div x-show="s.status === 'running' && s.last_page" class="mt-3">
        <div class="h-2 overflow-hidden rounded bg-slate-200 dark:bg-slate-800"><div class="h-full bg-brand transition-all" :style="`width:${Math.round(s.page / s.last_page * 100)}%`"></div></div>
    </div>
    <p class="mt-2 text-xs" :class="s.status === 'error' ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400'"
       x-show="s.message || error" x-text="error || ((s.status === 'done' && s.finished_at ? 'Última: ' + s.finished_at + ' · ' : '') + (s.message || ''))"></p>
</div>
<?php if (empty($GLOBALS['__syncPanelJs'])): $GLOBALS['__syncPanelJs'] = true; ?>
<script>
function syncPanel(cfg) {
    const base = <?= json_encode(url('/proveedores/')) ?> + cfg.code;
    return {
        s: cfg.sync || {}, busy: false, error: '', timer: null,
        async start() {
            this.busy = true; this.error = '';
            try {
                const res = await fetch(base + '/sincronizar', { method: 'POST', headers: { 'X-CSRF-Token': csrfToken(), Accept: 'application/json' } });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'Error ' + res.status);
                this.s = data; this.poll();
            } catch (e) { this.error = e.message; } finally { this.busy = false; }
        },
        poll() {
            clearTimeout(this.timer);
            if (!['queued', 'running'].includes(this.s.status)) return;
            this.timer = setTimeout(async () => {
                try { this.s = await (await fetch(base + '/sincronizacion', { headers: { Accept: 'application/json' } })).json(); } catch (e) {}
                this.poll();
            }, 3000);
        },
    };
}
</script>
<?php endif; ?>
