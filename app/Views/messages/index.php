<?php
/**
 * Message Center View
 * @var array $selectedCompany
 * @var string $selectedChannel
 * @var array $channelTemplates
 * @var array $currentTemplate
 * @var string $renderedSubject
 * @var string $renderedBody
 * @var array $targetInfo
 * @var array $allTemplates
 * @var array $recentOutbound
 * @var array $smtpStatus
 * @var string $businessName
 */

$channelLabels = [
    'whatsapp' => 'WhatsApp',
    'email'    => 'E-posta'
];

$channelIcons = [
    'whatsapp' => 'message-circle',
    'email'    => 'mail'
];
?>

<div style="display: flex; flex-direction: column; gap: 2rem; max-width: 1400px; margin: 0 auto;">

    <!-- Üst Başlık & Durum Göstergesi -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #fff; margin: 0; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="messages-square" style="color: var(--color-primary); width: 28px; height: 28px;"></i>
                Mesaj Merkezi
            </h2>
            <p style="color: var(--text-secondary); margin: 4px 0 0 0; font-size: 0.875rem;">
                Firma odaklı şablon hazırlama, WhatsApp açma ve tekil e-posta erişimi.
            </p>
        </div>

        <!-- SMTP Durum Rozeti -->
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <?php if ($smtpStatus['code'] === 'ready'): ?>
                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 999px; font-size: 0.8rem; font-weight: 600; background: rgba(193, 255, 0, 0.12); color: #c1ff00; border: 1px solid rgba(193, 255, 0, 0.3);">
                    <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
                    SMTP: Hazır
                </span>
            <?php elseif ($smtpStatus['code'] === 'unconfigured'): ?>
                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 999px; font-size: 0.8rem; font-weight: 600; background: rgba(245, 158, 11, 0.12); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3);" title="config.php içinde SMTP bilgilerini yapılandırın.">
                    <i data-lucide="alert-circle" style="width: 14px; height: 14px;"></i>
                    SMTP: Yapılandırılmadı
                </span>
            <?php else: ?>
                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 999px; font-size: 0.8rem; font-weight: 600; background: rgba(239, 68, 68, 0.12); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3);" title="PHPMailer kütüphanesi eksik. 'composer install' çalıştırın.">
                    <i data-lucide="x-circle" style="width: 14px; height: 14px;"></i>
                    SMTP: Bağımlılık Eksik
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- BÖLÜM 1: MESAJ OLUŞTURUCU (COMPOSER) -->
    <div class="glass-panel" style="padding: 1.5rem; position: relative;">
        <div style="border-bottom: 1px solid var(--card-border); padding-bottom: 1rem; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.15rem; font-weight: 600; color: #fff; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="pen-tool" style="width: 20px; height: 20px; color: var(--color-primary);"></i>
                1. Mesaj Oluştur
            </h3>
            <span style="font-size: 0.8rem; color: var(--text-secondary);">
                Değişkenler firma bilgileriyle otomatik doldurulur.
            </span>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;" class="composer-grid">
            
            <!-- Sol Sütun: Firma ve Şablon Seçimi -->
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                
                <!-- 1. Firma Arama / Seçim -->
                <div class="form-group" style="margin: 0; position: relative;">
                    <label class="form-label" style="display: flex; justify-content: space-between;">
                        <span>Hedef Firma *</span>
                        <?php if ($selectedCompany): ?>
                            <a href="<?= BASE_PATH ?>/companies/show/<?= $selectedCompany['id'] ?>" target="_blank" style="color: var(--color-primary); font-size: 0.75rem; text-decoration: none;">
                                Detayını Aç <i data-lucide="external-link" style="width: 12px; height: 12px; display: inline-block;"></i>
                            </a>
                        <?php endif; ?>
                    </label>

                    <div style="position: relative;">
                        <input type="text" id="companySearchInput" class="form-input" 
                               placeholder="Firma adı, telefon veya e-posta ile arayın..." 
                               value="<?= $selectedCompany ? \App\Helpers\Security::escape($selectedCompany['name']) : '' ?>" 
                               autocomplete="off">
                        <input type="hidden" id="selectedCompanyId" value="<?= $selectedCompany['id'] ?? '' ?>">
                        
                        <div id="companySearchResults" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #0f172a; border: 1px solid var(--card-border); border-radius: 8px; max-height: 240px; overflow-y: auto; z-index: 100; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);">
                            <!-- Dinamik arama sonuçları JS ile doldurulacak -->
                        </div>
                    </div>

                    <!-- Seçili Firma Rozeti -->
                    <div id="selectedCompanyBadge" style="display: <?= $selectedCompany ? 'flex' : 'none' ?>; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 0.5rem 0.75rem; border-radius: 6px; margin-top: 0.5rem; font-size: 0.8rem;">
                        <div style="display: flex; gap: 0.75rem; color: #cbd5e1;">
                            <span id="badgeSector" style="color: var(--text-secondary);"><?= \App\Helpers\Security::escape($selectedCompany['sector'] ?? 'Sektör belirtilmemiş') ?></span>
                            <span>•</span>
                            <span id="badgeCity"><?= \App\Helpers\Security::escape($selectedCompany['district'] ?? '') ?><?= !empty($selectedCompany['district']) ? ', ' : '' ?><?= \App\Helpers\Security::escape($selectedCompany['city'] ?? 'Konya') ?></span>
                        </div>
                        <button type="button" onclick="clearSelectedCompany()" style="background: none; border: none; color: #94a3b8; cursor: pointer; padding: 2px 4px; font-size: 0.75rem;" title="Firma seçimini temizle">
                            ✕ Temizle
                        </button>
                    </div>
                </div>

                <!-- 2. Kanal Seçimi (WhatsApp vs Email) -->
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Kanal *</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                        <label id="channelBtnWA" class="channel-card <?= $selectedChannel === 'whatsapp' ? 'active' : '' ?>" onclick="switchChannel('whatsapp')" style="cursor: pointer; padding: 0.75rem; border-radius: 8px; border: 1px solid <?= $selectedChannel === 'whatsapp' ? 'var(--color-primary)' : 'rgba(255,255,255,0.08)' ?>; background: <?= $selectedChannel === 'whatsapp' ? 'rgba(193, 255, 0, 0.08)' : 'rgba(255,255,255,0.02)' ?>; display: flex; align-items: center; gap: 10px; transition: all 0.2s;">
                            <input type="radio" name="channel_radio" value="whatsapp" <?= $selectedChannel === 'whatsapp' ? 'checked' : '' ?> style="display: none;">
                            <i data-lucide="message-circle" style="color: #22c55e; width: 20px; height: 20px;"></i>
                            <div>
                                <div style="font-weight: 600; font-size: 0.9rem; color: #fff;">WhatsApp</div>
                                <div style="font-size: 0.75rem; color: var(--text-secondary);">Doğrudan wa.me linki</div>
                            </div>
                        </label>

                        <label id="channelBtnEmail" class="channel-card <?= $selectedChannel === 'email' ? 'active' : '' ?>" onclick="switchChannel('email')" style="cursor: pointer; padding: 0.75rem; border-radius: 8px; border: 1px solid <?= $selectedChannel === 'email' ? 'var(--color-primary)' : 'rgba(255,255,255,0.08)' ?>; background: <?= $selectedChannel === 'email' ? 'rgba(193, 255, 0, 0.08)' : 'rgba(255,255,255,0.02)' ?>; display: flex; align-items: center; gap: 10px; transition: all 0.2s;">
                            <input type="radio" name="channel_radio" value="email" <?= $selectedChannel === 'email' ? 'checked' : '' ?> style="display: none;">
                            <i data-lucide="mail" style="color: #38bdf8; width: 20px; height: 20px;"></i>
                            <div>
                                <div style="font-weight: 600; font-size: 0.9rem; color: #fff;">E-posta</div>
                                <div style="font-size: 0.75rem; color: var(--text-secondary);">SMTP tekil gönderim</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- 3. Şablon Seçimi -->
                <div class="form-group" style="margin: 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <label class="form-label" style="margin: 0;">Şablon *</label>
                        <a href="#templates" style="font-size: 0.75rem; color: var(--color-primary); text-decoration: none;">
                            Şablonları Yönet →
                        </a>
                    </div>
                    <select id="templateSelect" class="form-input" onchange="onTemplateSelectChange()">
                        <?php foreach ($channelTemplates as $tpl): ?>
                            <option value="<?= $tpl['id'] ?>" <?= ($currentTemplate && $currentTemplate['id'] == $tpl['id']) ? 'selected' : '' ?>>
                                <?= \App\Helpers\Security::escape($tpl['name']) ?><?= $tpl['is_default'] ? ' (Varsayılan)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (empty($channelTemplates)): ?>
                            <option value="">-- Bu kanala ait aktif şablon yok --</option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- 4. Hedef İletişim Bilgisi & Uyarı Paneli -->
                <div id="targetDisplayBox" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 8px; padding: 0.85rem; font-size: 0.825rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: var(--text-secondary);">Hedef Adres:</span>
                        <strong id="targetValueText" style="color: #fff; font-family: monospace; font-size: 0.85rem;">
                            <?= !empty($targetInfo['target']) ? \App\Helpers\Security::escape($targetInfo['target']) : '-' ?>
                        </strong>
                    </div>
                    <div id="targetWarningBox" style="display: <?= !empty($targetInfo['warning']) ? 'flex' : 'none' ?>; align-items: center; gap: 6px; color: #f87171; font-size: 0.775rem; margin-top: 0.5rem;">
                        <i data-lucide="alert-triangle" style="width: 14px; height: 14px;"></i>
                        <span id="targetWarningText"><?= \App\Helpers\Security::escape($targetInfo['warning'] ?? '') ?></span>
                    </div>
                </div>

            </div>

            <!-- Sağ Sütun: Konu, Mesaj ve Aksiyon Butonları -->
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                
                <!-- Konu Alanı (Sadece E-posta için görünür) -->
                <div class="form-group" id="subjectGroup" style="margin: 0; display: <?= $selectedChannel === 'email' ? 'block' : 'none' ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                        <label class="form-label" style="margin: 0;">E-posta Konusu *</label>
                        <button type="button" class="btn btn-sm btn-outline" onclick="copySubject()" style="padding: 2px 8px; font-size: 0.7rem;">
                            <i data-lucide="copy" style="width: 12px; height: 12px;"></i> Konuyu Kopyala
                        </button>
                    </div>
                    <input type="text" id="composerSubject" class="form-input" placeholder="Konu başlığı..." 
                           value="<?= \App\Helpers\Security::escape($renderedSubject) ?>" maxlength="255">
                </div>

                <!-- Mesaj Gövdesi -->
                <div class="form-group" style="margin: 0; display: flex; flex-direction: column; flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                        <label class="form-label" style="margin: 0;">Mesaj İçeriği *</label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span id="charCount" style="font-size: 0.75rem; color: var(--text-secondary);">
                                <?= mb_strlen($renderedBody) ?> / 10.000
                            </span>
                            <button type="button" class="btn btn-sm btn-outline" onclick="copyMessageBody()" style="padding: 2px 8px; font-size: 0.7rem;">
                                <i data-lucide="copy" style="width: 12px; height: 12px;"></i> Mesajı Kopyala
                            </button>
                        </div>
                    </div>
                    <textarea id="composerBody" class="form-input" rows="9" 
                              style="font-family: inherit; font-size: 0.875rem; line-height: 1.5; resize: vertical;" 
                              oninput="onBodyInput()"><?= \App\Helpers\Security::escape($renderedBody) ?></textarea>
                </div>

                <!-- Aksiyon Butonları -->
                <div style="display: flex; gap: 0.75rem; justify-content: flex-end; align-items: center; padding-top: 0.5rem; border-top: 1px solid rgba(255,255,255,0.06);">
                    
                    <!-- WhatsApp Aksiyonları -->
                    <div id="waActions" style="display: <?= $selectedChannel === 'whatsapp' ? 'flex' : 'none' ?>; gap: 0.75rem; align-items: center;">
                        <button type="button" id="btnOpenWA" class="btn btn-outline" onclick="openWhatsAppTab()" style="border-color: #22c55e; color: #4ade80;">
                            <i data-lucide="external-link" style="width: 15px; height: 15px;"></i>
                            WhatsApp'ta Aç
                        </button>
                        <button type="button" id="btnLogWA" class="btn btn-primary" onclick="logWhatsAppCommunication()">
                            <i data-lucide="check" style="width: 15px; height: 15px;"></i>
                            İletişimi Kaydet
                        </button>
                    </div>

                    <!-- E-posta Aksiyonları -->
                    <div id="emailActions" style="display: <?= $selectedChannel === 'email' ? 'flex' : 'none' ?>; gap: 0.75rem; align-items: center;">
                        <?php if ($smtpStatus['code'] === 'ready'): ?>
                            <button type="button" id="btnSendEmail" class="btn btn-primary" onclick="sendSingleEmail()">
                                <i data-lucide="send" style="width: 15px; height: 15px;"></i>
                                E-postayı Gönder
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-outline" disabled style="opacity: 0.5; cursor: not-allowed;" title="SMTP yapılandırılmadı.">
                                <i data-lucide="mail-x" style="width: 15px; height: 15px;"></i>
                                SMTP Yapılandırılmadı
                            </button>
                        <?php endif; ?>
                    </div>

                </div>

            </div>

        </div>
    </div>

    <!-- BÖLÜM 2: ŞABLONLAR (TEMPLATES MANAGEMENT) -->
    <div id="templates" class="glass-panel" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--card-border); padding-bottom: 0.85rem;">
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 600; color: #fff; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="file-text" style="width: 20px; height: 20px; color: var(--color-primary);"></i>
                    2. Şablonlar
                </h3>
                <p style="color: var(--text-secondary); margin: 4px 0 0 0; font-size: 0.8rem;">
                    Kullanılabilir değişkenler: <code style="color: var(--color-primary); font-size: 0.75rem;">{{company_name}}</code>, <code style="color: var(--color-primary); font-size: 0.75rem;">{{sector}}</code>, <code style="color: var(--color-primary); font-size: 0.75rem;">{{district}}</code>, <code style="color: var(--color-primary); font-size: 0.75rem;">{{city}}</code>, <code style="color: var(--color-primary); font-size: 0.75rem;">{{website}}</code>, <code style="color: var(--color-primary); font-size: 0.75rem;">{{sender_name}}</code>, <code style="color: var(--color-primary); font-size: 0.75rem;">{{agency_name}}</code>
                </p>
            </div>
            <button type="button" class="btn btn-primary" onclick="openCreateTemplateModal()">
                <i data-lucide="plus" style="width: 15px; height: 15px;"></i> Yeni Şablon
            </button>
        </div>

        <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                <thead>
                    <tr style="text-align: left; border-bottom: 1px solid var(--card-border); color: var(--text-secondary);">
                        <th style="padding: 0.75rem;">Kanal</th>
                        <th style="padding: 0.75rem;">Şablon Adı</th>
                        <th style="padding: 0.75rem;">Konu / Açıklama</th>
                        <th style="padding: 0.75rem;">Durum</th>
                        <th style="padding: 0.75rem;">Varsayılan</th>
                        <th style="padding: 0.75rem; text-align: right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allTemplates)): ?>
                        <tr>
                            <td colspan="6" style="padding: 2rem; text-align: center; color: var(--text-secondary);">
                                Henüz tanımlı şablon bulunmuyor.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allTemplates as $tpl): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.04);">
                                <td style="padding: 0.75rem;">
                                    <?php if ($tpl['channel'] === 'whatsapp'): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; background: rgba(34, 197, 94, 0.15); color: #4ade80;">
                                            <i data-lucide="message-circle" style="width: 12px; height: 12px;"></i> WhatsApp
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; background: rgba(56, 189, 248, 0.15); color: #38bdf8;">
                                            <i data-lucide="mail" style="width: 12px; height: 12px;"></i> E-posta
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.75rem; font-weight: 600; color: #fff;">
                                    <?= \App\Helpers\Security::escape($tpl['name']) ?>
                                </td>
                                <td style="padding: 0.75rem; color: #94a3b8; max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= !empty($tpl['subject']) ? \App\Helpers\Security::escape($tpl['subject']) : \App\Helpers\Security::escape(mb_substr($tpl['body'], 0, 60)) . '...' ?>
                                </td>
                                <td style="padding: 0.75rem;">
                                    <?php if ($tpl['is_active']): ?>
                                        <span style="color: #4ade80; font-size: 0.75rem;">● Aktif</span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.75rem;">○ Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.75rem;">
                                    <?php if ($tpl['is_default']): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; background: rgba(193, 255, 0, 0.15); color: #c1ff00;">
                                            ★ Varsayılan
                                        </span>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline" onclick="setDefaultTemplate(<?= $tpl['id'] ?>)" style="padding: 2px 6px; font-size: 0.7rem;">
                                            Varsayılan Yap
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.75rem; text-align: right;">
                                    <div style="display: inline-flex; gap: 6px;">
                                        <button type="button" class="btn btn-sm btn-outline" 
                                                onclick='openEditTemplateModal(<?= json_encode($tpl, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'
                                                title="Düzenle">
                                            <i data-lucide="edit-3" style="width: 13px; height: 13px;"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline" 
                                                onclick="deleteTemplate(<?= $tpl['id'] ?>)"
                                                style="color: #f87171; border-color: rgba(239, 68, 68, 0.3);" title="Sil">
                                            <i data-lucide="trash-2" style="width: 13px; height: 13px;"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- BÖLÜM 3: SON GÖNDERİMLER (RECENT OUTBOUND) -->
    <div class="glass-panel" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--card-border); padding-bottom: 0.85rem;">
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 600; color: #fff; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="history" style="width: 20px; height: 20px; color: var(--color-primary);"></i>
                    3. Son Gönderimler (WhatsApp & E-posta)
                </h3>
                <p style="color: var(--text-secondary); margin: 4px 0 0 0; font-size: 0.8rem;">
                    CRM zaman tüneline kaydedilen son 25 giden iletişim kaydı.
                </p>
            </div>
            <a href="<?= BASE_PATH ?>/history" class="btn btn-sm btn-outline" style="font-size: 0.75rem;">
                Tüm Geçmişi İncele →
            </a>
        </div>

        <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.825rem;">
                <thead>
                    <tr style="text-align: left; border-bottom: 1px solid var(--card-border); color: var(--text-secondary);">
                        <th style="padding: 0.65rem;">Tarih / Saat</th>
                        <th style="padding: 0.65rem;">Firma</th>
                        <th style="padding: 0.65rem;">Kanal</th>
                        <th style="padding: 0.65rem;">Konu / Başlık</th>
                        <th style="padding: 0.65rem;">Mesaj Özeti</th>
                        <th style="padding: 0.65rem;">Sonuç</th>
                        <th style="padding: 0.65rem;">Kullanıcı</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentOutbound)): ?>
                        <tr>
                            <td colspan="7" style="padding: 2rem; text-align: center; color: var(--text-secondary);">
                                Henüz giden WhatsApp veya e-posta kaydı bulunmuyor.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentOutbound as $row): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.03);">
                                <td style="padding: 0.65rem; color: #94a3b8; font-family: monospace; font-size: 0.75rem; white-space: nowrap;">
                                    <?= date('d.m.Y H:i', strtotime($row['contacted_at'])) ?>
                                </td>
                                <td style="padding: 0.65rem;">
                                    <a href="<?= BASE_PATH ?>/companies/show/<?= $row['company_id'] ?>" style="color: #fff; font-weight: 500; text-decoration: none;">
                                        <?= \App\Helpers\Security::escape($row['company_name']) ?>
                                    </a>
                                </td>
                                <td style="padding: 0.65rem;">
                                    <?php if ($row['type'] === 'whatsapp'): ?>
                                        <span style="color: #4ade80; font-size: 0.75rem; font-weight: 600;">WhatsApp</span>
                                    <?php else: ?>
                                        <span style="color: #38bdf8; font-size: 0.75rem; font-weight: 600;">E-posta</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.65rem; color: #cbd5e1; max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= \App\Helpers\Security::escape($row['subject'] ?: '-') ?>
                                </td>
                                <td style="padding: 0.65rem; color: #94a3b8; max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= \App\Helpers\Security::escape($row['message'] ?: '-') ?>
                                </td>
                                <td style="padding: 0.65rem;">
                                    <span style="padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; background: rgba(255,255,255,0.05); color: #cbd5e1;">
                                        <?= \App\Helpers\Security::escape($row['outcome'] ?? 'sent') ?>
                                    </span>
                                </td>
                                <td style="padding: 0.65rem; color: #94a3b8; font-size: 0.75rem;">
                                    <?= \App\Helpers\Security::escape($row['user_name'] ?? 'Sistem') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ŞABLON EKLE / DÜZENLE MODAL -->
