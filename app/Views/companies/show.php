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

$priorityLabels = [
    'low' => 'Düşük',
    'normal' => 'Normal',
    'high' => 'Yüksek'
];

$cleanPhone = preg_replace('/[^0-9]/', '', $company['phone'] ?? '');
$cleanWa = preg_replace('/[^0-9]/', '', $company['whatsapp'] ?? '');
$hasWa = !empty($cleanWa) || (!empty($cleanPhone) && (str_starts_with($cleanPhone, '05') || str_starts_with($cleanPhone, '5') || str_starts_with($cleanPhone, '905')));
$waTargetPhone = !empty($cleanWa) ? $cleanWa : $cleanPhone;
if (strlen($waTargetPhone) === 10) $waTargetPhone = '90' . $waTargetPhone;
elseif (strlen($waTargetPhone) === 11 && str_starts_with($waTargetPhone, '0')) $waTargetPhone = '9' . $waTargetPhone;
?>

<!-- Üst Başlık & Primary Aksiyonlar -->
<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; flex-wrap: wrap;">
            <h2 style="font-size: 1.65rem; font-weight: 700; margin: 0; color: #fff;"><?= \App\Helpers\Security::escape($company['name']) ?></h2>
            <span class="status-badge status-<?= $company['status'] ?>"><?= $statusLabels[$company['status']] ?? $company['status'] ?></span>
        </div>
        <div style="display: flex; gap: 1.25rem; align-items: center; color: var(--text-secondary); font-size: 0.875rem; flex-wrap: wrap;">
            <span><i data-lucide="briefcase" style="width: 15px; height: 15px; margin-right: 4px; vertical-align: -2px;"></i> <?= \App\Helpers\Security::escape($company['sector']) ?: 'Sektör Belirtilmemiş' ?></span>
            <span><i data-lucide="map-pin" style="width: 15px; height: 15px; margin-right: 4px; vertical-align: -2px;"></i> <?= \App\Helpers\Security::escape($company['district']) ? \App\Helpers\Security::escape($company['district']) . ' / ' : '' ?><?= \App\Helpers\Security::escape($company['city'] ?: 'Konya') ?></span>
            <?php if (!empty($company['source'])): ?>
                <span><i data-lucide="tag" style="width: 15px; height: 15px; margin-right: 4px; vertical-align: -2px;"></i> <?= \App\Helpers\Security::escape($company['source']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
        <button type="button" class="btn btn-primary" onclick="openCommModal()" style="display: inline-flex; align-items: center; gap: 6px;">
            <i data-lucide="plus-circle" style="width: 16px; height: 16px;"></i> İletişim Ekle
        </button>
        <button type="button" class="btn" onclick="openFollowUpModal()" style="background: rgba(193, 255, 0, 0.12); color: var(--color-primary); border: 1px solid rgba(193, 255, 0, 0.3); display: inline-flex; align-items: center; gap: 6px;">
            <i data-lucide="calendar-plus" style="width: 16px; height: 16px;"></i> Follow-up Ekle
        </button>
        <button type="button" class="btn" onclick="openStatusModal()" style="background: rgba(255,255,255,0.08); color: #fff; display: inline-flex; align-items: center; gap: 6px;">
            <i data-lucide="refresh-cw" style="width: 16px; height: 16px;"></i> Durumu Değiştir
        </button>
        <a href="<?= BASE_PATH ?>/companies/edit/<?= $company['id'] ?>" class="btn" style="background: rgba(255,255,255,0.06); color: var(--text-secondary); display: inline-flex; align-items: center; gap: 6px;">
            <i data-lucide="edit-3" style="width: 16px; height: 16px;"></i> Düzenle
        </a>
        <form action="<?= BASE_PATH ?>/companies/delete/<?= $company['id'] ?>" method="POST" style="display: inline;" onsubmit="return confirm('Bu firmayı ve tüm iletişim/takip geçmişini silmek istediğinize emin misiniz?');">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
            <button type="submit" class="btn" style="background: rgba(239, 68, 68, 0.12); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.25);" title="Firmayı Sil">
                <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
            </button>
        </form>
    </div>
</div>

<!-- 65% / 35% Responsive Grid -->
<div class="company-show-grid">
    <!-- Sol Kolon: Timeline (İletişim Geçmişi) -->
    <div>
        <div class="glass-panel" style="padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--card-border); padding-bottom: 0.75rem;">
                <h3 style="font-size: 1.15rem; font-weight: 600; color: #fff; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="history" style="width: 20px; height: 20px; color: var(--color-primary);"></i> İletişim Geçmişi
                </h3>
                <button type="button" class="btn btn-sm btn-ghost" onclick="openCommModal()" style="font-size: 0.8rem; padding: 0.25rem 0.6rem;">
                    <i data-lucide="plus" style="width: 14px; height: 14px;"></i> Yeni Kayıt
                </button>
            </div>

            <?php if (empty($communications)): ?>
                <div class="empty-state" style="padding: 2.5rem 1rem;">
                    <div class="empty-state-icon" style="width: 48px; height: 48px;">
                        <i data-lucide="message-square-off"></i>
                    </div>
                    <h4 class="empty-state-title" style="font-size: 1.05rem;">Henüz iletişim kaydı yok.</h4>
                    <p class="empty-state-desc" style="max-width: 400px; margin: 0 auto 1.25rem;">Bu firma ile yapılan aramaları, WhatsApp yazışmalarını, e-postaları veya iç notları kaydederek süreci takip edin.</p>
                    <button type="button" class="btn btn-primary" onclick="openCommModal()">
                        <i data-lucide="plus"></i> İlk İletişimi Ekle
                    </button>
                </div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($communications as $comm): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon type-<?= $comm['type'] ?>">
                                <i data-lucide="<?= $channelIcons[$comm['type']] ?? 'circle-dot' ?>" style="width: 16px; height: 16px;"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <div class="timeline-title">
                                        <span><?= ucfirst($comm['type']) ?></span>
                                        <span class="comm-badge dir-<?= $comm['direction'] ?>"><?= $directionLabels[$comm['direction']] ?? $comm['direction'] ?></span>
                                        <?php if (!empty($comm['outcome'])): ?>
                                            <span class="outcome-badge outcome-<?= $comm['outcome'] ?>"><?= $outcomeLabels[$comm['outcome']] ?? $comm['outcome'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timeline-meta">
                                        <?= date('d.m.Y H:i', strtotime($comm['contacted_at'])) ?>
                                        <?php if (!empty($comm['created_by_name'])): ?>
                                            • <span style="color: #cbd5e1;"><?= \App\Helpers\Security::escape($comm['created_by_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if (!empty($comm['subject'])): ?>
                                    <div style="font-weight: 600; font-size: 0.875rem; color: #fff; margin-bottom: 0.35rem;">
                                        <?= \App\Helpers\Security::escape($comm['subject']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($comm['message'])): ?>
                                    <div class="timeline-body">
                                        <?= nl2br(\App\Helpers\Security::escape($comm['message'])) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="timeline-footer">
                                    <span style="font-size: 0.72rem; color: #64748b;">Kayıt: <?= date('d.m.Y H:i', strtotime($comm['created_at'])) ?></span>
                                    <form action="<?= BASE_PATH ?>/history/delete/<?= $comm['id'] ?>" method="POST" style="margin: 0;" onsubmit="return confirm('Bu iletişim kaydını silmek istediğinize emin misiniz?');">
                                        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                                        <button type="submit" class="btn btn-ghost" style="padding: 2px 6px; font-size: 0.72rem; color: #94a3b8;" title="Kaydı Sil">
                                            <i data-lucide="trash-2" style="width: 12px; height: 12px;"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sağ Kolon: Takipler, Hızlı Aksiyonlar, Ek Bilgiler -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        <!-- Takipler (Follow-ups) Kartı -->
        <div class="glass-panel" style="padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--card-border); padding-bottom: 0.5rem;">
                <h3 style="font-size: 1.05rem; font-weight: 600; color: #fff; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="calendar-clock" style="width: 18px; height: 18px; color: var(--color-primary);"></i> Takipler
                </h3>
                <button type="button" class="btn btn-sm btn-ghost" onclick="openFollowUpModal()" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                    <i data-lucide="plus" style="width: 12px; height: 12px;"></i> Ekle
                </button>
            </div>

            <?php
            $pendingFollowUps = array_filter($followUps, fn($f) => $f['status'] === 'pending');
            $now = time();
            $todayDate = date('Y-m-d');
            ?>

            <?php if (empty($pendingFollowUps)): ?>
                <div style="text-align: center; padding: 1.5rem 0.5rem; color: var(--text-secondary); font-size: 0.85rem;">
                    <i data-lucide="check-circle" style="width: 28px; height: 28px; color: #64748b; margin-bottom: 0.5rem; display: block; margin-left: auto; margin-right: auto;"></i>
                    Bekleyen takip bulunmuyor.
                </div>
            <?php else: ?>
                <?php foreach ($pendingFollowUps as $fu): 
                    $dueTime = strtotime($fu['due_at']);
                    $dueDateStr = date('Y-m-d', $dueTime);
                    $isOverdue = ($dueTime < $now);
                    $isToday = ($dueDateStr === $todayDate);
                    
                    $cardClass = 'followup-upcoming';
                    if ($isOverdue) $cardClass = 'followup-overdue';
                    elseif ($isToday) $cardClass = 'followup-today';
                ?>
                    <div class="followup-card <?= $cardClass ?>">
                        <div class="followup-header">
                            <span class="followup-title"><?= \App\Helpers\Security::escape($fu['title']) ?></span>
                            <span class="priority-badge priority-<?= $fu['priority'] ?>"><?= $priorityLabels[$fu['priority']] ?? $fu['priority'] ?></span>
                        </div>
                        <?php if (!empty($fu['notes'])): ?>
                            <div style="font-size: 0.8rem; color: #94a3b8; margin-top: 0.25rem; line-height: 1.4;">
                                <?= nl2br(\App\Helpers\Security::escape($fu['notes'])) ?>
                            </div>
                        <?php endif; ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.6rem;">
                            <div class="followup-meta">
                                <i data-lucide="clock" style="width: 13px; height: 13px;"></i>
                                <span style="<?= $isOverdue ? 'color: #f87171; font-weight: 600;' : ($isToday ? 'color: var(--color-primary); font-weight: 600;' : '') ?>">
                                    <?= date('d.m.Y H:i', $dueTime) ?>
                                    <?= $isOverdue ? '(Gecikti)' : ($isToday ? '(Bugün)' : '') ?>
                                </span>
                            </div>
                            <div style="display: flex; gap: 4px;">
                                <form action="<?= BASE_PATH ?>/followups/complete/<?= $fu['id'] ?>" method="POST" style="margin: 0;">
                                    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                                    <button type="submit" class="btn btn-sm btn-primary" style="padding: 0.2rem 0.5rem; font-size: 0.72rem;" title="Tamamlandı Olarak İşaretle">
                                        <i data-lucide="check" style="width: 12px; height: 12px;"></i> Tamamla
                                    </button>
                                </form>
                                <form action="<?= BASE_PATH ?>/followups/cancel/<?= $fu['id'] ?>" method="POST" style="margin: 0;" onsubmit="return confirm('Bu takibi iptal etmek istediğinize emin misiniz?');">
                                    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                                    <button type="submit" class="btn btn-sm btn-ghost" style="padding: 0.2rem 0.4rem; font-size: 0.72rem; color: #94a3b8;" title="İptal Et">
                                        <i data-lucide="x" style="width: 12px; height: 12px;"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Hızlı İletişim & Aksiyonlar -->
        <div class="glass-panel" style="padding: 1.25rem;">
            <h3 style="font-size: 1.05rem; font-weight: 600; color: #fff; margin-bottom: 1rem; border-bottom: 1px solid var(--card-border); padding-bottom: 0.5rem; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="zap" style="width: 18px; height: 18px; color: var(--color-primary);"></i> Hızlı İletişim
            </h3>
            
            <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                <!-- Telefon -->
                <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.02); padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <i data-lucide="phone" style="width: 16px; height: 16px; color: #60a5fa;"></i>
                        <?php if(!empty($company['phone'])): ?>
                            <a href="tel:<?= \App\Helpers\Security::escape($company['phone']) ?>" style="color: #fff; text-decoration: none; font-size: 0.875rem;"><?= \App\Helpers\Security::escape($company['phone']) ?></a>
                        <?php else: ?>
                            <span style="color: var(--text-secondary); font-size: 0.85rem;">Telefon yok</span>
                        <?php endif; ?>
                    </div>
                    <?php if(!empty($company['phone'])): ?>
                        <button type="button" class="btn btn-sm btn-ghost" onclick="quickLogComm('phone')" style="font-size: 0.72rem; padding: 0.2rem 0.5rem;">
                            <i data-lucide="phone-call" style="width: 12px; height: 12px;"></i> Aramayı Kaydet
                        </button>
                    <?php endif; ?>
                </div>

                <!-- WhatsApp -->
                <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.02); padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <i data-lucide="message-circle" style="width: 16px; height: 16px; color: #4ade80;"></i>
                        <span style="font-size: 0.875rem; color: #fff;"><?= !empty($company['whatsapp']) ? \App\Helpers\Security::escape($company['whatsapp']) : (!empty($company['phone']) ? \App\Helpers\Security::escape($company['phone']) : 'Numara yok') ?></span>
                    </div>
                    <button type="button" class="btn btn-sm" onclick="openWhatsAppModal()" <?= !$hasWa ? 'disabled' : '' ?> style="background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); font-size: 0.72rem; padding: 0.2rem 0.6rem;">
                        <i data-lucide="send" style="width: 12px; height: 12px;"></i> WhatsApp
                    </button>
                </div>

                <!-- E-posta -->
                <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.02); padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                    <div style="display: flex; align-items: center; gap: 0.5rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <i data-lucide="mail" style="width: 16px; height: 16px; color: #fbbf24;"></i>
                        <?php if(!empty($company['email'])): ?>
                            <a href="mailto:<?= \App\Helpers\Security::escape($company['email']) ?>" style="color: #fff; text-decoration: none; font-size: 0.875rem; text-overflow: ellipsis; overflow: hidden;"><?= \App\Helpers\Security::escape($company['email']) ?></a>
                        <?php else: ?>
                            <span style="color: var(--text-secondary); font-size: 0.85rem;">E-posta yok</span>
                        <?php endif; ?>
                    </div>
                    <?php if(!empty($company['email'])): ?>
                        <button type="button" class="btn btn-sm btn-ghost" onclick="quickLogComm('email')" style="font-size: 0.72rem; padding: 0.2rem 0.5rem; flex-shrink: 0;">
                            <i data-lucide="mail" style="width: 12px; height: 12px;"></i> E-postayı Kaydet
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Web Sitesi & Sosyal -->
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.25rem;">
                    <?php if(!empty($company['website'])): ?>
                        <a href="<?= \App\Helpers\Security::escape($company['website']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-ghost" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                            <i data-lucide="globe" style="width: 13px; height: 13px;"></i> Website
                        </a>
                    <?php endif; ?>
                    <?php if(!empty($company['instagram'])): ?>
                        <a href="<?= \App\Helpers\Security::escape($company['instagram']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-ghost" style="font-size: 0.75rem; padding: 0.25rem 0.5rem; color: #ec4899;">
                            <i data-lucide="instagram" style="width: 13px; height: 13px;"></i> Instagram
                        </a>
                    <?php endif; ?>
                    <?php if(!empty($company['facebook'])): ?>
                        <a href="<?= \App\Helpers\Security::escape($company['facebook']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-ghost" style="font-size: 0.75rem; padding: 0.25rem 0.5rem; color: #3b82f6;">
                            <i data-lucide="facebook" style="width: 13px; height: 13px;"></i> Facebook
                        </a>
                    <?php endif; ?>
                    <?php if(!empty($company['linkedin'])): ?>
                        <a href="<?= \App\Helpers\Security::escape($company['linkedin']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-ghost" style="font-size: 0.75rem; padding: 0.25rem 0.5rem; color: #0ea5e9;">
                            <i data-lucide="linkedin" style="width: 13px; height: 13px;"></i> LinkedIn
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Ek Bilgiler & Notlar -->
        <div class="glass-panel" style="padding: 1.25rem;">
            <h3 style="font-size: 1.05rem; font-weight: 600; color: #fff; margin-bottom: 0.85rem; border-bottom: 1px solid var(--card-border); padding-bottom: 0.5rem; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="info" style="width: 18px; height: 18px; color: var(--color-primary);"></i> Ek Bilgiler
            </h3>
            
            <div style="font-size: 0.825rem; display: flex; flex-direction: column; gap: 0.6rem; color: #cbd5e1;">
                <div>
                    <span style="color: var(--text-secondary); display: block; font-size: 0.75rem;">Adres:</span>
                    <?= nl2br(\App\Helpers\Security::escape($company['address'])) ?: '-' ?>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">İlk İletişim:</span>
                    <span><?= $company['first_contact_at'] ? date('d.m.Y H:i', strtotime($company['first_contact_at'])) : '-' ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">Son İletişim:</span>
                    <span><?= $company['last_contact_at'] ? date('d.m.Y H:i', strtotime($company['last_contact_at'])) : '-' ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">Eklenme Tarihi:</span>
                    <span><?= date('d.m.Y H:i', strtotime($company['created_at'])) ?></span>
                </div>
                <?php if(!empty($company['notes'])): ?>
                    <div style="margin-top: 0.4rem; padding-top: 0.5rem; border-top: 1px solid rgba(255,255,255,0.05);">
                        <span style="color: var(--text-secondary); display: block; font-size: 0.75rem; margin-bottom: 2px;">Firma Notları:</span>
                        <div style="color: var(--text-primary); font-size: 0.8rem; line-height: 1.4; white-space: pre-wrap;"><?= nl2br(\App\Helpers\Security::escape($company['notes'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- ================= MODALS ================= -->

<!-- 1. İletişim Ekle Modalı -->
<div id="commModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="glass-panel" style="width: 100%; max-width: 540px; padding: 1.75rem; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="font-size: 1.2rem; font-weight: 600; color: #fff; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="plus-circle" style="color: var(--color-primary); width: 20px; height: 20px;"></i> İletişim Kaydı Ekle
            </h3>
            <button type="button" onclick="closeCommModal()" style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.2rem;">&times;</button>
        </div>

        <form action="<?= BASE_PATH ?>/history/store" method="POST" id="commForm">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
            <input type="hidden" name="company_id" value="<?= $company['id'] ?>">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Kanal *</label>
                    <select name="type" id="commTypeSelect" class="form-input" required>
                        <option value="phone">Telefon</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="email">E-posta</option>
                        <option value="meeting">Toplantı</option>
                        <option value="instagram">Instagram</option>
                        <option value="linkedin">LinkedIn</option>
                        <option value="note">İç Not</option>
                        <option value="other">Diğer</option>
                    </select>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Yön *</label>
                    <select name="direction" class="form-input" required>
                        <option value="outbound">Giden (Outbound)</option>
                        <option value="inbound">Gelen (Inbound)</option>
                        <option value="internal">Dahili / Not (Internal)</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Tarih</label>
                    <input type="date" name="contacted_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Saat</label>
                    <input type="time" name="contacted_time" class="form-input" value="<?= date('H:i') ?>" required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label">Görüşme Sonucu</label>
                <select name="outcome" class="form-input">
                    <option value="">-- Sonuç Seçin --</option>
                    <option value="sent">Gönderildi / İletildi</option>
                    <option value="no_answer">Cevapsız / Ulaşılamadı</option>
                    <option value="replied">Cevap Alındı</option>
                    <option value="interested">İlgileniyor</option>
                    <option value="proposal_requested">Teklif İstendi</option>
                    <option value="proposal_sent">Teklif Gönderildi</option>
                    <option value="meeting_scheduled">Toplantı Planlandı</option>
                    <option value="callback">Tekrar Aranacak</option>
                    <option value="customer">Müşteri Oldu (Kazanıldı)</option>
                    <option value="not_interested">İlgilenmiyor (Olumsuz)</option>
                    <option value="other">Diğer</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label">Konu / Başlık</label>
                <input type="text" name="subject" class="form-input" placeholder="Örn: İlk tanışma araması, teklif sunumu vb.">
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label">Mesaj / Görüşme Detayı</label>
                <textarea name="message" class="form-input" rows="4" placeholder="Konuşulan konular, müşteri talepleri veya iç notlar..."></textarea>
            </div>

            <!-- Follow-up Checkbox & Fields -->
            <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 0.875rem; margin-bottom: 1.25rem;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.875rem; font-weight: 500; color: #fff;">
                    <input type="checkbox" name="create_followup" id="commFollowupCheck" value="1" onchange="toggleCommFollowupBox()">
                    <span>Bu görüşme için Follow-up (Takip) oluştur</span>
                </label>

                <div id="commFollowupFields" style="display: none; margin-top: 0.875rem; padding-top: 0.75rem; border-top: 1px solid rgba(255,255,255,0.06);">
                    <div class="form-group" style="margin-bottom: 0.75rem;">
                        <label class="form-label" style="font-size: 0.8rem;">Takip Başlığı *</label>
                        <input type="text" name="follow_up_title" class="form-input" placeholder="Örn: Tekrar ara, teklifi takip et">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" style="font-size: 0.8rem;">Takip Tarihi</label>
                            <input type="date" name="follow_up_due_date" class="form-input" value="<?= date('Y-m-d', strtotime('+2 days')) ?>">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" style="font-size: 0.8rem;">Öncelik</label>
                            <select name="follow_up_priority" class="form-input">
                                <option value="normal">Normal</option>
                                <option value="high">Yüksek</option>
                                <option value="low">Düşük</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-ghost" onclick="closeCommModal()">İptal</button>
                <button type="submit" class="btn btn-primary">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Follow-up Ekle Modalı -->
<div id="followupModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="glass-panel" style="width: 100%; max-width: 480px; padding: 1.75rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="font-size: 1.2rem; font-weight: 600; color: #fff; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="calendar-plus" style="color: var(--color-primary); width: 20px; height: 20px;"></i> Follow-up (Takip) Ekle
            </h3>
            <button type="button" onclick="closeFollowUpModal()" style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.2rem;">&times;</button>
        </div>

        <form action="<?= BASE_PATH ?>/followups/store" method="POST">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
            <input type="hidden" name="company_id" value="<?= $company['id'] ?>">

            <div class="form-group" style="margin-bottom: 0.75rem;">
                <label class="form-label">Başlık *</label>
                <input type="text" name="title" id="fuTitleInput" class="form-input" placeholder="Örn: Tekrar ara" required>
            </div>

            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 1rem;">
                <button type="button" class="chip" onclick="setFuTitle('Tekrar ara')">Tekrar ara</button>
                <button type="button" class="chip" onclick="setFuTitle('WhatsApp\'tan dönüş yap')">WhatsApp'tan dönüş yap</button>
                <button type="button" class="chip" onclick="setFuTitle('Teklif gönder')">Teklif gönder</button>
                <button type="button" class="chip" onclick="setFuTitle('Teklifi takip et')">Teklifi takip et</button>
                <button type="button" class="chip" onclick="setFuTitle('Toplantı teyidi')">Toplantı teyidi</button>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Tarih *</label>
                    <input type="date" name="due_date" class="form-input" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Saat</label>
                    <input type="time" name="due_time" class="form-input" value="10:00" required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label">Öncelik</label>
                <select name="priority" class="form-input">
                    <option value="normal" selected>Normal</option>
                    <option value="high">Yüksek (Acil)</option>
                    <option value="low">Düşük</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label">Takip Notları</label>
                <textarea name="notes" class="form-input" rows="3" placeholder="Görüşme öncesi hatırlanacak notlar..."></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-ghost" onclick="closeFollowUpModal()">İptal</button>
                <button type="submit" class="btn btn-primary">Oluştur</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. Durumu Değiştir Modalı -->
<div id="statusModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="glass-panel" style="width: 100%; max-width: 440px; padding: 1.75rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="font-size: 1.2rem; font-weight: 600; color: #fff; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="refresh-cw" style="color: var(--color-primary); width: 20px; height: 20px;"></i> Durumu Değiştir
            </h3>
            <button type="button" onclick="closeStatusModal()" style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.2rem;">&times;</button>
        </div>

        <form action="<?= BASE_PATH ?>/companies/updateStatus/<?= $company['id'] ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">

            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label">Yeni Firma Durumu *</label>
                <select name="status" class="form-input" required>
                    <option value="new" <?= $company['status'] === 'new' ? 'selected' : '' ?>>Yeni (Lead)</option>
                    <option value="contacted" <?= $company['status'] === 'contacted' ? 'selected' : '' ?>>İletişimde (Contacted)</option>
                    <option value="replied" <?= $company['status'] === 'replied' ? 'selected' : '' ?>>Cevap Alındı (Replied)</option>
                    <option value="proposal" <?= $company['status'] === 'proposal' ? 'selected' : '' ?>>Teklif Aşaması (Proposal)</option>
                    <option value="customer" <?= $company['status'] === 'customer' ? 'selected' : '' ?>>Müşteri (Customer)</option>
                    <option value="negative" <?= $company['status'] === 'negative' ? 'selected' : '' ?>>Olumsuz (Negative)</option>
                </select>
                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.5rem;">
                    * Durum değişikliği zaman çizelgesine otomatik sistem notu olarak işlenir.
                </p>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-ghost" onclick="closeStatusModal()">İptal</button>
                <button type="submit" class="btn btn-primary">Güncelle</button>
            </div>
        </form>
    </div>
</div>

<!-- 4. WhatsApp Mesaj Modalı -->
<div id="waModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="glass-panel" style="width: 100%; max-width: 500px; padding: 1.75rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="font-size: 1.2rem; font-weight: 600; color: #fff; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="message-circle" style="color: #4ade80; width: 20px; height: 20px;"></i> WhatsApp İletişimi
            </h3>
            <button type="button" onclick="closeWhatsAppModal()" style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.2rem;">&times;</button>
        </div>

        <div class="form-group" style="margin-bottom: 1rem;">
            <label class="form-label">Numara</label>
            <input type="text" id="waPhoneDisplay" class="form-input" value="<?= $waTargetPhone ?>" readonly>
        </div>

        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label class="form-label">Mesaj Metni</label>
            <textarea id="waMessageText" class="form-input" rows="6"><?= "Merhaba, Ajasis Media'dan iletişime geçiyorum.\n\n" . \App\Helpers\Security::escape($company['name']) . " markanızı incelerken dijital varlığınızı ve müşteri dönüşümlerinizi geliştirebileceğimiz birkaç stratejik nokta tespit ettik.\n\nUygunsanız hazırladığımız analizleri kısaca paylaşmak isteriz." ?></textarea>
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; flex-wrap: wrap;">
            <button type="button" class="btn btn-ghost" onclick="closeWhatsAppModal()">Kapat</button>
            <button type="button" class="btn" onclick="openWhatsAppWeb()" style="background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.4);">
                <i data-lucide="external-link" style="width: 14px; height: 14px;"></i> WhatsApp'ı Aç
            </button>
            <button type="button" class="btn btn-primary" onclick="logWhatsAppCommunication()">
                <i data-lucide="save" style="width: 14px; height: 14px;"></i> İletişimi Kaydet
            </button>
        </div>
    </div>
</div>

<script>
function openCommModal(defaultType) {
    if (defaultType) {
        const select = document.getElementById('commTypeSelect');
        if (select) select.value = defaultType;
    }
    document.getElementById('commModal').style.display = 'flex';
    if (window.lucide) lucide.createIcons();
}
function closeCommModal() {
    document.getElementById('commModal').style.display = 'none';
}

function openFollowUpModal() {
    document.getElementById('followupModal').style.display = 'flex';
    if (window.lucide) lucide.createIcons();
}
function closeFollowUpModal() {
    document.getElementById('followupModal').style.display = 'none';
}
function setFuTitle(title) {
    const input = document.getElementById('fuTitleInput');
    if (input) input.value = title;
}

function openStatusModal() {
    document.getElementById('statusModal').style.display = 'flex';
    if (window.lucide) lucide.createIcons();
}
function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}

function openWhatsAppModal() {
    document.getElementById('waModal').style.display = 'flex';
    if (window.lucide) lucide.createIcons();
}
function closeWhatsAppModal() {
    document.getElementById('waModal').style.display = 'none';
}

function openWhatsAppWeb() {
    const phone = document.getElementById('waPhoneDisplay').value;
    const msg = document.getElementById('waMessageText').value;
    const url = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(msg);
    window.open(url, '_blank', 'noopener,noreferrer');
}

function logWhatsAppCommunication() {
    const msg = document.getElementById('waMessageText').value;
    const formData = new FormData();
    formData.append('csrf_token', '<?= \App\Helpers\Security::generateCsrfToken() ?>');
    formData.append('company_id', '<?= $company['id'] ?>');
    formData.append('type', 'whatsapp');
    formData.append('direction', 'outbound');
    formData.append('outcome', 'sent');
    formData.append('subject', 'WhatsApp Tanışma / Sunum Mesajı');
    formData.append('message', msg);
    formData.append('is_ajax', '1');

    fetch('<?= BASE_PATH ?>/history/store', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert('WhatsApp iletişimi başarıyla zaman çizelgesine kaydedildi.');
            window.location.reload();
        } else {
            alert('Hata: ' + (res.error || 'İletişim kaydedilemedi.'));
        }
    })
    .catch(err => {
        alert('İletişim kaydedilirken sunucu hatası oluştu.');
    });
}

function quickLogComm(type) {
    openCommModal(type);
}

function toggleCommFollowupBox() {
    const check = document.getElementById('commFollowupCheck');
    const box = document.getElementById('commFollowupFields');
    if (box) {
        box.style.display = check.checked ? 'block' : 'none';
    }
}
</script>
