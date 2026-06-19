<div class="portal-logo-wrap">
    @if(!empty($logoUrl))
        <img src="{{ $logoUrl }}" alt="{{ $tenant->name ?? 'Logo' }}" class="portal-logo"
             onerror="this.style.display='none';this.nextElementSibling&&(this.nextElementSibling.style.display='flex');">
        @if(!empty($tenant))
            <div class="portal-avatar" style="display:none">{{ strtoupper(substr($tenant->name, 0, 1)) }}</div>
        @endif
    @elseif(!empty($tenant))
        <div class="portal-avatar">{{ strtoupper(substr($tenant->name, 0, 1)) }}</div>
    @endif
</div>