<div id="templateModal" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
    <div class="glass-panel" style="width: 100%; max-width: 640px; padding: 1.75rem; border: 1px solid var(--card-border); max-height: 90vh; overflow-y: auto;">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--card-border); padding-bottom: 0.75rem;">
            <h3 id="tplModalTitle" style="font-size: 1.15rem; font-weight: 600; color: #fff; margin: 0;">
                Yeni Şablon Oluştur
            </h3>
            <button type="button" onclick="closeTemplateModal()" style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.25rem;">
                ✕
            </button>
        </div>

        <form id="templateForm" method="POST" action="<?= BASE_PATH ?>/messages/templates/store">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
            <input type="hidden" id="tplId" name="id" value="">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Şablon Adı *</label>
                    <input type="text" id="tplName" name="name" class="form-input" placeholder="Örn: İlk Tanışma, Fiyat Takibi..." required maxlength="150">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Kanal *</label>
                    <select id="tplChannel" name="channel" class="form-input" onchange="toggleTplModalSubject()" required>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="email">E-posta</option>
                    </select>
                </div>
            </div>

            <div class="form-group" id="tplSubjectGroup" style="margin-bottom: 1rem; display: none;">
                <label class="form-label">E-posta Konusu</label>
                <input type="text" id="tplSubject" name="subject" class="form-input" placeholder="Örn: {{company_name}} için dijital strateji" maxlength="255">
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                    <label class="form-label" style="margin: 0;">Şablon Metni *</label>
                    <span style="font-size: 0.7rem; color: var(--color-primary);">Değişkenleri {{kod}} ile ekleyin</span>
                </div>
                <textarea id="tplBody" name="body" class="form-input" rows="7" placeholder="Merhaba,\n\n{{company_name}} için..." required maxlength="10000"></textarea>
            </div>

            <div style="display: flex; gap: 1.5rem; margin-bottom: 1.5rem; background: rgba(255,255,255,0.02); padding: 0.75rem; border-radius: 6px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85rem; color: #fff;">
                    <input type="checkbox" id="tplIsDefault" name="is_default" value="1">
                    <span>Bu kanal için varsayılan şablon yap</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85rem; color: #fff;">
                    <input type="checkbox" id="tplIsActive" name="is_active" value="1" checked>
                    <span>Aktif</span>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-outline" onclick="closeTemplateModal()">İptal</button>
                <button type="submit" class="btn btn-primary">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
