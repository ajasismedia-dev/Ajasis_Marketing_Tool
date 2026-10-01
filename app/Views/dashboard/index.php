<?php
$statusLabels = [
    'new' => 'Yeni',
    'contacted' => 'İletişimde',
    'replied' => 'Cevap Alındı',
    'proposal' => 'Teklif Aşaması',
    'customer' => 'Müşteri',
    'negative' => 'Olumsuz'
];

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
    'internal' => 'İç Not'
];
?>

<!-- Üst Özet İstatistikler -->
<div class="stats-grid" style="margin-bottom: 1.5rem;">
    <div class="stat-card">
        <div class="stat-header">
            <span>Toplam Firma</span>
            <i data-lucide="building-2" class="stat-icon"></i>
        </div>
        <div class="stat-value"><?= number_format($total_companies) ?></div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <span>Yeni Lead</span>
            <i data-lucide="user-plus" class="stat-icon" style="color: #60a5fa;"></i>
        </div>
        <div class="stat-value" style="color: #60a5fa;"><?= number_format($new_leads) ?></div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <span>İletişim & Teklif</span>
            <i data-lucide="message-square" class="stat-icon" style="color: var(--color-primary);"></i>
        </div>
        <div class="stat-value" style="color: var(--color-primary);"><?= number_format($contacted) ?></div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <span>Kazanılan Müşteri</span>
            <i data-lucide="check-circle" class="stat-icon" style="color: #4ade80;"></i>
        </div>
        <div class="stat-value" style="color: #4ade80;"><?= number_format($converted) ?></div>
    </div>
</div>

<!-- Satış Pipeline Özeti -->
<div style="margin-bottom: 1.75rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
        <h3 style="font-size: 0.95rem; font-weight: 600; color: #cbd5e1; text-transform: uppercase; letter-spacing: 0.5px; margin: 0;">
            Satış Hunisi (Pipeline)
        </h3>
        <span style="font-size: 0.8rem; color: var(--text-secondary);">Aktif CRM Durumu</span>
    </div>

    <div class="pipeline-summary">
        <div class="pipeline-card">
            <div class="pipeline-count" style="color: #94a3b8;"><?= $pipeline['new'] ?? 0 ?></div>
            <div class="pipeline-label">Yeni Lead</div>
        </div>
        <div class="pipeline-card">
            <div class="pipeline-count" style="color: #60a5fa;"><?= $pipeline['contacted'] ?? 0 ?></div>
            <div class="pipeline-label">İletişimde</div>
        </div>
        <div class="pipeline-card">
            <div class="pipeline-count" style="color: #38bdf8;"><?= $pipeline['replied'] ?? 0 ?></div>
            <div class="pipeline-label">Cevap Alındı</div>
        </div>
        <div class="pipeline-card">
            <div class="pipeline-count" style="color: #fbbf24;"><?= $pipeline['proposal'] ?? 0 ?></div>
            <div class="pipeline-label">Teklif Aşaması</div>
        </div>
        <div class="pipeline-card" style="border-color: rgba(34, 197, 94, 0.3);">
            <div class="pipeline-count" style="color: #4ade80;"><?= $pipeline['customer'] ?? 0 ?></div>
            <div class="pipeline-label">Müşteri</div>
        </div>
        <div class="pipeline-card">
            <div class="pipeline-count" style="color: #f87171;"><?= $pipeline['negative'] ?? 0 ?></div>
            <div class="pipeline-label">Olumsuz</div>
        </div>
    </div>
</div>

