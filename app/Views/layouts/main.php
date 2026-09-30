<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \App\Helpers\Security::escape($page_title ?? 'Marketing Tool') ?> - Ajasis Media</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/public/assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

<div class="app-container">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="brand">
            ajasis <span>marketing</span>
        </div>
        <ul class="nav-menu">
            <li>
                <a href="<?= BASE_PATH ?>/dashboard" class="nav-link <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/dashboard') !== false ? 'active' : '' ?>">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                    Dashboard
                </a>
            </li>
            <li>
                <a href="<?= BASE_PATH ?>/leads" class="nav-link <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/leads') !== false ? 'active' : '' ?>">
                    <i data-lucide="search" class="w-5 h-5"></i>
                    Firma Bul
                </a>
            </li>
            <li>
                <a href="<?= BASE_PATH ?>/companies" class="nav-link <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/companies') !== false ? 'active' : '' ?>">
                    <i data-lucide="building-2" class="w-5 h-5"></i>
                    Firmalar
                </a>
            </li>
            <li>
                <a href="<?= BASE_PATH ?>/history" class="nav-link <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/history') !== false ? 'active' : '' ?>">
                    <i data-lucide="history" class="w-5 h-5"></i>
                    İletişim Geçmişi
                </a>
            </li>
            <li style="margin-top: auto;">
                <a href="<?= BASE_PATH ?>/settings" class="nav-link <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/settings') !== false ? 'active' : '' ?>">
                    <i data-lucide="settings" class="w-5 h-5"></i>
                    Ayarlar
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="topbar">
            <h1><?= \App\Helpers\Security::escape($page_title ?? 'Dashboard') ?></h1>
            <div class="user-info">
                <span><?= \App\Helpers\Security::escape($_SESSION['user_name'] ?? '') ?></span>
                <a href="<?= BASE_PATH ?>/login/logout" class="logout-btn">
                    <i data-lucide="log-out" class="w-5 h-5"></i> Çıkış
                </a>
            </div>
        </header>
        
        <div class="content-area">
            <?= $content ?>
        </div>
    </main>
</div>

<script>
    lucide.createIcons();
</script>
</body>
</html>