// State
let currentChannel = '<?= $selectedChannel ?>';
let composerDirty = false;
let currentClientMessageId = generateUUID();

function generateUUID() {
    return 'msg_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
}

// 1. Company Search Autocomplete
let searchTimeout = null;
const searchInput = document.getElementById('companySearchInput');
const resultsBox = document.getElementById('companySearchResults');

searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const q = this.value.trim();
    if (q.length < 2) {
        resultsBox.style.display = 'none';
        return;
    }
    searchTimeout = setTimeout(() => {
        fetch('<?= BASE_PATH ?>/messages/company-search?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (data.success && data.companies.length > 0) {
                    renderSearchResults(data.companies);
                } else {
                    resultsBox.innerHTML = '<div style="padding: 0.75rem; color: #94a3b8; font-size: 0.8rem;">Sonuç bulunamadı.</div>';
                    resultsBox.style.display = 'block';
                }
            })
            .catch(() => {
                resultsBox.style.display = 'none';
            });
    }, 250);
});

document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !resultsBox.contains(e.target)) {
        resultsBox.style.display = 'none';
    }
});

function renderSearchResults(companies) {
    let html = '';
    companies.forEach(c => {
        const phone = c.whatsapp || c.phone || 'Tel yok';
        const email = c.email || 'E-posta yok';
        html += `
            <div onclick='selectCompany(${JSON.stringify(c)})' 
                 style="padding: 0.65rem 0.85rem; border-bottom: 1px solid rgba(255,255,255,0.05); cursor: pointer; transition: background 0.15s;"
                 onmouseenter="this.style.background='rgba(193,255,0,0.08)'"
                 onmouseleave="this.style.background='transparent'">
                <div style="font-weight: 600; color: #fff; font-size: 0.85rem;">${escapeHtml(c.name)}</div>
                <div style="display: flex; gap: 0.75rem; font-size: 0.75rem; color: #94a3b8; margin-top: 2px;">
                    <span>${escapeHtml(c.sector || '-')}</span>
                    <span>•</span>
                    <span>${escapeHtml(phone)}</span>
                    <span>•</span>
                    <span>${escapeHtml(email)}</span>
                </div>
            </div>
        `;
    });
    resultsBox.innerHTML = html;
    resultsBox.style.display = 'block';
}

