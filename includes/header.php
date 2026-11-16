<?php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/profile_avatar.php';
start_app_session();

$currentUser = current_user();
$successFlash = get_flash('success');
$errorFlash = get_flash('error');
$pageDesign = isset($pageDesign) && is_string($pageDesign) && $pageDesign !== '' ? $pageDesign : 'site';
$currentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$hasMobileBottomNav = $currentUser !== null
    && !in_array($currentPage, ['login.php', 'register.php', 'report-lost.php', 'upload-found.php'], true);
require __DIR__ . '/navigation.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#21164c">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | ' : '' ?>ReLink · Classroom Lost &amp; Found Management System</title>
    <link rel="stylesheet" href="../assets/tailwind.generated.css?v=20261023-1">
    <link rel="stylesheet" href="../assets/style-base.php?v=20261116-4">

<link rel="icon" href="../assets/relink-icon.svg" type="image/svg+xml"><link rel="stylesheet" href="../assets/ui-consistency.css?v=<?= (int) filemtime(__DIR__ . '/../assets/ui-consistency.css') ?>"></head>
<body class="site-app website-shell relink-phase5 min-h-screen antialiased design-reference design-zeta design-<?= e($pageDesign) ?><?= $hasMobileBottomNav ? ' has-mobile-bottom-nav' : '' ?>">
    <a class="skip-link sr-only z-50 rounded-md bg-white p-3 text-blue-800 focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-blue-700" href="#main-content">Skip to main content</a>
    <header class="<?= $currentUser === null ? 'relink-guest-header' : 'relink-member-header' ?>">
        <div class="mx-auto flex max-w-6xl gap-4 px-4 py-4 sm:items-center sm:justify-between sm:px-6">
            <a class="text-xl font-bold tracking-tight focus:outline focus:outline-2 focus:outline-offset-4 focus:outline-blue-700" href="<?= $currentUser !== null ? '../pages/dashboard.php' : '../public/index.php' ?>"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.72-1.71"></path><path d="m8 12 8 0"></path></svg></span><span class="brand-copy"><span class="brand-name">ReLink</span><span class="brand-tagline">Classroom Lost &amp; Found Management System</span></span></a>
            <nav class="primary-navigation" aria-label="Main navigation">
                <ul class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm font-medium">
                <?php foreach ($navigationItems as $section => [$label, $href, $icon]): ?>
                    <?php if (in_array($section, ['support', 'account'], true) || ($currentUser === null && $section === 'home')) { continue; } ?>
                    <li><a <?= $section === 'support' && $currentUser !== null && $currentUser['role'] === 'student' ? 'data-open-support-chat' : '' ?> href="<?= e($href) ?>" <?= $navigationSection === $section ? 'aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icon ?></svg><span><?= e($label) ?></span></a></li>
                <?php endforeach; ?>
                </ul>
            </nav>
            <?php if ($currentUser === null): ?>
                <a class="relink-guest-login" href="../pages/login.php"><span>Log in</span></a>
            <?php else: ?>
            <details class="design-account-menu">
                    <summary aria-label="Account and site navigation"><span aria-hidden="true">☰</span><span class="account-menu-label">Menu</span></summary>
                    <div class="account-menu-panel">
                        <?php if ($currentUser !== null): ?>
                            <p class="account-menu-identity">
                                <?php $avatarSrc = profile_avatar_src($currentUser['avatar_path'] ?? null); ?>
                                <?php if ($avatarSrc !== null): ?><img class="profile-avatar profile-avatar-menu" src="<?= e($avatarSrc) ?>" alt=""><?php endif; ?>
                                <span><?= e($currentUser['name'] ?? 'Account') ?></span>
                            </p>
                            <a href="../pages/dashboard.php">Dashboard</a>
                            <a href="../pages/profile.php">Profile</a>
                            <?php if ($currentUser['role'] === 'staff'): ?>
                                <a href="../pages/support-inbox.php">Customer service</a>
                                <a href="../pages/upload-found.php">Report found item</a>
                                <a href="../pages/manage-found.php">Manage found items</a>
                                <a href="../pages/manage-lost.php">Manage lost reports</a>
                                <a href="../pages/review-claims.php">Review claims</a>
                                <a href="../pages/user-management.php">User management</a>
                            <?php else: ?>
                                <a data-open-support-chat href="../pages/support-chat.php">Chat with staff</a>
                                <a href="../pages/report-lost.php">Report a lost item</a>
                                <a href="../pages/upload-found.php">Report found item</a>
                                <a href="../pages/my-claims.php#claims">My activity</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>Welcome to ReLink</p>
                            <a href="../pages/login.php">Log in</a>
                            <a href="../pages/register.php">Create an account</a>
                        <?php endif; ?>
                        <?php if ($currentUser !== null): ?>
                            <form method="post" action="../pages/logout.php"><?= csrf_field() ?><button type="submit">Log out</button></form>
                        <?php endif; ?>
                    </div>
            </details>
            <?php endif; ?>
        </div>
    </header>
    <main id="main-content" class="mx-auto min-h-[60vh] max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
        <?php if ($successFlash !== null): ?>
            <p class="flash-message flash-message-success" role="status" aria-live="polite">
                <svg class="flash-message-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 6 9 17l-5-5"></path>
                    <circle cx="12" cy="12" r="10"></circle>
                </svg>
                <span><?= e($successFlash) ?></span>
            </p>
        <?php endif; ?>
        <?php if ($errorFlash !== null): ?>
            <p class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4 text-red-900" role="alert"><?= e($errorFlash) ?></p>
        <?php endif; ?>
