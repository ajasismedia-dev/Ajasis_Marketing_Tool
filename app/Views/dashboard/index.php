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

<?php if(empty($latest_companies)): ?>
    <div class="empty-state glass-panel">
        <i data-lucide="inbox"></i>
        <p>Henüz pazarlama verisi bulunmuyor.</p>
    </div>
<?php else: ?>
    <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem;">Son Eklenen Firmalar</h3>
    <div class="glass-panel" style="overflow-x: auto;">
        <table style="width: 100%; text-align: left; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 1px solid var(--card-border); color: var(--text-secondary);">
                    <th style="padding: 1rem;">Firma</th>
                    <th style="padding: 1rem;">Sektör</th>
                    <th style="padding: 1rem;">Durum</th>
                    <th style="padding: 1rem;">Tarih</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $statusLabels = [
                    'new' => 'Yeni',
                    'contacted' => 'İletişime Geçildi',
                    'replied' => 'Cevap Verdi',
                    'proposal' => 'Teklif Gönderildi',
                    'customer' => 'Müşteri',
                    'negative' => 'Olumsuz'
                ];
                foreach($latest_companies as $company): ?>
                <tr style="border-bottom: 1px solid var(--card-border);">
                    <td style="padding: 1rem; font-weight: 500;">
                        <a href="<?= BASE_PATH ?>/companies/show/<?= $company['id'] ?>" style="color: var(--color-primary); text-decoration: none;">
                            <?= \App\Helpers\Security::escape($company['name']) ?>
                        </a>
                    </td>
                    <td style="padding: 1rem; color: var(--text-secondary);"><?= \App\Helpers\Security::escape($company['sector']) ?></td>
                    <td style="padding: 1rem;">
                        <span class="status-badge status-<?= $company['status'] ?>">
                            <?= $statusLabels[$company['status']] ?>
                        </span>
                    </td>
                    <td style="padding: 1rem; color: var(--text-secondary);">
                        <?= date('d.m.Y', strtotime($company['created_at'])) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
