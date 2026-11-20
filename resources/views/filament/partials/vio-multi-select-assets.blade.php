{{-- Shared Alpine multi-select (Select2-like) used by New fines / Wheelsys import filters. --}}
<script>
    document.addEventListener('alpine:init', () => {
        if (window.__vioMultiSelectRegistered) {
            return;
        }
        window.__vioMultiSelectRegistered = true;

        Alpine.data('vioMultiSelect', (cfg) => ({
            options: cfg.options || [],
            selected: Array.isArray(cfg.selected) ? cfg.selected.map(String) : [],
            placeholder: cfg.placeholder || 'Select…',
            onChange: cfg.onChange || (() => {}),
            open: false,
            query: '',
            get filtered() {
                const q = this.query.trim().toLowerCase();
                if (! q) return this.options;
                return this.options.filter((o) => String(o.label).toLowerCase().includes(q));
            },
            labelFor(value) {
                const hit = this.options.find((o) => String(o.value) === String(value));
                return hit ? hit.label : value;
            },
            toggle(value) {
                value = String(value);
                if (this.selected.includes(value)) {
                    this.selected = this.selected.filter((v) => v !== value);
                } else {
                    this.selected = [...this.selected, value];
                }
                this.onChange(this.selected);
            },
        }));
    });
</script>
<style>
    [x-cloak] { display: none !important; }
    .vio-ms { position: relative; }
    .vio-ms-control {
        position: relative;
        width: 100%; min-height: 42px; display: flex; flex-wrap: wrap; align-items: center; gap: 6px;
        padding: 8px 28px 8px 10px; border: 1px solid #cbd5e1; border-radius: 0.5rem;
        background: #fff; text-align: left; cursor: pointer; box-shadow: 0 1px 2px rgba(15,23,42,.04);
    }
    .vio-ms-control:hover { border-color: #94a3b8; }
    .vio-ms-placeholder { color: #94a3b8; font-size: 13px; }
    .vio-ms-chip {
        display: inline-flex; align-items: center; gap: 4px; max-width: 100%;
        padding: 2px 8px; border-radius: 999px; background: #eef2f6; color: #223a4e;
        font-size: 12px; font-weight: 600; line-height: 1.35;
    }
    .vio-ms-chip-x {
        border: 0; background: transparent; color: #64748b; cursor: pointer; font-size: 14px; line-height: 1; padding: 0 2px;
    }
    .vio-ms-caret { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 11px; pointer-events: none; }
    .vio-ms-panel {
        position: absolute; z-index: 40; left: 0; right: 0; top: calc(100% + 4px);
        background: #fff; border: 1px solid #e2e8f0; border-radius: 0.65rem;
        box-shadow: 0 12px 28px rgba(15,23,42,.12); overflow: hidden;
    }
    .vio-ms-search {
        width: 100%; border: 0; border-bottom: 1px solid #e2e8f0; padding: 10px 12px;
        font-size: 13px; outline: none;
    }
    .vio-ms-list { list-style: none; margin: 0; padding: 4px; max-height: 220px; overflow: auto; }
    .vio-ms-option {
        width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 8px;
        border: 0; background: transparent; text-align: left; padding: 8px 10px; border-radius: 0.45rem;
        font-size: 13px; color: #0f172a; cursor: pointer;
    }
    .vio-ms-option:hover { background: #f1f5f9; }
    .vio-ms-option.is-on { background: #ecfdf5; color: #166534; font-weight: 600; }
    .vio-ms-check { color: #16a34a; font-weight: 700; }
    .vio-ms-empty { padding: 10px 12px; font-size: 12px; color: #94a3b8; }
</style>
