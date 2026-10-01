<?php
$channelIcons = [
    'whatsapp' => 'message-circle',
    'phone' => 'phone',
    'email' => 'mail',
    'instagram' => 'instagram',
    'linkedin' => 'linkedin',
    'meeting' => 'users',
    'note' => 'sticky-note',
    'other' => 'circle-dot'
];

$directionLabels = [
    'outbound' => 'Giden',
    'inbound' => 'Gelen',
    'internal' => 'Dahili / Not'
];

$outcomeLabels = [
    'sent' => 'Gönderildi',
    'no_answer' => 'Cevapsız',
    'replied' => 'Cevap Alındı',
    'interested' => 'İlgileniyor',
    'not_interested' => 'İlgilenmiyor',
    'callback' => 'Geri Arama',
    'proposal_requested' => 'Teklif İstendi',
    'proposal_sent' => 'Teklif Gönderildi',
    'meeting_scheduled' => 'Toplantı Planlandı',
    'customer' => 'Müşteri Oldu',
    'other' => 'Diğer'
];
?>

<!-- Üst İstatistik Kartları -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(193, 255, 0, 0.1); color: var(--color-primary);">
            <i data-lucide="message-square"></i>
        </div>
        <div class="stat-value"><?= number_format($stats['today']) ?></div>
        <div class="stat-label">Bugünkü İletişimler</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #60a5fa;">
            <i data-lucide="calendar"></i>
        </div>
        <div class="stat-value"><?= number_format($stats['week']) ?></div>
        <div class="stat-label">Bu Hafta</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: #4ade80;">
            <i data-lucide="corner-down-left"></i>
        </div>
        <div class="stat-value"><?= number_format($stats['replied']) ?></div>
        <div class="stat-label">Cevap Verenler</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #fbbf24;">
            <i data-lucide="file-text"></i>
        </div>
        <div class="stat-value"><?= number_format($stats['proposals']) ?></div>
        <div class="stat-label">Teklif Gönderilenler</div>
    </div>
</div>

