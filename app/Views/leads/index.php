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

<div class="filter-bar">
    <form method="GET" action="<?= BASE_PATH ?>/leads" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; width: 100%;">
        <div class="form-group" style="flex: 2; min-width: 200px;">
            <label class="form-label">Arama Kelimesi / Sektör</label>
            <input type="text" name="q" class="form-input" required value="<?= \App\Helpers\Security::escape($filters['q']) ?>" placeholder="Örn: Mimarlık, Makina...">
        </div>
        <div class="form-group" style="flex: 1; min-width: 140px;">
            <label class="form-label">Şehir</label>
            <input type="text" name="city" class="form-input" value="<?= \App\Helpers\Security::escape($filters['city']) ?>">
        </div>
        <div class="form-group" style="flex: 1; min-width: 140px;">
            <label class="form-label">İlçe</label>
            <input type="text" name="district" class="form-input" value="<?= \App\Helpers\Security::escape($filters['district']) ?>" placeholder="Tümü">
        </div>
        <div class="form-group" style="flex: 1; min-width: 140px;">
            <label class="form-label">Max Sonuç</label>
            <select name="limit" class="form-input">
                <option value="10" <?= $filters['limit'] == 10 ? 'selected' : '' ?>>10</option>
                <option value="25" <?= $filters['limit'] == 25 ? 'selected' : '' ?>>25</option>
                <option value="50" <?= $filters['limit'] == 50 ? 'selected' : '' ?>>50</option>
            </select>
        </div>
        <div class="form-group" style="flex: 1; min-width: 160px;">
            <label class="form-label">Kaynak</label>
            <select name="source" class="form-input">
                <option value="all" <?= $filters['source'] === 'all' ? 'selected' : '' ?>>Tümü</option>
                <option value="kto" <?= $filters['source'] === 'kto' ? 'selected' : '' ?>>Konya Ticaret Odası</option>
                <option value="kso" <?= $filters['source'] === 'kso' ? 'selected' : '' ?>>Konya Sanayi Odası</option>
                <option value="osm" <?= $filters['source'] === 'osm' ? 'selected' : '' ?>>OpenStreetMap</option>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary" style="width: auto;"><i data-lucide="search"></i> Ara</button>
        </div>
    </form>
</div>