function selectCompany(c) {
    document.getElementById('selectedCompanyId').value = c.id;
    document.getElementById('companySearchInput').value = c.name;
    resultsBox.style.display = 'none';

    // Show badge
    document.getElementById('badgeSector').innerText = c.sector || 'Sektör belirtilmemiş';
    document.getElementById('badgeCity').innerText = (c.district ? c.district + ', ' : '') + (c.city || 'Konya');
    document.getElementById('selectedCompanyBadge').style.display = 'flex';

    // Trigger render
    triggerServerRender();
}

function clearSelectedCompany() {
    document.getElementById('selectedCompanyId').value = '';
    document.getElementById('companySearchInput').value = '';
    document.getElementById('selectedCompanyBadge').style.display = 'none';
    document.getElementById('targetValueText').innerText = '-';
    document.getElementById('targetWarningBox').style.display = 'none';
    triggerServerRender();
}

// 2. Channel Switching
function switchChannel(channel) {
    currentChannel = channel;
    
    // Toggle Button Styles
    const btnWA = document.getElementById('channelBtnWA');
    const btnEmail = document.getElementById('channelBtnEmail');
    if (channel === 'whatsapp') {
        btnWA.style.borderColor = 'var(--color-primary)';
        btnWA.style.background = 'rgba(193, 255, 0, 0.08)';
        btnEmail.style.borderColor = 'rgba(255,255,255,0.08)';
        btnEmail.style.background = 'rgba(255,255,255,0.02)';
        document.getElementById('subjectGroup').style.display = 'none';
        document.getElementById('waActions').style.display = 'flex';
        document.getElementById('emailActions').style.display = 'none';
    } else {
        btnEmail.style.borderColor = 'var(--color-primary)';
        btnEmail.style.background = 'rgba(193, 255, 0, 0.08)';
        btnWA.style.borderColor = 'rgba(255,255,255,0.08)';
        btnWA.style.background = 'rgba(255,255,255,0.02)';
        document.getElementById('subjectGroup').style.display = 'block';
        document.getElementById('waActions').style.display = 'none';
        document.getElementById('emailActions').style.display = 'flex';
    }

    // Refresh template dropdown for channel
    fetchTemplatesForChannel(channel);
}

