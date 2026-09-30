<div class="auth-box glass-panel">
    <div class="brand">
        ajasis <span>marketing</span>
    </div>

    <div style="text-align: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem; font-weight: 600;">İlk Kurulum</h2>
        <p style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.5rem;">Lütfen yönetici hesabınızı oluşturun.</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert-error">
            <?= \App\Helpers\Security::escape($error) ?>
        </div>
    <?php endif; ?>

    <form action="<?= BASE_PATH ?>/setup/submit" method="POST">
        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
        
        <div class="form-group">
            <label class="form-label">Ad Soyad</label>
            <input type="text" name="name" class="form-input" required autofocus>
        </div>

        <div class="form-group">
            <label class="form-label">Kullanıcı Adı</label>
            <input type="text" name="username" class="form-input" required>
        </div>
        
        <div class="form-group">
            <label class="form-label">Şifre (Min. 10 karakter)</label>
            <input type="password" name="password" class="form-input" minlength="10" required>
        </div>

        <div class="form-group">
            <label class="form-label">Şifre Tekrar</label>
            <input type="password" name="password_confirm" class="form-input" minlength="10" required>
        </div>
        
        <button type="submit" class="btn btn-primary">Hesabı Oluştur</button>
    </form>
</div>
