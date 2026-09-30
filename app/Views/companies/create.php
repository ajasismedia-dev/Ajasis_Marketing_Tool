<?php
$isEdit = isset($company);
$data = $isEdit ? $company : ($formData ?? []);
$action = $isEdit ? BASE_PATH . '/companies/update/' . $company['id'] : BASE_PATH . '/companies/store';

$statusLabels = [
    'new' => 'Yeni',
    'contacted' => 'İletişime Geçildi',
    'replied' => 'Cevap Verdi',
    'proposal' => 'Teklif Gönderildi',
    'customer' => 'Müşteri',
    'negative' => 'Olumsuz'
];
?>

<?php if (!empty($duplicateWarning)): ?>
    <div class="glass-panel" style="background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.3); padding: 1rem; margin-bottom: 1.5rem; color: #fbbf24;">
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
            <i data-lucide="alert-triangle"></i>
            <strong>Bu firma mevcut bir kayıtla eşleşiyor olabilir.</strong>
        </div>
        <p>Benzer kayıt: <a href="<?= BASE_PATH ?>/companies/show/<?= $duplicateWarning['id'] ?>" style="color: #fff; text-decoration: underline;" target="_blank"><?= \App\Helpers\Security::escape($duplicateWarning['name']) ?></a></p>
        <p style="margin-top: 0.5rem; font-size: 0.875rem;">Yine de kaydetmek istiyorsanız formu tekrar gönderin.</p>
    </div>
<?php endif; ?>

<form action="<?= $action ?>" method="POST" style="display: grid; grid-template-columns: 7fr 3fr; gap: 2rem;" class="form-grid">
    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
    <?php if (!empty($duplicateWarning)): ?>
        <input type="hidden" name="confirm_duplicate" value="1">
    <?php endif; ?>

    <!-- Sol: Ana Bilgiler -->
    <div class="glass-panel" style="padding: 2rem;">
        <h2 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1.5rem; border-bottom: 1px solid var(--card-border); padding-bottom: 0.75rem;">Firma Bilgileri</h2>
        
        <div class="form-group">
            <label class="form-label">Firma Adı *</label>
            <input type="text" name="name" class="form-input" required value="<?= \App\Helpers\Security::escape($data['name'] ?? '') ?>">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label">Sektör</label>
                <input type="text" name="sector" class="form-input" value="<?= \App\Helpers\Security::escape($data['sector'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Telefon</label>
                <input type="text" name="phone" class="form-input" value="<?= \App\Helpers\Security::escape($data['phone'] ?? '') ?>">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label">WhatsApp</label>
                <input type="text" name="whatsapp" class="form-input" value="<?= \App\Helpers\Security::escape($data['whatsapp'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">E-posta</label>
                <input type="email" name="email" class="form-input" value="<?= \App\Helpers\Security::escape($data['email'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Web Sitesi</label>
            <input type="url" name="website" class="form-input" value="<?= \App\Helpers\Security::escape($data['website'] ?? '') ?>">
        </div>

        <h3 style="font-size: 1rem; font-weight: 600; margin: 2rem 0 1rem 0; border-bottom: 1px solid var(--card-border); padding-bottom: 0.75rem;">Sosyal Medya</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label class="form-label">Instagram</label>
                <input type="url" name="instagram" class="form-input" value="<?= \App\Helpers\Security::escape($data['instagram'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Facebook</label>
                <input type="url" name="facebook" class="form-input" value="<?= \App\Helpers\Security::escape($data['facebook'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">LinkedIn</label>
                <input type="url" name="linkedin" class="form-input" value="<?= \App\Helpers\Security::escape($data['linkedin'] ?? '') ?>">
            </div>
        </div>

        <h3 style="font-size: 1rem; font-weight: 600; margin: 2rem 0 1rem 0; border-bottom: 1px solid var(--card-border); padding-bottom: 0.75rem;">Lokasyon & Adres</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label">İl</label>
                <input type="text" name="city" class="form-input" value="<?= \App\Helpers\Security::escape($data['city'] ?? 'Konya') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">İlçe</label>
                <input type="text" name="district" class="form-input" value="<?= \App\Helpers\Security::escape($data['district'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Açık Adres</label>
            <textarea name="address" class="form-input" rows="3"><?= \App\Helpers\Security::escape($data['address'] ?? '') ?></textarea>
        </div>
    </div>

    <!-- Sağ: Durum ve Aksiyonlar -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <div class="glass-panel" style="padding: 2rem;">
            <div class="form-group">
                <label class="form-label">Durum</label>
                <select name="status" class="form-input">
                    <?php foreach($statusLabels as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($data['status'] ?? 'new') === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Kaynak</label>
                <input type="text" name="source" class="form-input" value="<?= \App\Helpers\Security::escape($data['source'] ?? '') ?>" placeholder="Örn: Google Haritalar">
            </div>
        </div>

        <div class="glass-panel" style="padding: 2rem;">
            <div class="form-group">
                <label class="form-label">Notlar</label>
                <textarea name="notes" class="form-input" rows="5"><?= \App\Helpers\Security::escape($data['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="glass-panel" style="padding: 2rem;">
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: 1rem;">
                <i data-lucide="save"></i> <?= $isEdit ? 'Güncelle' : 'Kaydet' ?>
            </button>
            <a href="<?= BASE_PATH ?>/companies" class="btn" style="width: 100%; background: rgba(255,255,255,0.1); color: #fff; text-align: center;">İptal</a>
        </div>
    </div>
</form>

<style>
@media (max-width: 1024px) {
    .form-grid {
        grid-template-columns: 1fr !important;
    }
}
</style>