function fetchTemplatesForChannel(channel) {
    const select = document.getElementById('templateSelect');
    select.innerHTML = '';

    const allTpls = <?= json_encode($allTemplates, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
    const filtered = allTpls.filter(t => t.channel === channel && t.is_active == 1);

    if (filtered.length === 0) {
        select.innerHTML = '<option value="">-- Bu kanala ait aktif şablon yok --</option>';
        triggerServerRender();
        return;
    }

    let defaultId = null;
    filtered.forEach(t => {
        const opt = document.createElement('option');
        opt.value = t.id;
        opt.text = t.name + (t.is_default == 1 ? ' (Varsayılan)' : '');
        if (t.is_default == 1) {
            opt.selected = true;
            defaultId = t.id;
        }
        select.appendChild(opt);
    });

    if (!defaultId && filtered.length > 0) {
        select.options[0].selected = true;
    }

    triggerServerRender();
}

function onTemplateSelectChange() {
    if (composerDirty) {
        if (!confirm('Mesaj metninde değişiklik yaptınız. Şablonu değiştirmek yazdıklarınızın üzerine yazacaktır. Devam edilsin mi?')) {
            return;
        }
    }
    composerDirty = false;
    triggerServerRender();
}

function onBodyInput() {
    composerDirty = true;
    const body = document.getElementById('composerBody').value;
    document.getElementById('charCount').innerText = body.length + ' / 10.000';
}

// 3. Server-side Render
function triggerServerRender() {
    const companyId = document.getElementById('selectedCompanyId').value;
    const templateId = document.getElementById('templateSelect').value;

    const fd = new FormData();
    fd.append('csrf_token', '<?= \App\Helpers\Security::generateCsrfToken() ?>');
    fd.append('company_id', companyId);
    fd.append('template_id', templateId);
    fd.append('channel', currentChannel);

    fetch('<?= BASE_PATH ?>/messages/render', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            document.getElementById('composerSubject').value = res.subject || '';
            document.getElementById('composerBody').value = res.body || '';
            document.getElementById('charCount').innerText = (res.body ? res.body.length : 0) + ' / 10.000';
            
            // Target info
            document.getElementById('targetValueText').innerText = res.target || '-';
            const warningBox = document.getElementById('targetWarningBox');
            const warningText = document.getElementById('targetWarningText');
            if (res.target_warning) {
                warningText.innerText = res.target_warning;
                warningBox.style.display = 'flex';
            } else {
                warningBox.style.display = 'none';
            }

            composerDirty = false;
            // Generate a fresh client_message_id for this composition
            currentClientMessageId = generateUUID();
        }
    })
    .catch(err => {
        console.error('Render error:', err);
    });
}

