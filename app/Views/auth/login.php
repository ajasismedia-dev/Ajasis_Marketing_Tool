<div class="auth-box">
    <div class="brand">
        ajasis <span>marketing</span>
    </div>
    <div class="auth-subtitle">Hesabınıza giriş yapın.</div>

    <?php if (!empty($error)): ?>
        <div class="alert-error">
            <?= \App\Helpers\Security::escape($error) ?>
        </div>
    <?php endif; ?>

    <form action="<?= BASE_PATH ?>/login/submit" method="POST">
        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
        
        <div class="form-group">
            <label class="form-label">Kullanıcı Adı</label>
            <input type="text" name="username" class="form-input" required autofocus>
        </div>
        
        <div class="form-group">
            <label class="form-label">Şifre</label>
            <input type="password" name="password" class="form-input" required>
        </div>
        
        <button type="submit" class="btn btn-primary">Giriş Yap</button>
    </form>
</div>
