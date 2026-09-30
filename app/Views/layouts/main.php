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
    <aside class="sidebar" id="app-sidebar">
        <div class="brand">
            <span class="brand-text">ajasis <span>marketing</span></span>
            <button type="button" class="toggle-sidebar-btn" id="toggle-sidebar-btn" title="Menüyü Daralt/Genişlet">
                <i data-lucide="menu"></i>
            </button>
        </div>
        <ul class="nav-menu">
            <li>
                <a href="<?= BASE_PATH ?>/dashboard" class="nav-link <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/dashboard') !== false ? 'active' : '' ?>" title="Dashboard">
                    <i data-lucide="layout-dashboard"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_PATH ?>/leads" class="nav-link <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/leads') !== false ? 'active' : '' ?>" title="Firma Bul">
                    <i data-lucide="search"></i>
                    <span>Firma Bul</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_PATH ?>/companies" class="nav-link <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/companies') !== false ? 'active' : '' ?>" title="Firmalar">
                    <i data-lucide="building-2"></i>
                    <span>Firmalar</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_PATH ?>/history" class="nav-link <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/history') !== false ? 'active' : '' ?>" title="İletişim Geçmişi">
                    <i data-lucide="history"></i>
                    <span>İletişim Geçmişi</span>
                </a>
            </li>
            <li style="margin-top: auto;">
                <a href="<?= BASE_PATH ?>/settings" class="nav-link <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/settings') !== false ? 'active' : '' ?>" title="Ayarlar">
                    <i data-lucide="settings"></i>
                    <span>Ayarlar</span>
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
                <form action="<?= BASE_PATH ?>/login/logout" method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                    <button type="submit" class="logout-btn">
                        <i data-lucide="log-out"></i> Çıkış
                    </button>
                </form>
            </div>
        </header>
        
        <div class="content-area">
            <?= $content ?>
        </div>
    </main>
</div>

<script>
    // Initialize Lucide Icons
    lucide.createIcons();

    // Sidebar Toggle Logic
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('app-sidebar');
        const toggleBtn = document.getElementById('toggle-sidebar-btn');
        
        // Restore state from localStorage
        const isCollapsed = localStorage.getItem('sidebar_collapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
        }

        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebar_collapsed', sidebar.classList.contains('collapsed'));
        });
    });
</script>
</body>
</html>