// 4. WhatsApp Workflow
function openWhatsAppTab() {
    const target = document.getElementById('targetValueText').innerText.trim();
    if (!target || target === '-') {
        alert('Bu firma için WhatsApp kullanılabilecek geçerli bir numara bulunamadı.');
        return;
    }
    const body = document.getElementById('composerBody').value;
    const url = 'https://wa.me/' + target.replace(/[^0-9]/g, '') + '?text=' + encodeURIComponent(body);
    window.open(url, '_blank', 'noopener,noreferrer');
}

function logWhatsAppCommunication() {
    const companyId = document.getElementById('selectedCompanyId').value;
    if (!companyId) {
        alert('Lütfen önce bir firma seçin.');
        return;
    }
    const message = document.getElementById('composerBody').value.trim();
    if (!message) {
        alert('Mesaj içeriği boş olamaz.');
        return;
    }

    const btn = document.getElementById('btnLogWA');
    btn.disabled = true;
    btn.innerText = 'Kaydediliyor...';

    const fd = new FormData();
    fd.append('csrf_token', '<?= \App\Helpers\Security::generateCsrfToken() ?>');
    fd.append('company_id', companyId);
    fd.append('message', message);
    fd.append('subject', document.getElementById('templateSelect').selectedOptions[0]?.text || 'WhatsApp Mesajı');
    fd.append('client_message_id', currentClientMessageId);

    fetch('<?= BASE_PATH ?>/messages/log-whatsapp', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" style="width: 15px; height: 15px;"></i> İletişimi Kaydet';
        if (res.success) {
            alert('WhatsApp iletişimi başarıyla zaman tüneline kaydedildi.');
            window.location.reload();
        } else {
            alert('Hata: ' + (res.error || 'İletişim kaydedilemedi.'));
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" style="width: 15px; height: 15px;"></i> İletişimi Kaydet';
        alert('Sunucu iletişim hatası oluştu.');
    });
}

