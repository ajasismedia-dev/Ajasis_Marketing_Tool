<div class="stats-grid">
    <div class="stat-card glass-panel">
        <div class="stat-header">
            <span>Toplam Firma</span>
            <i data-lucide="building-2" class="stat-icon"></i>
        </div>
        <div class="stat-value"><?= $total_companies ?></div>
    </div>
    
    <div class="stat-card glass-panel">
        <div class="stat-header">
            <span>Yeni Lead</span>
            <i data-lucide="user-plus" class="stat-icon"></i>
        </div>
        <div class="stat-value"><?= $new_leads ?></div>
    </div>
    
    <div class="stat-card glass-panel">
        <div class="stat-header">
            <span>İletişime Geçilen</span>
            <i data-lucide="message-square" class="stat-icon"></i>
        </div>
        <div class="stat-value"><?= $contacted ?></div>
    </div>
    
    <div class="stat-card glass-panel">
        <div class="stat-header">
            <span>Müşteriye Dönüşen</span>
            <i data-lucide="check-circle" class="stat-icon"></i>
        </div>
        <div class="stat-value"><?= $converted ?></div>
    </div>
</div>

<div class="empty-state glass-panel">
    <i data-lucide="inbox"></i>
    <p>Henüz pazarlama verisi bulunmuyor.</p>
</div>