<?php if (!empty($filters['q'])): ?>
<?php if (!empty($statuses)): ?>
<div class="glass-panel" style="padding: 1rem; margin-bottom: 1rem; display: flex; gap: 1rem; flex-wrap: wrap;">
    <div style="font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; margin-right: 1rem;">Kaynak Durumu:</div>
    <?php foreach ($statuses as $source => $stat): ?>
        <?php 
            $color = $stat['status'] === 'success' ? '#34d399' : ($stat['status'] === 'timeout' ? '#fbbf24' : '#f87171'); 
            $bg = $stat['status'] === 'success' ? 'rgba(16, 185, 129, 0.15)' : ($stat['status'] === 'timeout' ? 'rgba(251, 191, 36, 0.15)' : 'rgba(248, 113, 113, 0.15)');
            
            $statusTexts = [
                'success' => 'Çalışıyor',
                'empty' => 'Sonuç Yok',
                'filtered_zero' => 'Eşleşme Yok',
                'timeout' => 'Zaman Aşımı',
                'unavailable' => 'Kullanılamıyor',
                'error' => 'Hata'
            ];
            $displayStatus = $statusTexts[$stat['status']] ?? $stat['status'];
        ?>
        <div style="background: <?= $bg ?>; color: <?= $color ?>; padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
            <strong><?= $source ?></strong>: 
            <?= $stat['status'] === 'success' ? $stat['count'] . ' kayıt (' . $stat['duration'] . ')' : $displayStatus ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="glass-panel" style="overflow-x: auto;">
    <table style="width: 100%; text-align: left; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom: 1px solid var(--card-border); color: var(--text-secondary);">
                <th style="padding: 1rem;">Firma</th>
                <th style="padding: 1rem;">Sektör</th>
                <th style="padding: 1rem;">Telefon</th>
                <th style="padding: 1rem;">Web / Sosyal</th>
                <th style="padding: 1rem;">İlçe</th>
                <th style="padding: 1rem;">Kaynak</th>
                <th style="padding: 1rem;">Durum</th>
                <th style="padding: 1rem;">Aksiyonlar</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($leads)): ?>
            <tr>
                <td colspan="8" style="padding: 2.5rem 1rem; text-align: center; color: var(--text-secondary);">
                    <i data-lucide="search-x" style="width: 36px; height: 36px; margin: 0 auto 0.75rem; opacity: 0.5; display: block;"></i>
                    Aradığınız kriterlere uygun firma kaydı bulunamadı. Lütfen arama terimini değiştirin.
                </td>
            </tr>
            <?php else: ?>
                <?php foreach($leads as $i => $lead): ?>
                <tr style="border-bottom: 1px solid var(--card-border);" id="lead-row-<?= $i ?>">
                    <td style="padding: 1rem; font-weight: 500;">
                        <span id="lead-name-<?= $i ?>"><?= \App\Helpers\Security::escape($lead['name']) ?></span>
                    </td>
                    <td style="padding: 1rem; color: var(--text-secondary);">
                        <span id="lead-sector-<?= $i ?>"><?= \App\Helpers\Security::escape($lead['sector']) ?></span>
                    </td>
                    <td style="padding: 1rem;">
                        <span id="lead-phone-<?= $i ?>"><?= \App\Helpers\Security::escape($lead['phone']) ?></span>
                    </td>
                    <td style="padding: 1rem; font-size: 0.875rem;">
                        <div id="lead-web-<?= $i ?>">
                            <?php if($lead['website']): ?>
                                <a href="<?= \App\Helpers\Security::escape($lead['website']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--color-primary); display: block;">Website</a>
                            <?php endif; ?>
                            <?php if($lead['instagram']): ?>
                                <a href="<?= \App\Helpers\Security::escape($lead['instagram']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--text-secondary); display: block;">Instagram</a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="padding: 1rem; color: var(--text-secondary);"><?= \App\Helpers\Security::escape($lead['district']) ?></td>
                    <td style="padding: 1rem; color: var(--text-secondary); font-size: 0.875rem;">
                        <?php if($lead['source_url']): ?>
                            <a href="<?= \App\Helpers\Security::escape($lead['source_url']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--text-secondary);"><?= \App\Helpers\Security::escape($lead['source']) ?></a>
                        <?php else: ?>
                            <?= \App\Helpers\Security::escape($lead['source']) ?>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 1rem;">
                        <?php if ($lead['db_id']): ?>
                            <span class="status-badge status-<?= $lead['db_status'] ?>" style="display: block; text-align: center; margin-bottom: 0.25rem;">
                                <?= $statusLabels[$lead['db_status']] ?>
                            </span>
                            <span style="font-size: 0.75rem; color: var(--text-secondary); display: block; text-align: center;">Kayıtlı</span>
                        <?php else: ?>
                            <span class="status-badge status-new">YENİ</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <?php if ($lead['db_id']): ?>
                            <a href="<?= BASE_PATH ?>/companies/show/<?= $lead['db_id'] ?>" class="btn" style="padding: 0.5rem; background: rgba(255,255,255,0.1);"><i data-lucide="eye"></i> Detay</a>
                        <?php else: ?>
                            <form action="<?= BASE_PATH ?>/leads/save" method="POST" style="margin: 0;">
                                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                                <input type="hidden" name="name" value="<?= \App\Helpers\Security::escape($lead['name']) ?>">
                                <input type="hidden" name="sector" value="<?= \App\Helpers\Security::escape($lead['sector']) ?>">
                                <input type="hidden" name="phone" id="form-phone-<?= $i ?>" value="<?= \App\Helpers\Security::escape($lead['phone'] ?? '') ?>">
                                <input type="hidden" name="email" id="form-email-<?= $i ?>" value="<?= \App\Helpers\Security::escape($lead['email'] ?? '') ?>">
                                <input type="hidden" name="whatsapp" id="form-whatsapp-<?= $i ?>" value="<?= \App\Helpers\Security::escape($lead['whatsapp'] ?? '') ?>">
                                <input type="hidden" name="website" id="form-website-<?= $i ?>" value="<?= \App\Helpers\Security::escape($lead['website'] ?? '') ?>">
                                <input type="hidden" name="instagram" id="form-instagram-<?= $i ?>" value="<?= \App\Helpers\Security::escape($lead['instagram'] ?? '') ?>">
                                <input type="hidden" name="facebook" id="form-facebook-<?= $i ?>" value="<?= \App\Helpers\Security::escape($lead['facebook'] ?? '') ?>">
                                <input type="hidden" name="linkedin" id="form-linkedin-<?= $i ?>" value="<?= \App\Helpers\Security::escape($lead['linkedin'] ?? '') ?>">
                                <input type="hidden" name="district" value="<?= \App\Helpers\Security::escape($lead['district'] ?? '') ?>">
                                <input type="hidden" name="city" value="<?= \App\Helpers\Security::escape($lead['city'] ?? '') ?>">
                                <input type="hidden" name="address" id="form-address-<?= $i ?>" value="<?= \App\Helpers\Security::escape($lead['address'] ?? '') ?>">
                                <input type="hidden" name="source" id="form-source-<?= $i ?>" value="<?= \App\Helpers\Security::escape($lead['source'] ?? '') ?>">
                                <input type="hidden" name="google_place_id" id="form-google-place-id-<?= $i ?>" value="">
                                <input type="hidden" name="enrichment_status" id="form-enrichment-status-<?= $i ?>" value="">
                                <button type="submit" class="btn btn-primary" style="padding: 0.5rem;"><i data-lucide="plus"></i> Kaydet</button>
                            </form>
                        <?php endif; ?>
                        
                        <button type="button" class="btn google-enrich-btn" data-index="<?= $i ?>" data-name="<?= \App\Helpers\Security::escape($lead['name']) ?>" data-city="<?= \App\Helpers\Security::escape($lead['city'] ?? 'Konya') ?>" data-district="<?= \App\Helpers\Security::escape($lead['district'] ?? '') ?>" style="padding: 0.5rem; background: rgba(59, 130, 246, 0.15); color: #60a5fa;" title="Google Places ve Web Sitesinden Otomatik Zenginleştir">
                            <i data-lucide="sparkles"></i> Google ile Zenginleştir
                        </button>

                        <?php if ($lead['website']): ?>
                            <button type="button" class="btn enrich-btn" data-index="<?= $i ?>" data-url="<?= \App\Helpers\Security::escape($lead['website']) ?>" style="padding: 0.5rem; background: rgba(168, 85, 247, 0.15); color: #c084fc;">
                                <i data-lucide="globe"></i> Web Tara
                            </button>
                        <?php elseif (strpos($lead['source_url'] ?? '', 'kso.org.tr') !== false): ?>
                            <button type="button" class="btn enrich-btn" data-index="<?= $i ?>" data-url="<?= \App\Helpers\Security::escape($lead['source_url']) ?>" style="padding: 0.5rem; background: rgba(168, 85, 247, 0.15); color: #c084fc;">
                                <i data-lucide="search"></i> KSO Detay
                            </button>
                        <?php endif; ?>
                        
                        <?php 
                            $hasWA = !empty($lead['whatsapp']);
                            $isMobile = !empty($lead['phone']) && strlen($lead['phone']) >= 10 && (strpos($lead['phone'], '05') === 0 || strpos($lead['phone'], '905') === 0 || strpos($lead['phone'], '+905') === 0 || strpos($lead['phone'], '5') === 0);
                            $waDisabled = (!$hasWA && !$isMobile);
                        ?>
                        <button type="button" class="btn wa-btn" data-phone="<?= \App\Helpers\Security::escape($lead['phone']) ?>" data-whatsapp="<?= \App\Helpers\Security::escape($lead['whatsapp'] ?? '') ?>" data-name="<?= \App\Helpers\Security::escape($lead['name']) ?>" data-dbid="<?= $lead['db_id'] ?: '' ?>" <?= $waDisabled ? 'disabled' : '' ?> style="padding: 0.5rem; background: rgba(16, 185, 129, 0.15); color: #34d399;">
                            <i data-lucide="message-circle"></i> WhatsApp
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal for WhatsApp Message -->
<div id="waModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="glass-panel" style="width: 100%; max-width: 500px; padding: 2rem;">
        <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">WhatsApp Mesajı Gönder</h3>
        <p style="margin-bottom: 1rem; color: var(--text-secondary); font-size: 0.875rem;">Mesajınızı düzenleyebilir ve WhatsApp Web/Uygulaması üzerinden gönderebilirsiniz.</p>
        
        <div class="form-group">
            <label class="form-label">Numara</label>
            <input type="text" id="waPhone" class="form-input" readonly>
        </div>
        
        <div class="form-group">
            <label class="form-label">Mesaj</label>
            <textarea id="waMessage" class="form-input" rows="6"></textarea>
        </div>
        
        <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
            <button type="button" id="waModalClose" class="btn" style="background: rgba(255,255,255,0.1); color: #fff;">İptal</button>
            <button type="button" id="waModalSend" class="btn btn-primary">WhatsApp'ta Aç</button>
        </div>
        
        <div id="waMarkContactedBox" style="display: none; margin-top: 1rem; border-top: 1px solid var(--card-border); padding-top: 1rem;">
            <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.5rem;">İletişim kurduktan sonra firmayı güncelleyin:</p>
            <form id="waMarkContactedForm" method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i data-lucide="check-circle"></i> İletişime Geçildi Olarak İşaretle
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Modal for Google Match Confirmation -->
<div id="googleMatchModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="glass-panel" style="width: 100%; max-width: 520px; padding: 2rem;">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
            <div style="background: rgba(59, 130, 246, 0.2); width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                <i data-lucide="sparkles" style="color: #60a5fa; width: 22px; height: 22px;"></i>
            </div>
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 600; margin: 0;">Muhtemel Google Eşleşmesi</h3>
                <span style="font-size: 0.8rem; color: var(--text-secondary);">Detaylar çekilmeden önce doğrulanması önerilir</span>
            </div>
        </div>

        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--card-border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem;">
            <div style="margin-bottom: 0.75rem;">
                <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Google Adayı</div>
                <div id="gmCandidateName" style="font-size: 1rem; font-weight: 600; color: #fff; margin-top: 3px;"></div>
            </div>
            <div style="margin-bottom: 0.75rem;">
                <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Adres</div>
                <div id="gmCandidateAddress" style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 3px;"></div>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 0.75rem; margin-top: 0.75rem;">
                <div>
                    <span style="font-size: 0.8rem; color: var(--text-secondary);">Güven Skoru: </span>
                    <span id="gmCandidateScore" class="badge" style="background: rgba(59, 130, 246, 0.2); color: #60a5fa; font-weight: 600;"></span>
                </div>
                <span class="google-maps-attribution" translate="no" style="font-size: 0.75rem; color: #94a3b8; white-space: nowrap;">Google Maps</span>
            </div>
        </div>

        <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
            <button type="button" id="gmModalCancel" class="btn" style="background: rgba(255,255,255,0.1); color: #fff;">İptal</button>
            <button type="button" id="gmModalConfirm" class="btn btn-primary" style="background: #3b82f6; border-color: #3b82f6;">Eşleşmeyi Onayla</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.google-enrich-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const button = e.currentTarget;
            const index = button.getAttribute('data-index');
            const name = button.getAttribute('data-name');
            const city = button.getAttribute('data-city') || 'Konya';
            const district = button.getAttribute('data-district') || '';
            enrichGoogleLead(button, index, name, city, district, false);
        });
    });

    document.querySelectorAll('.enrich-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const button = e.currentTarget;
            const index = button.getAttribute('data-index');
            const url = button.getAttribute('data-url');
            enrichLead(button, index, url);
        });
    });

    document.querySelectorAll('.wa-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const button = e.currentTarget;
            if (button.hasAttribute('disabled')) return;
            const wa = button.getAttribute('data-whatsapp');
            const ph = button.getAttribute('data-phone');
            let phone = wa || ph;
            
            // Format for Turkey standard 90...
            if (phone) {
                phone = phone.replace(/\D/g, '');
                if (phone.length === 10) phone = '90' + phone;
                if (phone.length === 11 && phone.startsWith('0')) phone = '9' + phone.substring(1);
            }
            
            const name = button.getAttribute('data-name');
            const dbId = button.getAttribute('data-dbid');
            openWhatsApp(phone, name, dbId);
        });
    });
    
    document.getElementById('waModalClose').addEventListener('click', () => {
        document.getElementById('waModal').style.display='none';
    });
    
    document.getElementById('waModalSend').addEventListener('click', () => {
        sendWhatsApp();
    });

    // Google Medium Match Modal Listeners
    const gmModal = document.getElementById('googleMatchModal');
    const gmCancel = document.getElementById('gmModalCancel');
    const gmConfirm = document.getElementById('gmModalConfirm');

    if (gmCancel) {
        gmCancel.addEventListener('click', () => {
            if (gmModal) gmModal.style.display = 'none';
            if (window.pendingGoogleMatch) {
                const { btn, originalHtml } = window.pendingGoogleMatch;
                btn.innerHTML = originalHtml;
                btn.disabled = false;
                if (window.lucide) lucide.createIcons();
                window.pendingGoogleMatch = null;
            }
        });
    }

    if (gmConfirm) {
        gmConfirm.addEventListener('click', () => {
            if (gmModal) gmModal.style.display = 'none';
            if (window.pendingGoogleMatch) {
                const { btn, index, matchToken, originalHtml } = window.pendingGoogleMatch;
                window.pendingGoogleMatch = null;
                confirmGoogleLead(btn, index, matchToken, originalHtml);
            }
        });
    }
});

