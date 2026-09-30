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
    <form method="GET" action="<?= BASE_PATH ?>/leads" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 200px;">
            <label class="form-label">Arama Kelimesi / Sektör</label>
            <input type="text" name="q" class="form-input" required value="<?= \App\Helpers\Security::escape($filters['q']) ?>" placeholder="Örn: Mimarlık, Makina...">
        </div>
        <div style="width: 150px;">
            <label class="form-label">Şehir</label>
            <input type="text" name="city" class="form-input" value="<?= \App\Helpers\Security::escape($filters['city']) ?>">
        </div>
        <div style="width: 150px;">
            <label class="form-label">İlçe</label>
            <input type="text" name="district" class="form-input" value="<?= \App\Helpers\Security::escape($filters['district']) ?>" placeholder="Tümü">
        </div>
        <div style="width: 120px;">
            <label class="form-label">Max Sonuç</label>
            <select name="limit" class="form-input">
                <option value="10" <?= $filters['limit'] == 10 ? 'selected' : '' ?>>10</option>
                <option value="25" <?= $filters['limit'] == 25 ? 'selected' : '' ?>>25</option>
                <option value="50" <?= $filters['limit'] == 50 ? 'selected' : '' ?>>50</option>
            </select>
        </div>
        <div style="width: 180px;">
            <label class="form-label">Kaynak</label>
            <select name="source" class="form-input">
                <option value="all" <?= $filters['source'] === 'all' ? 'selected' : '' ?>>Tümü</option>
                <option value="kso" <?= $filters['source'] === 'kso' ? 'selected' : '' ?>>Konya Sanayi Odası</option>
                <option value="listofcompany" <?= $filters['source'] === 'listofcompany' ? 'selected' : '' ?>>List of Company</option>
                <option value="osm" <?= $filters['source'] === 'osm' ? 'selected' : '' ?>>OpenStreetMap</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-primary"><i data-lucide="search"></i> Ara</button>
        </div>
        <?php if (!empty($filters['q'])): ?>
            <div style="margin-left: auto;">
                <a href="https://www.google.com/maps/search/<?= urlencode($filters['q'] . ' ' . $filters['district'] . ' ' . $filters['city']) ?>" target="_blank" rel="noopener noreferrer" class="btn" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
                    <i data-lucide="map"></i> Google Maps'te Ara
                </a>
            </div>
        <?php endif; ?>
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
        ?>
        <div style="background: <?= $bg ?>; color: <?= $color ?>; padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
            <strong><?= $source ?></strong>: 
            <?= $stat['status'] === 'success' ? $stat['count'] . ' kayıt (' . $stat['duration'] . ')' : $stat['status'] ?>
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
                <td colspan="8" style="padding: 2rem; text-align: center; color: var(--text-secondary);">
                    Sonuç bulunamadı. <br><br>
                    <a href="https://www.google.com/maps/search/<?= urlencode($filters['q'] . ' ' . $filters['district'] . ' ' . $filters['city']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary" style="display: inline-flex;">Google Maps'te Ara</a>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach($leads as $i => $lead): ?>
                <tr style="border-bottom: 1px solid var(--card-border);" id="lead-row-<?= $i ?>">
                    <td style="padding: 1rem; font-weight: 500;">
                        <?= \App\Helpers\Security::escape($lead['name']) ?>
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
                                <input type="hidden" name="address" value="<?= \App\Helpers\Security::escape($lead['address'] ?? '') ?>">
                                <input type="hidden" name="source" value="<?= \App\Helpers\Security::escape($lead['source'] ?? '') ?>">
                                <button type="submit" class="btn btn-primary" style="padding: 0.5rem;"><i data-lucide="plus"></i> Kaydet</button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($lead['website']): ?>
                            <button type="button" class="btn enrich-btn" data-index="<?= $i ?>" data-url="<?= \App\Helpers\Security::escape($lead['website']) ?>" style="padding: 0.5rem; background: rgba(168, 85, 247, 0.15); color: #c084fc;">
                                <i data-lucide="zap"></i> Zenginleştir
                            </button>
                        <?php elseif (strpos($lead['source_url'] ?? '', 'kso.org.tr') !== false): ?>
                            <button type="button" class="btn enrich-btn" data-index="<?= $i ?>" data-url="<?= \App\Helpers\Security::escape($lead['source_url']) ?>" style="padding: 0.5rem; background: rgba(168, 85, 247, 0.15); color: #c084fc;">
                                <i data-lucide="search"></i> KSO Detay
                            </button>
                        <?php endif; ?>
                        
                        <button type="button" class="btn wa-btn" data-phone="<?= \App\Helpers\Security::escape($lead['phone']) ?>" data-name="<?= \App\Helpers\Security::escape($lead['name']) ?>" data-dbid="<?= $lead['db_id'] ?: '' ?>" <?= empty($lead['phone']) ? 'disabled' : '' ?> style="padding: 0.5rem; background: rgba(16, 185, 129, 0.15); color: #34d399;">
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

<script>
document.addEventListener('DOMContentLoaded', () => {
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
            const phone = button.getAttribute('data-phone');
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
});

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
<?php endif; ?>
