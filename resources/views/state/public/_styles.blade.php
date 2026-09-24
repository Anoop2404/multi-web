{{-- Shared table/chip styling for the public State pages. Kept in one partial so the verification
     page, which has no nav, still renders its table the same way. --}}
<style>
    .portal-chip {
        display:inline-block;padding:.4rem .85rem;border-radius:999px;border:1px solid #cbd5e1;
        font-size:.8rem;color:#1e3a5f;text-decoration:none;background:#fff;
    }
    .portal-chip:hover { background:#f1f5f9; }
    .state-table { width:100%;border-collapse:collapse;font-size:.875rem; }
    .state-table th { text-align:left;color:#64748b;border-bottom:2px solid #e2e8f0;padding:.6rem .5rem;font-weight:600; }
    .state-table td { padding:.6rem .5rem;border-bottom:1px solid #f1f5f9;vertical-align:top; }
    .pos-badge { display:inline-block;min-width:1.6rem;text-align:center;border-radius:.4rem;padding:.1rem .4rem;font-weight:700;font-size:.75rem; }
    .pos-1 { background:#fef3c7;color:#92400e; }
    .pos-2 { background:#e2e8f0;color:#475569; }
    .pos-3 { background:#fde8d7;color:#9a3412; }
</style>
