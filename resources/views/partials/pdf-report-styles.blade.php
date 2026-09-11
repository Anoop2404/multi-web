{{-- Shared "premium" report styling for paginated per-day PDF reports (item-schedule,
     schedule-clashes). Kept DomPDF-safe: no flexbox/grid, plain borders and background
     colors only, since the fallback PDF engine doesn't render those reliably. --}}
<style>
    body{font-family:'DejaVu Sans',sans-serif;font-size:10.5px;color:#1e293b;margin:0;padding:0}
    .report-title{font-size:17px;font-weight:800;text-align:center;color:#0f172a;margin:6px 0 3px;letter-spacing:.2px}
    .report-meta{text-align:center;margin:0 0 14px}
    .badge{display:inline-block;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;border-radius:9px;padding:2px 9px;margin:0 3px;font-size:9px;font-weight:700;letter-spacing:.2px}
    .badge-dark{background:#0f172a;color:#ffffff;border-color:#0f172a}
    .badge-warn{background:#fffbeb;color:#92400e;border-color:#fde68a}
    .day-page{page-break-inside:auto;padding:40px 42px 36px}
    .day-band{background:#0f172a;color:#ffffff;padding:9px 14px;font-size:13px;font-weight:800;border-radius:5px;margin:18px 0 9px;letter-spacing:.2px}
    .day-band .count{font-weight:400;color:#cbd5e1;font-size:10px}
    .stage-band td{background:#eef2ff;color:#3730a3;font-weight:700;font-size:9.5px;padding:5px 10px;border-left:3px solid #4f46e5;border-top:1px solid #e0e7ff;border-bottom:1px solid #e0e7ff}
    table{width:100%;border-collapse:collapse;margin-bottom:6px}
    thead th{background:#1e293b;color:#ffffff;font-size:9px;text-transform:uppercase;letter-spacing:.5px;padding:7px 8px;text-align:left;border:1px solid #1e293b}
    tbody td{padding:6px 8px;font-size:9.5px;border:1px solid #e2e8f0;vertical-align:top}
    tbody tr.row-even td{background:#f8fafc}
    small{color:#64748b}
</style>