window.pendingGoogleMatch = null;

function enrichGoogleLead(btn, index, name, city, district) {
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i data-lucide="loader" class="spin"></i> Aranıyor...';
    btn.disabled = true;
    if (window.lucide) lucide.createIcons();

    const formData = new FormData();
    formData.append('name', name);
    formData.append('city', city);
    formData.append('district', district);
    formData.append('csrf_token', '<?= \App\Helpers\Security::generateCsrfToken() ?>');

    fetch('<?= BASE_PATH ?>/leads/enrichGoogle', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            alert(res.message || 'Google Places eşleşmesi bulunamadı.');
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            if (window.lucide) lucide.createIcons();
            return;
        }

        if (res.status === 'medium_confidence') {
            const cand = res.candidate || {};
            const nameEl = document.getElementById('gmCandidateName');
            const addrEl = document.getElementById('gmCandidateAddress');
            const scoreEl = document.getElementById('gmCandidateScore');

            if (nameEl) nameEl.textContent = cand.display_name || '-';
            if (addrEl) addrEl.textContent = cand.formatted_address || '-';
            if (scoreEl) scoreEl.textContent = '%' + Math.round((res.confidence_score || 0.6) * 100);

            window.pendingGoogleMatch = {
                btn: btn,
                index: index,
                matchToken: res.match_token,
                originalHtml: originalHtml
            };

            const gmModal = document.getElementById('googleMatchModal');
            if (gmModal) {
                gmModal.style.display = 'flex';
                if (window.lucide) lucide.createIcons();
            }
            return;
        }

        applyEnrichedLeadData(btn, index, res, originalHtml);
    })
    .catch(err => {
        alert('İşlem sırasında bir hata oluştu.');
        btn.innerHTML = originalHtml;
        btn.disabled = false;
        if (window.lucide) lucide.createIcons();
    });
}

