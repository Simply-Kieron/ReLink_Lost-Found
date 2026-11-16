<?php
// Shared destinations keep desktop and mobile active states in sync.
$navigationSection = 'home';
if (in_array($currentPage, ['found-items.php', 'item-detail.php', 'upload-found.php', 'edit-found.php', 'manage-found.php'], true)) {
    $navigationSection = 'found';
} elseif (in_array($currentPage, ['lost-items.php', 'lost-item-detail.php', 'report-lost.php', 'edit-report.php', 'report-found-lost.php', 'manage-lost.php'], true)) {
    $navigationSection = 'lost';
} elseif (in_array($currentPage, ['support-chat.php', 'support-inbox.php'], true)) {
    $navigationSection = 'support';
} elseif (in_array($currentPage, ['activity.php', 'my-claims.php'], true)) {
    $navigationSection = 'activity';
} elseif (!in_array($currentPage, ['index.php', 'dashboard.php', 'login.php', 'register.php'], true)) {
    $navigationSection = 'account';
}
if ($currentPage === 'item-detail.php' && isset($item['type'])) {
    $navigationSection = $item['type'] === 'lost' ? 'lost' : 'found';
}
$navigationItems = [
    'home' => ['Home', $currentUser !== null ? '../pages/dashboard.php' : '../public/index.php', '<path d="m3 10 9-7 9 7v10H15v-6H9v6H3Z"/>'],
    'found' => ['Found', '../pages/found-items.php', '<path d="m12 3 9 5v9l-9 5-9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v9"/>'],
    'lost' => ['Lost', '../pages/lost-items.php', '<path d="M3 3h8l10 10-8 8L3 11Z"/><circle cx="7.5" cy="7.5" r="1"/>'],
    'support' => ['Support', $currentUser !== null && $currentUser['role'] === 'staff' ? '../pages/support-inbox.php' : '../pages/support-chat.php', '<path d="M4 13v-2a8 8 0 0 1 16 0v2"/><rect x="2" y="12" width="5" height="8" rx="2"/><rect x="17" y="12" width="5" height="8" rx="2"/><path d="M20 20c0 2-3 2-6 2"/>'],
    'account' => ['Account', '../pages/profile.php', '<circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2Z"/>'],
];

if ($currentUser !== null && $currentUser['role'] === 'staff') {
    $navigationItems['found'][1] = '../pages/manage-found.php';
    $navigationItems['lost'][1] = '../pages/manage-lost.php';
}