<!-- Satış Operasyonları 2 Kolon Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Sol Kolon: Takipler (Gecikmiş + Bugün) -->
    <div>
        <!-- Gecikmiş Takipler (Varsa) -->
        <?php if (!empty($overdue_followups)): ?>
            <div class="glass-panel" style="padding: 1.25rem; margin-bottom: 1.25rem; border: 1px solid rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.04);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; border-bottom: 1px solid rgba(239, 68, 68, 0.2); padding-bottom: 0.5rem;">
                    <h4 style="font-size: 0.95rem; font-weight: 600; color: #f87171; margin: 0; display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="alert-circle" style="width: 16px; height: 16px;"></i> Gecikmiş Takipler (<?= count($overdue_followups) ?>)
                    </h4>
                    <span style="font-size: 0.72rem; color: #f87171;">Müdahale Gerekli</span>
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                    <?php foreach ($overdue_followups as $fu): ?>
                        <div class="followup-card followup-overdue" style="margin-bottom: 0;">
                            <div class="followup-header">
                                <span class="followup-title"><?= \App\Helpers\Security::escape($fu['title']) ?></span>
                                <span class="priority-badge priority-<?= $fu['priority'] ?>"><?= ucfirst($fu['priority']) ?></span>
                            </div>
                            <div style="font-size: 0.8rem; color: #fff; margin-top: 2px;">
                                <a href="<?= BASE_PATH ?>/companies/show/<?= $fu['company_id'] ?>" style="color: #60a5fa; text-decoration: none; font-weight: 500;">
                                    <?= \App\Helpers\Security::escape($fu['company_name']) ?>
                                </a>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                                <span style="font-size: 0.72rem; color: #f87171; font-weight: 600;">
                                    <i data-lucide="clock" style="width: 12px; height: 12px; vertical-align: -2px;"></i> <?= date('d.m.Y H:i', strtotime($fu['due_at'])) ?>
                                </span>
                                <div style="display: flex; gap: 6px;">
                                    <a href="<?= BASE_PATH ?>/companies/show/<?= $fu['company_id'] ?>" class="btn btn-sm btn-ghost" style="padding: 2px 6px; font-size: 0.72rem;">Detay</a>
                                    <form action="<?= BASE_PATH ?>/followups/complete/<?= $fu['id'] ?>" method="POST" style="margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                                        <button type="submit" class="btn btn-sm btn-primary" style="padding: 2px 8px; font-size: 0.72rem;">Tamamla</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Bugünkü Takipler -->
        <div class="glass-panel" style="padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; border-bottom: 1px solid var(--card-border); padding-bottom: 0.5rem;">
                <h4 style="font-size: 0.95rem; font-weight: 600; color: #fff; margin: 0; display: flex; align-items: center; gap: 6px;">
                    <i data-lucide="calendar-check" style="width: 16px; height: 16px; color: var(--color-primary);"></i> Bugünkü Takipler (<?= count($today_followups) ?>)
                </h4>
                <span style="font-size: 0.75rem; color: var(--text-secondary);"><?= date('d.m.Y') ?></span>
            </div>

            <?php if (empty($today_followups)): ?>
                <div style="text-align: center; padding: 1.5rem 0.5rem; color: var(--text-secondary); font-size: 0.85rem;">
                    <i data-lucide="smile" style="width: 24px; height: 24px; color: #64748b; margin-bottom: 0.35rem; display: block; margin-left: auto; margin-right: auto;"></i>
                    Bugün için bekleyen takip bulunmuyor.
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                    <?php foreach ($today_followups as $fu): ?>
                        <div class="followup-card followup-today" style="margin-bottom: 0;">
                            <div class="followup-header">
                                <span class="followup-title"><?= \App\Helpers\Security::escape($fu['title']) ?></span>
                                <span class="priority-badge priority-<?= $fu['priority'] ?>"><?= ucfirst($fu['priority']) ?></span>
                            </div>
                            <div style="font-size: 0.8rem; color: #fff; margin-top: 2px;">
                                <a href="<?= BASE_PATH ?>/companies/show/<?= $fu['company_id'] ?>" style="color: #60a5fa; text-decoration: none; font-weight: 500;">
                                    <?= \App\Helpers\Security::escape($fu['company_name']) ?>
                                </a>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                                <span style="font-size: 0.72rem; color: var(--color-primary); font-weight: 600;">
                                    <i data-lucide="clock" style="width: 12px; height: 12px; vertical-align: -2px;"></i> <?= date('H:i', strtotime($fu['due_at'])) ?>
                                </span>
                                <div style="display: flex; gap: 6px;">
                                    <a href="<?= BASE_PATH ?>/companies/show/<?= $fu['company_id'] ?>" class="btn btn-sm btn-ghost" style="padding: 2px 6px; font-size: 0.72rem;">Detay</a>
                                    <form action="<?= BASE_PATH ?>/followups/complete/<?= $fu['id'] ?>" method="POST" style="margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                                        <button type="submit" class="btn btn-sm btn-primary" style="padding: 2px 8px; font-size: 0.72rem;">Tamamla</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sağ Kolon: Son Aktiviteler (Son 8 Communication) -->
    <div>
        <div class="glass-panel" style="padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; border-bottom: 1px solid var(--card-border); padding-bottom: 0.5rem;">
                <h4 style="font-size: 0.95rem; font-weight: 600; color: #fff; margin: 0; display: flex; align-items: center; gap: 6px;">
                    <i data-lucide="activity" style="width: 16px; height: 16px; color: var(--color-primary);"></i> Son Satış Aktiviteleri
                </h4>
                <a href="<?= BASE_PATH ?>/history" style="font-size: 0.75rem; color: var(--color-primary); text-decoration: none;">Tüm Geçmiş →</a>
            </div>

            <?php if (empty($recent_activities)): ?>
                <div style="text-align: center; padding: 2rem 0.5rem; color: var(--text-secondary); font-size: 0.85rem;">
                    Henüz aktivite kaydı yok.
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach ($recent_activities as $act): ?>
                        <div style="display: flex; gap: 0.75rem; align-items: flex-start; padding: 0.6rem 0.75rem; background: rgba(255,255,255,0.02); border-radius: 8px; border: 1px solid rgba(255,255,255,0.04);">
                            <div class="timeline-icon type-<?= $act['type'] ?>" style="position: static; width: 28px; height: 28px; flex-shrink: 0;">
                                <i data-lucide="<?= $channelIcons[$act['type']] ?? 'circle-dot' ?>" style="width: 14px; height: 14px;"></i>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                                    <a href="<?= BASE_PATH ?>/companies/show/<?= $act['company_id'] ?>" style="font-size: 0.825rem; font-weight: 600; color: #fff; text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 180px;">
                                        <?= \App\Helpers\Security::escape($act['company_name']) ?>
                                    </a>
                                    <span style="font-size: 0.7rem; color: var(--text-secondary); white-space: nowrap;">
                                        <?= date('d.m H:i', strtotime($act['contacted_at'])) ?>
                                    </span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px; font-size: 0.72rem; margin-bottom: 2px;">
                                    <span class="comm-badge dir-<?= $act['direction'] ?>" style="padding: 1px 4px; font-size: 0.68rem;"><?= $directionLabels[$act['direction']] ?? $act['direction'] ?></span>
                                    <?php if (!empty($act['outcome'])): ?>
                                        <span class="outcome-badge outcome-<?= $act['outcome'] ?>" style="padding: 1px 4px; font-size: 0.68rem;"><?= $act['outcome'] ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($act['subject'])): ?>
                                    <div style="font-size: 0.78rem; color: #cbd5e1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= \App\Helpers\Security::escape($act['subject']) ?>
                                    </div>
                                <?php elseif (!empty($act['message'])): ?>
                                    <div style="font-size: 0.75rem; color: #94a3b8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= \App\Helpers\Security::escape($act['message']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Son Eklenen Firmalar Tablosu -->