// 5. Single Email Workflow
function sendSingleEmail() {
    const companyId = document.getElementById('selectedCompanyId').value;
    if (!companyId) {
        alert('Lütfen önce bir firma seçin.');
        return;
    }
    const target = document.getElementById('targetValueText').innerText.trim();
    if (!target || target === '-' || target.indexOf('@') === -1) {
        alert('Bu firmaya ait geçerli bir e-posta adresi bulunmuyor.');
        return;
    }
    const subject = document.getElementById('composerSubject').value.trim();
    if (!subject) {
        alert('Lütfen bir e-posta konusu girin.');
        return;
    }
    const body = document.getElementById('composerBody').value.trim();
    if (!body) {
        alert('E-posta içeriği boş olamaz.');
        return;
    }

    if (!confirm(target + ' adresine e-posta gönderilsin mi?')) {
        return;
    }

    const btn = document.getElementById('btnSendEmail');
    if (btn) {
        btn.disabled = true;
        btn.innerText = 'Gönderiliyor...';
    }

    const fd = new FormData();
    fd.append('csrf_token', '<?= \App\Helpers\Security::generateCsrfToken() ?>');
    fd.append('company_id', companyId);
    fd.append('subject', subject);
    fd.append('body', body);
    fd.append('client_message_id', currentClientMessageId);

    fetch('<?= BASE_PATH ?>/messages/send-email', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="send" style="width: 15px; height: 15px;"></i> E-postayı Gönder';
        }
        if (res.success) {
            alert(res.message || 'E-posta başarıyla gönderildi ve zaman tüneline kaydedildi.');
            window.location.reload();
        } else {
            alert('Hata: ' + (res.error || 'E-posta gönderilemedi.'));
        }
    })
    .catch(() => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="send" style="width: 15px; height: 15px;"></i> E-postayı Gönder';
        }
        alert('E-posta gönderilirken sunucu hatası oluştu.');
    });
}