function confirmGoogleLead(btn, index, matchToken, originalHtml) {
    btn.innerHTML = '<i data-lucide="loader" class="spin"></i> Detaylar Alınıyor...';
    btn.disabled = true;
    if (window.lucide) lucide.createIcons();

    const formData = new FormData();
    formData.append('action', 'confirm');
    formData.append('match_token', matchToken);
    formData.append('csrf_token', '<?= \App\Helpers\Security::generateCsrfToken() ?>');

    fetch('<?= BASE_PATH ?>/leads/enrichGoogle', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            alert(res.message || 'Google Place Details alınamadı.');
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            if (window.lucide) lucide.createIcons();
            return;
        }
        applyEnrichedLeadData(btn, index, res, originalHtml);
    })
    .catch(err => {
        alert('Detay alma sırasında hata oluştu.');
        btn.innerHTML = originalHtml;
        btn.disabled = false;
        if (window.lucide) lucide.createIcons();
    });
}

function applyEnrichedLeadData(btn, index, res, originalHtml) {
        if (res.phone) {
            const pSpan = document.getElementById('lead-phone-' + index);
            if (pSpan) pSpan.textContent = res.phone;
            const fPhone = document.getElementById('form-phone-' + index);
            if (fPhone) fPhone.value = res.phone;
        }
        if (res.website) {
            const fWeb = document.getElementById('form-website-' + index);
            if (fWeb) fWeb.value = res.website;
            const webContainer = document.getElementById('lead-web-' + index);
            if (webContainer) {
                const a = document.createElement('a');
                a.href = res.website;
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
                a.style.color = 'var(--color-primary)';
                a.style.display = 'block';
                a.textContent = 'Website';
                webContainer.prepend(a);
            }
        }
        if (res.email) {
            const fEmail = document.getElementById('form-email-' + index);
            if (fEmail) fEmail.value = res.email;
        }
        if (res.instagram) {
            const fInsta = document.getElementById('form-instagram-' + index);
            if (fInsta) fInsta.value = res.instagram;
            const webContainer = document.getElementById('lead-web-' + index);
            if (webContainer && !webContainer.innerHTML.includes('Instagram')) {
                const a = document.createElement('a');
                a.href = res.instagram;
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
                a.style.color = 'var(--text-secondary)';
                a.style.display = 'block';
                a.textContent = 'Instagram';
                webContainer.appendChild(a);
            }
        }
        if (res.facebook) {
            const fFb = document.getElementById('form-facebook-' + index);
            if (fFb) fFb.value = res.facebook;
        }
        if (res.linkedin) {
            const fLi = document.getElementById('form-linkedin-' + index);
            if (fLi) fLi.value = res.linkedin;
        }
        if (res.address) {
            const fAddr = document.getElementById('form-address-' + index);
            if (fAddr) fAddr.value = res.address;
        }
        if (res.google_place_id) {
            const fGid = document.getElementById('form-google-place-id-' + index);
            if (fGid) fGid.value = res.google_place_id;
        }
        const fStatus = document.getElementById('form-enrichment-status-' + index);
        if (fStatus) fStatus.value = res.status || 'google_enriched';

        // Update Source badge & hidden form field (NEVER add Google Places to persistent source)
        if (res.source_trace) {
            const fSource = document.getElementById('form-source-' + index);
            if (fSource) {
                const curSource = fSource.value || '';
                if (!curSource.includes(res.source_trace)) {
                    fSource.value = curSource ? (curSource + ' + ' + res.source_trace) : res.source_trace;
                }
            }

            const row = document.getElementById('lead-row-' + index);
            if (row) {
                const srcCell = row.children[5];
                if (srcCell && !srcCell.textContent.includes(res.source_trace)) {
                    srcCell.innerHTML += '<span style="font-size:0.75rem; color:#60a5fa; display:block;">+ ' + res.source_trace + '</span>';
                }
            }
        }

        // Update WhatsApp button
        const waBtn = btn.parentElement.querySelector('.wa-btn');
        if (waBtn) {
            const curPhone = (document.getElementById('form-phone-' + index) ? document.getElementById('form-phone-' + index).value : '') || '';
            const curWa = (document.getElementById('form-whatsapp-' + index) ? document.getElementById('form-whatsapp-' + index).value : '') || '';
            waBtn.setAttribute('data-phone', curPhone);
            waBtn.setAttribute('data-whatsapp', curWa);
            const hasWa = curWa.length > 0;
            const isMob = curPhone.length >= 10 && (curPhone.startsWith('05') || curPhone.startsWith('905') || curPhone.startsWith('+905') || curPhone.startsWith('5'));
            if (hasWa || isMob) {
                waBtn.removeAttribute('disabled');
            } else {
                waBtn.setAttribute('disabled', 'disabled');
            }
        }

        btn.innerHTML = '<i data-lucide="check"></i> Zenginleştirildi';
        btn.style.background = 'rgba(16, 185, 129, 0.15)';
        btn.style.color = '#34d399';
        btn.disabled = true;
        if (window.lucide) lucide.createIcons();
}