<div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h3 style="font-size: 1.1rem; font-weight: 600; color: #fff; margin: 0;">Son Eklenen Firmalar</h3>
        <a href="<?= BASE_PATH ?>/companies" style="font-size: 0.8rem; color: var(--color-primary); text-decoration: none;">Tüm Firmalar →</a>
    </div>

    <?php if(empty($latest_companies)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i data-lucide="building-2"></i>
            </div>
            <h3 class="empty-state-title">Henüz firma eklenmedi</h3>
            <p class="empty-state-desc">Firma Bul modülünden potansiyel müşterileri keşfetmeye başlayabilirsiniz.</p>
            <div class="empty-state-actions">
                <a href="<?= BASE_PATH ?>/leads" class="btn btn-primary" style="width:auto;">Firma Bul</a>
                <a href="<?= BASE_PATH ?>/companies/create" class="btn btn-secondary" style="width:auto;">Manuel Ekle</a>
            </div>
        </div>
    <?php else: ?>
        <div class="glass-panel" style="overflow-x: auto; padding: 0;">
            <table style="width: 100%; text-align: left; border-collapse: collapse; min-width: 600px;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--card-border); color: var(--text-secondary); font-size: 0.8rem;">
                        <th style="padding: 0.85rem 1rem;">Firma</th>
                        <th style="padding: 0.85rem 1rem;">Sektör</th>
                        <th style="padding: 0.85rem 1rem;">Durum</th>
                        <th style="padding: 0.85rem 1rem;">Tarih</th>
                        <th style="padding: 0.85rem 1rem; text-align: right;">Aksiyon</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($latest_companies as $company): ?>
                    <tr style="border-bottom: 1px solid var(--card-border);">
                        <td style="padding: 0.85rem 1rem; font-weight: 500;">
                            <a href="<?= BASE_PATH ?>/companies/show/<?= $company['id'] ?>" style="color: #fff; text-decoration: none; font-weight: 600;">
                                <?= \App\Helpers\Security::escape($company['name']) ?>
                            </a>
                        </td>
                        <td style="padding: 0.85rem 1rem; color: var(--text-secondary); font-size: 0.85rem;"><?= \App\Helpers\Security::escape($company['sector']) ?: '-' ?></td>
                        <td style="padding: 0.85rem 1rem;">
                            <span class="status-badge status-<?= $company['status'] ?>">
                                <?= $statusLabels[$company['status']] ?? $company['status'] ?>
                            </span>
                        </td>
                        <td style="padding: 0.85rem 1rem; color: var(--text-secondary); font-size: 0.85rem;">
                            <?= date('d.m.Y', strtotime($company['created_at'])) ?>
                        </td>
                        <td style="padding: 0.85rem 1rem; text-align: right;">
                            <a href="<?= BASE_PATH ?>/companies/show/<?= $company['id'] ?>" class="btn btn-sm btn-ghost" style="padding: 4px 8px;" title="Detay">
                                <i data-lucide="arrow-right" style="width: 14px; height: 14px;"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