// 6. Copy Actions
function copySubject() {
    const s = document.getElementById('composerSubject').value;
    navigator.clipboard.writeText(s).then(() => {
        alert('Konu panoya kopyalandı.');
    }).catch(() => {
        document.getElementById('composerSubject').select();
        document.execCommand('copy');
        alert('Konu panoya kopyalandı.');
    });
}

function copyMessageBody() {
    const b = document.getElementById('composerBody').value;
    navigator.clipboard.writeText(b).then(() => {
        alert('Mesaj metni panoya kopyalandı.');
    }).catch(() => {
        document.getElementById('composerBody').select();
        document.execCommand('copy');
        alert('Mesaj metni panoya kopyalandı.');
    });
}

// 7. Template Modal Functions
function openCreateTemplateModal() {
    document.getElementById('tplModalTitle').innerText = 'Yeni Şablon Oluştur';
    document.getElementById('templateForm').action = '<?= BASE_PATH ?>/messages/templates/store';
    document.getElementById('tplId').value = '';
    document.getElementById('tplName').value = '';
    document.getElementById('tplChannel').value = currentChannel;
    document.getElementById('tplSubject').value = '';
    document.getElementById('tplBody').value = '';
    document.getElementById('tplIsDefault').checked = false;
    document.getElementById('tplIsActive').checked = true;

    toggleTplModalSubject();
    document.getElementById('templateModal').style.display = 'flex';
}

function openEditTemplateModal(tpl) {
    document.getElementById('tplModalTitle').innerText = 'Şablonu Düzenle';
    document.getElementById('templateForm').action = '<?= BASE_PATH ?>/messages/templates/update/' + tpl.id;
    document.getElementById('tplId').value = tpl.id;
    document.getElementById('tplName').value = tpl.name;
    document.getElementById('tplChannel').value = tpl.channel;
    document.getElementById('tplSubject').value = tpl.subject || '';
    document.getElementById('tplBody').value = tpl.body;
    document.getElementById('tplIsDefault').checked = tpl.is_default == 1;
    document.getElementById('tplIsActive').checked = tpl.is_active == 1;

    toggleTplModalSubject();
    document.getElementById('templateModal').style.display = 'flex';
}

function closeTemplateModal() {
    document.getElementById('templateModal').style.display = 'none';
}

function toggleTplModalSubject() {
    const ch = document.getElementById('tplChannel').value;
    document.getElementById('tplSubjectGroup').style.display = (ch === 'email') ? 'block' : 'none';
}

function setDefaultTemplate(id) {
    if (!confirm('Bu şablon varsayılan yapılsın mı?')) return;
    const fd = new FormData();
    fd.append('csrf_token', '<?= \App\Helpers\Security::generateCsrfToken() ?>');
    fetch('<?= BASE_PATH ?>/messages/templates/default/' + id, {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            window.location.reload();
        } else {
            alert('Hata: ' + (res.error || 'İşlem başarısız.'));
        }
    });
}

function deleteTemplate(id) {
    if (!confirm('Bu şablonu silmek istediğinize emin misiniz?')) return;
    const fd = new FormData();
    fd.append('csrf_token', '<?= \App\Helpers\Security::generateCsrfToken() ?>');
    fetch('<?= BASE_PATH ?>/messages/templates/delete/' + id, {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            window.location.reload();
        } else {
            alert('Hata: ' + (res.error || 'Şablon silinemedi.'));
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) lucide.createIcons();
});
</script>