<!-- Filtreler Barı -->
<form method="GET" action="<?= BASE_PATH ?>/history" class="filter-bar" style="margin-bottom: 1.5rem;">
    <div class="form-group" style="min-width: 180px;">
        <label class="form-label" style="font-size: 0.8rem;">Arama</label>
        <input type="text" name="q" class="form-input" placeholder="Firma, konu veya mesaj..." value="<?= \App\Helpers\Security::escape($filters['q']) ?>">
    </div>

    <div class="form-group" style="min-width: 160px;">
        <label class="form-label" style="font-size: 0.8rem;">Firma</label>
        <select name="company_id" class="form-input">
            <option value="">Tüm Firmalar</option>
            <?php foreach($companies as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filters['company_id'] == $c['id'] ? 'selected' : '' ?>><?= \App\Helpers\Security::escape($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group" style="min-width: 120px;">
        <label class="form-label" style="font-size: 0.8rem;">Kanal</label>
        <select name="type" class="form-input">
            <option value="">Tümü</option>
            <option value="phone" <?= $filters['type'] === 'phone' ? 'selected' : '' ?>>Telefon</option>
            <option value="whatsapp" <?= $filters['type'] === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
            <option value="email" <?= $filters['type'] === 'email' ? 'selected' : '' ?>>E-posta</option>
            <option value="meeting" <?= $filters['type'] === 'meeting' ? 'selected' : '' ?>>Toplantı</option>
            <option value="instagram" <?= $filters['type'] === 'instagram' ? 'selected' : '' ?>>Instagram</option>
            <option value="linkedin" <?= $filters['type'] === 'linkedin' ? 'selected' : '' ?>>LinkedIn</option>
            <option value="note" <?= $filters['type'] === 'note' ? 'selected' : '' ?>>İç Not</option>
            <option value="other" <?= $filters['type'] === 'other' ? 'selected' : '' ?>>Diğer</option>
        </select>
    </div>

    <div class="form-group" style="min-width: 110px;">
        <label class="form-label" style="font-size: 0.8rem;">Yön</label>
        <select name="direction" class="form-input">
            <option value="">Tümü</option>
            <option value="outbound" <?= $filters['direction'] === 'outbound' ? 'selected' : '' ?>>Giden</option>
            <option value="inbound" <?= $filters['direction'] === 'inbound' ? 'selected' : '' ?>>Gelen</option>
            <option value="internal" <?= $filters['direction'] === 'internal' ? 'selected' : '' ?>>Dahili / Not</option>
        </select>
    </div>

    <div class="form-group" style="min-width: 130px;">
        <label class="form-label" style="font-size: 0.8rem;">Sonuç</label>
        <select name="outcome" class="form-input">
            <option value="">Tümü</option>
            <option value="sent" <?= $filters['outcome'] === 'sent' ? 'selected' : '' ?>>Gönderildi</option>
            <option value="no_answer" <?= $filters['outcome'] === 'no_answer' ? 'selected' : '' ?>>Cevapsız</option>
            <option value="replied" <?= $filters['outcome'] === 'replied' ? 'selected' : '' ?>>Cevap Alındı</option>
            <option value="interested" <?= $filters['outcome'] === 'interested' ? 'selected' : '' ?>>İlgileniyor</option>
            <option value="proposal_requested" <?= $filters['outcome'] === 'proposal_requested' ? 'selected' : '' ?>>Teklif İstendi</option>
            <option value="proposal_sent" <?= $filters['outcome'] === 'proposal_sent' ? 'selected' : '' ?>>Teklif Gönderildi</option>
            <option value="meeting_scheduled" <?= $filters['outcome'] === 'meeting_scheduled' ? 'selected' : '' ?>>Toplantı Planlandı</option>
            <option value="callback" <?= $filters['outcome'] === 'callback' ? 'selected' : '' ?>>Tekrar Aranacak</option>
            <option value="customer" <?= $filters['outcome'] === 'customer' ? 'selected' : '' ?>>Müşteri Oldu</option>
            <option value="not_interested" <?= $filters['outcome'] === 'not_interested' ? 'selected' : '' ?>>İlgilenmiyor</option>
        </select>
    </div>

    <div class="form-group" style="min-width: 120px;">
        <label class="form-label" style="font-size: 0.8rem;">Başlangıç</label>
        <input type="date" name="date_start" class="form-input" value="<?= \App\Helpers\Security::escape($filters['date_start']) ?>">
    </div>

    <div class="form-group" style="min-width: 120px;">
        <label class="form-label" style="font-size: 0.8rem;">Bitiş</label>
        <input type="date" name="date_end" class="form-input" value="<?= \App\Helpers\Security::escape($filters['date_end']) ?>">
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem;">
            <i data-lucide="filter"></i> Filtrele
        </button>
        <a href="<?= BASE_PATH ?>/history" class="btn btn-ghost" style="padding: 0.5rem 1rem;">Temizle</a>
    </div>
</form>

<!-- Tablo -->
<div class="glass-panel" style="padding: 0; overflow: hidden;">
    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--card-border); display: flex; justify-content: space-between; align-items: center;">
        <span style="font-size: 0.9rem; font-weight: 600; color: #fff;">
            Toplam <span style="color: var(--color-primary);"><?= number_format($total) ?></span> İletişim Kaydı
        </span>
    </div>

    <?php if (empty($communications)): ?>
        <div class="empty-state" style="padding: 3rem 1rem;">
            <div class="empty-state-icon">
                <i data-lucide="search-x"></i>
            </div>
            <h3 class="empty-state-title">Kayıt bulunamadı</h3>
            <p class="empty-state-desc">Belirttiğiniz filtre kriterlerine uygun iletişim kaydı bulunmuyor.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse; min-width: 800px;">
                <thead>
                    <tr>
                        <th style="width: 130px;">Tarih / Saat</th>
                        <th style="width: 200px;">Firma</th>
                        <th style="width: 100px;">Kanal</th>
                        <th style="width: 90px;">Yön</th>
                        <th style="width: 130px;">Sonuç</th>
                        <th>Mesaj / Görüşme Detayı</th>
                        <th style="width: 80px; text-align: right;">Aksiyon</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($communications as $comm): ?>
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-secondary); white-space: nowrap;">
                                <?= date('d.m.Y H:i', strtotime($comm['contacted_at'])) ?>
                            </td>
                            <td>
                                <a href="<?= BASE_PATH ?>/companies/show/<?= $comm['company_id'] ?>" style="color: #fff; font-weight: 600; text-decoration: none;">
                                    <?= \App\Helpers\Security::escape($comm['company_name']) ?>
                                </a>
                                <?php if (!empty($comm['created_by_name'])): ?>
                                    <div style="font-size: 0.72rem; color: var(--text-secondary); margin-top: 2px;">
                                        Kullanıcı: <?= \App\Helpers\Security::escape($comm['created_by_name']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.825rem; font-weight: 500;">
                                    <i data-lucide="<?= $channelIcons[$comm['type']] ?? 'circle-dot' ?>" style="width: 15px; height: 15px; color: var(--color-primary);"></i>
                                    <?= ucfirst($comm['type']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="comm-badge dir-<?= $comm['direction'] ?>">
                                    <?= $directionLabels[$comm['direction']] ?? $comm['direction'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($comm['outcome'])): ?>
                                    <span class="outcome-badge outcome-<?= $comm['outcome'] ?>">
                                        <?= $outcomeLabels[$comm['outcome']] ?? $comm['outcome'] ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-secondary); font-size: 0.75rem;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.85rem; color: #cbd5e1; max-width: 320px;">
                                <?php if (!empty($comm['subject'])): ?>
                                    <strong style="color: #fff; display: block; margin-bottom: 2px;"><?= \App\Helpers\Security::escape($comm['subject']) ?></strong>
                                <?php endif; ?>
                                <div style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.4;">
                                    <?= \App\Helpers\Security::escape($comm['message']) ?>
                                </div>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="<?= BASE_PATH ?>/companies/show/<?= $comm['company_id'] ?>" class="btn btn-sm btn-ghost" title="Firma Detayına Git" style="padding: 4px 8px;">
                                    <i data-lucide="external-link" style="width: 14px; height: 14px;"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Sayfalama -->
        <?php if ($totalPages > 1): ?>
            <div style="display: flex; justify-content: center; gap: 0.5rem; padding: 1.25rem; border-top: 1px solid var(--card-border);">
                <?php
                $queryParams = $_GET;
                ?>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php $queryParams['page'] = $p; ?>
                    <a href="?<?= http_build_query($queryParams) ?>" class="btn btn-sm <?= $p == $page ? 'btn-primary' : 'btn-ghost' ?>" style="min-width: 36px; text-align: center;">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
