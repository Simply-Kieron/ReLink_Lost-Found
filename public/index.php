<?php

require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Home';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-shell zeta-home">
    <section class="zeta-hero" aria-labelledby="home-title">
        <div class="zeta-hero-copy">
            <p class="zeta-eyebrow">ReLink · Classroom Lost &amp; Found Management System</p>
            <h1 id="home-title">Lost something? Let’s get it back to you.</h1>
            <p class="zeta-hero-description">One place to report a missing item, browse what has been handed in, and make a verified claim.</p>
            <div class="zeta-actions">
                <a class="zeta-button zeta-button-light" href="../pages/report-lost.php">Report a lost item <span aria-hidden="true">→</span></a>
                <a class="zeta-button zeta-button-outline" href="../pages/found-items.php">Browse found items</a>
            </div>
        </div>
        <div class="zeta-hero-art" aria-hidden="true">
            <div class="zeta-orbit zeta-orbit-outer"></div>
            <div class="zeta-orbit zeta-orbit-inner"></div>
            <div class="zeta-item-card zeta-item-card-back">
                <span class="zeta-item-icon">⌕</span>
                <span class="zeta-item-label">FOUND ITEM</span>
                <strong>Ready to be claimed</strong>
            </div>
            <div class="zeta-item-card zeta-item-card-front">
                <span class="zeta-item-label">RELINK</span>
                <span class="zeta-item-icon zeta-item-icon-accent">↗</span>
                <strong>Every item has a way home.</strong>
                <span class="zeta-card-line"></span>
                <span class="zeta-card-caption">Report · Match · Reunite</span>
            </div>
            <span class="zeta-spark zeta-spark-one">✦</span>
            <span class="zeta-spark zeta-spark-two">✦</span>
        </div>
    </section>

    <section class="zeta-process" aria-label="Our three-step process">
        <article class="zeta-process-step">
            <span class="zeta-step-number">01</span>
            <div>
                <h2>Report or browse</h2>
                <p>Students report lost and found items; staff review found reports before they are listed.</p>
            </div>
        </article>
        <article class="zeta-process-step">
            <span class="zeta-step-number">02</span>
            <div>
                <h2>Make a claim</h2>
                <p>Share private identifying details to help confirm an item is yours.</p>
            </div>
        </article>
        <article class="zeta-process-step">
            <span class="zeta-step-number">03</span>
            <div>
                <h2>Get it verified</h2>
                <p>Staff review each claim before coordinating a safe return.</p>
            </div>
        </article>
    </section>

    <section class="zeta-categories" aria-labelledby="home-actions-title">
        <div class="zeta-section-heading">
            <div>
                <p class="zeta-eyebrow">Start your search</p>
                <h2 id="home-actions-title">What are you looking for?</h2>
            </div>
            <a href="../pages/report-lost.php">Report a lost item <span aria-hidden="true">→</span></a>
        </div>
        <ul class="zeta-home-actions">
            <li><a href="../pages/found-items.php"><strong>Browse found items</strong><span>See items handed in on campus.</span></a></li>
            <li><a href="../pages/lost-items.php"><strong>Browse lost reports</strong><span>Check reports from other students.</span></a></li>
            <li><a href="../pages/report-lost.php"><strong>Report a lost item</strong><span>Share details to help find your item.</span></a></li>
        </ul>
    </section>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