function enrichLead(btn, index, url) {
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i data-lucide="loader" class="spin"></i> Bekleyin...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('url', url);
    formData.append('csrf_token', '<?= \App\Helpers\Security::generateCsrfToken() ?>');

    fetch('<?= BASE_PATH ?>/leads/enrich', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Yeni bilgiler bulundu ve listeye eklendi!');
            
            if (data.data.phone) {
                const phoneSpan = document.getElementById('lead-phone-' + index);
                if (!phoneSpan.textContent.trim()) {
                    phoneSpan.textContent = data.data.phone;
                    document.getElementById('form-phone-' + index).value = data.data.phone;
                }
            }
            
            if (data.data.email) {
                const emailInput = document.getElementById('form-email-' + index);
                if (!emailInput.value) emailInput.value = data.data.email;
            }
            if (data.data.website) {
                const websiteInput = document.getElementById('form-website-' + index);
                if (!websiteInput.value) {
                    websiteInput.value = data.data.website;
                    const a = document.createElement('a');
                    a.href = data.data.website;
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer';
                    a.style.color = 'var(--text-primary)';
                    a.style.textDecoration = 'underline';
                    a.textContent = data.data.website;
                    
                    const container = document.getElementById('lead-web-' + index);
                    container.innerHTML = '';
                    container.appendChild(a);
                }
            }
            
            const addSocial = (type, val) => {
                if (val) {
                    const input = document.getElementById('form-' + type + '-' + index);
                    if (!input.value) {
                        input.value = val;
                        const a = document.createElement('a');
                        a.href = val;
                        a.target = '_blank';
                        a.rel = 'noopener noreferrer';
                        a.style.color = 'var(--text-secondary)';
                        a.style.display = 'block';
                        a.textContent = type.charAt(0).toUpperCase() + type.slice(1);
                        document.getElementById('lead-web-' + index).appendChild(a);
                    }
                }
            };
            
            addSocial('instagram', data.data.instagram);
            addSocial('facebook', data.data.facebook);
            addSocial('linkedin', data.data.linkedin);
            
            // Update WA button
            const waBtn = btn.parentElement.querySelector('.wa-btn');
            if (waBtn) {
                const currentPhone = document.getElementById('form-phone-' + index).value || '';
                const currentWa = document.getElementById('form-whatsapp-' + index).value || '';
                waBtn.setAttribute('data-phone', currentPhone);
                waBtn.setAttribute('data-whatsapp', currentWa);
                
                const hasWa = currentWa.length > 0;
                const isMob = currentPhone.length >= 10 && (currentPhone.startsWith('05') || currentPhone.startsWith('905') || currentPhone.startsWith('+905') || currentPhone.startsWith('5'));
                
                if (hasWa || isMob) {
                    waBtn.removeAttribute('disabled');
                } else {
                    waBtn.setAttribute('disabled', 'disabled');
                }
            }
            
            btn.style.display = 'none';
        } else {
            alert(data.message || 'Yeni bilgi bulunamadı.');
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            lucide.createIcons();
        }
    })
    .catch(error => {
        alert('Bir hata oluştu.');
        btn.innerHTML = originalHtml;
        btn.disabled = false;
        lucide.createIcons();
    });
}

