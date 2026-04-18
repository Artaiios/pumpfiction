<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$navItems = [
    ['page' => 'dashboard', 'icon' => '🏠', 'label' => 'Dashboard'],
    ['page' => 'leaderboard', 'icon' => '🏆', 'label' => 'Ranking'],
    ['page' => 'stats', 'icon' => '📊', 'label' => 'Stats'],
    ['page' => 'wall', 'icon' => '📢', 'label' => 'Wall'],
    ['page' => 'voting', 'icon' => '🗳️', 'label' => 'Voting'],
    ['page' => 'profile', 'icon' => '👤', 'label' => 'Profil'],
];
?>
<nav class="pf-nav fixed bottom-0 left-0 right-0 lg:top-0 lg:bottom-0 lg:right-auto z-50">
    <div class="flex lg:flex-col justify-around lg:justify-start lg:gap-1 lg:px-1 lg:pt-4">
        <?php foreach ($navItems as $item): ?>
        <a href="<?= $item['page'] ?>.php" class="pf-nav-item <?= $currentPage === $item['page'] ? 'active' : '' ?>">
            <span class="pf-nav-icon"><?= $item['icon'] ?></span><span><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</nav>
