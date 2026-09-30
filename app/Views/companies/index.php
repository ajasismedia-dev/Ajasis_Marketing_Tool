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

<div class="glass-panel" style="padding: 1.5rem; margin-bottom: 2rem;">
    <form method="GET" action="<?= BASE_PATH ?>/companies" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 200px;">
            <label class="form-label">Arama (Ad, Tel, Web)</label>
            <input type="text" name="q" class="form-input" value="<?= \App\Helpers\Security::escape($filters['q']) ?>" placeholder="Arama yapın...">
        </div>
        <div style="width: 150px;">
            <label class="form-label">Durum</label>
            <select name="status" class="form-input">
                <option value="">Tümü</option>
                <?php foreach($statusLabels as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="width: 150px;">
            <label class="form-label">Sektör</label>
            <input type="text" name="sector" class="form-input" value="<?= \App\Helpers\Security::escape($filters['sector']) ?>">
        </div>
        <div style="width: 150px;">
            <label class="form-label">İlçe</label>
            <input type="text" name="district" class="form-input" value="<?= \App\Helpers\Security::escape($filters['district']) ?>">
        </div>
        <div>
            <button type="submit" class="btn btn-primary"><i data-lucide="filter"></i> Filtrele</button>
            <a href="<?= BASE_PATH ?>/companies" class="btn" style="background: rgba(255,255,255,0.1); color: #fff;">Temizle</a>
        </div>
        <div style="margin-left: auto;">
            <a href="<?= BASE_PATH ?>/companies/create" class="btn btn-primary"><i data-lucide="plus"></i> Yeni Firma</a>
        </div>
    </form>
</div>

<div class="glass-panel" style="overflow-x: auto;">
    <table style="width: 100%; text-align: left; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom: 1px solid var(--card-border); color: var(--text-secondary);">
                <th style="padding: 1rem;">Firma</th>
                <th style="padding: 1rem;">Sektör</th>
                <th style="padding: 1rem;">Telefon</th>
                <th style="padding: 1rem;">İlçe</th>
                <th style="padding: 1rem;">Kaynak</th>
                <th style="padding: 1rem;">Durum</th>
                <th style="padding: 1rem;">Son İletişim</th>
                <th style="padding: 1rem;">İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($companies)): ?>
            <tr>
                <td colspan="8" style="padding: 2rem; text-align: center; color: var(--text-secondary);">Kayıt bulunamadı.</td>
            </tr>
            <?php else: ?>
                <?php foreach($companies as $company): ?>
                <tr style="border-bottom: 1px solid var(--card-border);">
                    <td style="padding: 1rem; font-weight: 500;">
                        <a href="<?= BASE_PATH ?>/companies/show/<?= $company['id'] ?>" style="color: var(--color-primary); text-decoration: none;">
                            <?= \App\Helpers\Security::escape($company['name']) ?>
                        </a>
                    </td>
                    <td style="padding: 1rem; color: var(--text-secondary);"><?= \App\Helpers\Security::escape($company['sector']) ?></td>
                    <td style="padding: 1rem;"><?= \App\Helpers\Security::escape($company['phone']) ?></td>
                    <td style="padding: 1rem; color: var(--text-secondary);"><?= \App\Helpers\Security::escape($company['district']) ?></td>
                    <td style="padding: 1rem; color: var(--text-secondary);"><?= \App\Helpers\Security::escape($company['source']) ?: '-' ?></td>
                    <td style="padding: 1rem;">
                        <span class="status-badge status-<?= $company['status'] ?>">
                            <?= $statusLabels[$company['status']] ?>
                        </span>
                    </td>
                    <td style="padding: 1rem; color: var(--text-secondary);">
                        <?= $company['last_contact_at'] ? date('d.m.Y H:i', strtotime($company['last_contact_at'])) : '-' ?>
                    </td>
                    <td style="padding: 1rem;">
                        <a href="<?= BASE_PATH ?>/companies/show/<?= $company['id'] ?>" class="btn" style="padding: 0.5rem; background: rgba(255,255,255,0.1);"><i data-lucide="eye"></i></a>
                        <a href="<?= BASE_PATH ?>/companies/edit/<?= $company['id'] ?>" class="btn" style="padding: 0.5rem; background: rgba(255,255,255,0.1);"><i data-lucide="edit"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if ($totalPages > 1): ?>
    <div style="padding: 1rem; display: flex; gap: 0.5rem; justify-content: center; border-top: 1px solid var(--card-border);">
        <?php for($i=1; $i<=$totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&<?= http_build_query(array_filter($filters)) ?>" class="btn" style="padding: 0.5rem 1rem; <?= $page === $i ? 'background: var(--color-primary); color: #000;' : 'background: rgba(255,255,255,0.1); color: #fff;' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