function openWhatsApp(phone, companyName, dbId) {
    if (!phone) {
        alert('Bu firmanın kayıtlı bir telefon numarası yok.');
        return;
    }
    
    const cleanPhone = phone.replace(/[^0-9]/g, '');
    let formattedPhone = cleanPhone;
    if (cleanPhone.length === 10) formattedPhone = '90' + cleanPhone;
    else if (cleanPhone.length === 11 && cleanPhone.startsWith('0')) formattedPhone = '9' + cleanPhone;
    
    const msg = `Merhaba, Ajasis Media'dan Muhammet ben.\n\n${companyName} markasını incelerken sosyal medya ve dijital içerik tarafında geliştirebileceğimiz birkaç fikir dikkatimi çekti.\n\nUygunsanız markanıza özel hazırladığım 1-2 fikri ücretsiz paylaşmak isterim.`;
    
    document.getElementById('waPhone').value = formattedPhone;
    document.getElementById('waMessage').value = msg;
    document.getElementById('waModal').style.display = 'flex';
    
    if (dbId) {
        document.getElementById('waMarkContactedBox').style.display = 'block';
        document.getElementById('waMarkContactedForm').action = '<?= BASE_PATH ?>/companies/markContacted/' + dbId;
    } else {
        document.getElementById('waMarkContactedBox').style.display = 'none';
    }
}

