<?php
$statusLabels = [
    'new' => 'Yeni',
    'contacted' => 'İletişime Geçildi',
    'replied' => 'Cevap Verdi',
    'proposal' => 'Teklif Gönderildi',
    'customer' => 'Müşteri',
    'negative' => 'Olumsuz'
];
?>

<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
    <div>
        <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;"><?= \App\Helpers\Security::escape($company['name']) ?></h2>
        <div style="display: flex; gap: 1rem; align-items: center; color: var(--text-secondary); font-size: 0.875rem;">
            <span><i data-lucide="briefcase" style="width: 16px; height: 16px; margin-right: 4px;"></i> <?= \App\Helpers\Security::escape($company['sector']) ?: 'Belirtilmemiş' ?></span>
            <span><i data-lucide="map-pin" style="width: 16px; height: 16px; margin-right: 4px;"></i> <?= \App\Helpers\Security::escape($company['district']) ? \App\Helpers\Security::escape($company['district']) . ' / ' : '' ?><?= \App\Helpers\Security::escape($company['city']) ?></span>
            <span class="status-badge status-<?= $company['status'] ?>"><?= $statusLabels[$company['status']] ?></span>
        </div>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <form action="<?= BASE_PATH ?>/companies/markContacted/<?= $company['id'] ?>" method="POST" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
            <button type="submit" class="btn btn-primary" title="İletişime Geçildi Olarak İşaretle">
                <i data-lucide="check-circle"></i> İletişime Geçildi
            </button>
        </form>
        <a href="<?= BASE_PATH ?>/companies/edit/<?= $company['id'] ?>" class="btn" style="background: rgba(255,255,255,0.1); color: #fff;">
            <i data-lucide="edit"></i> Düzenle
        </a>
        <form action="<?= BASE_PATH ?>/companies/delete/<?= $company['id'] ?>" method="POST" style="display: inline;" onsubmit="return confirm('Bu firmayı silmek istediğinize emin misiniz?');">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
            <button type="submit" class="btn" style="background: rgba(239, 68, 68, 0.15); color: #f87171;">
                <i data-lucide="trash-2"></i> Sil
            </button>
        </form>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;" class="form-grid">
    <!-- İletişim Bilgileri -->
    <div class="glass-panel" style="padding: 1.5rem;">
        <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem; border-bottom: 1px solid var(--card-border); padding-bottom: 0.5rem;">İletişim Bilgileri</h3>
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <?php if($company['phone']): ?>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <i data-lucide="phone" class="stat-icon" style="width:18px;height:18px;"></i>
                <a href="tel:<?= \App\Helpers\Security::escape($company['phone']) ?>" style="color: var(--text-primary); text-decoration: none;"><?= \App\Helpers\Security::escape($company['phone']) ?></a>
            </div>
            <?php endif; ?>
            
            <?php if($company['whatsapp']): ?>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <i data-lucide="message-circle" class="stat-icon" style="width:18px;height:18px;"></i>
                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $company['whatsapp']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--text-primary); text-decoration: none;"><?= \App\Helpers\Security::escape($company['whatsapp']) ?></a>
            </div>
            <?php endif; ?>

            <?php if($company['email']): ?>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <i data-lucide="mail" class="stat-icon" style="width:18px;height:18px;"></i>
                <a href="mailto:<?= \App\Helpers\Security::escape($company['email']) ?>" style="color: var(--text-primary); text-decoration: none;"><?= \App\Helpers\Security::escape($company['email']) ?></a>
            </div>
            <?php endif; ?>

            <?php if($company['website']): ?>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <i data-lucide="globe" class="stat-icon" style="width:18px;height:18px;"></i>
                <a href="<?= \App\Helpers\Security::escape($company['website']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--color-primary); text-decoration: none;"><?= \App\Helpers\Security::escape($company['website']) ?></a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sosyal Medya -->
    <div class="glass-panel" style="padding: 1.5rem;">
        <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem; border-bottom: 1px solid var(--card-border); padding-bottom: 0.5rem;">Sosyal Medya</h3>
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <?php if($company['instagram']): ?>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <i data-lucide="instagram" class="stat-icon" style="width:18px;height:18px;"></i>
                <a href="<?= \App\Helpers\Security::escape($company['instagram']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--text-primary); text-decoration: none;">Instagram Profiline Git</a>
            </div>
            <?php endif; ?>

            <?php if($company['facebook']): ?>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <i data-lucide="facebook" class="stat-icon" style="width:18px;height:18px;"></i>
                <a href="<?= \App\Helpers\Security::escape($company['facebook']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--text-primary); text-decoration: none;">Facebook Profiline Git</a>
            </div>
            <?php endif; ?>

            <?php if($company['linkedin']): ?>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <i data-lucide="linkedin" class="stat-icon" style="width:18px;height:18px;"></i>
                <a href="<?= \App\Helpers\Security::escape($company['linkedin']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--text-primary); text-decoration: none;">LinkedIn Profiline Git</a>
            </div>
            <?php endif; ?>
            
            <?php if(empty($company['instagram']) && empty($company['facebook']) && empty($company['linkedin'])): ?>
                <p style="color: var(--text-secondary); font-size: 0.875rem;">Sosyal medya adresi eklenmemiş.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;" class="form-grid">
    <!-- Firma Bilgileri -->
    <div class="glass-panel" style="padding: 1.5rem;">
        <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem; border-bottom: 1px solid var(--card-border); padding-bottom: 0.5rem;">Ek Bilgiler</h3>
        <p style="margin-bottom: 0.5rem;"><strong style="color: var(--text-secondary);">Adres:</strong> <br><?= nl2br(\App\Helpers\Security::escape($company['address'])) ?: '-' ?></p>
        <p style="margin-bottom: 0.5rem;"><strong style="color: var(--text-secondary);">Kaynak:</strong> <?= \App\Helpers\Security::escape($company['source']) ?: '-' ?></p>
        <p style="margin-bottom: 0.5rem;"><strong style="color: var(--text-secondary);">Oluşturulma Tarihi:</strong> <?= date('d.m.Y H:i', strtotime($company['created_at'])) ?></p>
        <p style="margin-bottom: 0.5rem;"><strong style="color: var(--text-secondary);">İlk İletişim:</strong> <?= $company['first_contact_at'] ? date('d.m.Y H:i', strtotime($company['first_contact_at'])) : '-' ?></p>
        <p style="margin-bottom: 0.5rem;"><strong style="color: var(--text-secondary);">Son İletişim:</strong> <?= $company['last_contact_at'] ? date('d.m.Y H:i', strtotime($company['last_contact_at'])) : '-' ?></p>
    </div>

    <!-- Notlar -->
    <div class="glass-panel" style="padding: 1.5rem;">
        <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem; border-bottom: 1px solid var(--card-border); padding-bottom: 0.5rem;">Notlar</h3>
        <div style="white-space: pre-wrap; line-height: 1.5; color: var(--text-primary);">
            <?= nl2br(\App\Helpers\Security::escape($company['notes'])) ?: '<span style="color: var(--text-secondary);">Not bulunmuyor.</span>' ?>
        </div>
    </div>
</div>

<style>
@media (max-width: 1024px) {
    .form-grid {
        grid-template-columns: 1fr !important;
    }
}
</style>