function sendWhatsApp() {
    const phone = document.getElementById('waPhone').value;
    const msg = document.getElementById('waMessage').value;
    const url = `https://wa.me/${phone}?text=${encodeURIComponent(msg)}`;
    window.open(url, '_blank');
    document.getElementById('waModal').style.display = 'none';
}
</script>

<style>
@keyframes spin { 100% { transform: rotate(360deg); } }
.spin { animation: spin 1s linear infinite; }
</style>
<?php else: ?>
<div class="empty-state">
    <div class="empty-state-icon">
        <i data-lucide="search"></i>
    </div>
    <h3 class="empty-state-title">Potansiyel firmaları keşfedin</h3>
    <p class="empty-state-desc">Sektör veya firma türü yazarak Konya'daki işletmeleri farklı kaynaklardan tarayabilirsiniz.</p>
    <div class="quick-search-chips">
        <div class="chip" onclick="document.querySelector('input[name=q]').value='Mimarlık'; document.querySelector('form').submit();">Mimarlık</div>
        <div class="chip" onclick="document.querySelector('input[name=q]').value='Mobilya'; document.querySelector('form').submit();">Mobilya</div>
        <div class="chip" onclick="document.querySelector('input[name=q]').value='Makina'; document.querySelector('form').submit();">Makina</div>
        <div class="chip" onclick="document.querySelector('input[name=q]').value='Restoran'; document.querySelector('form').submit();">Restoran</div>
    </div>
</div>
<?php endif; ?>
