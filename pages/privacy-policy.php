<?php

$pageTitle = 'Privacy Policy';
require_once __DIR__ . '/../includes/header.php';
?>
<article class="relink-policy" aria-labelledby="policy-title">
    <div class="relink-policy-heading"><p class="relink-policy-brand">ReLink · Classroom Lost &amp; Found</p>
    <h1 id="policy-title">Privacy Policy</h1>
    <p class="mt-3 text-slate-700">This page explains the information the Classroom Lost &amp; Found application stores and displays. The school must complete the marked contact and retention details before using this application with real users.</p>
    </div>
    <nav class="relink-policy-toc" aria-label="On this page"><h2>On this page</h2><ul><li><a href="#information-title">Information the application stores</a></li><li><a href="#use-title">How information is used and shown</a></li><li><a href="#sharing-title">Who can see the information</a></li><li><a href="#retention-title">How long information is kept</a></li><li><a href="#contact-title">Questions about privacy</a></li></ul></nav>
    <section class="mt-8" aria-labelledby="information-title">
        <h2 id="information-title" class="text-xl font-bold">Information the application stores</h2>
        <ul class="mt-3 list-inside list-disc space-y-2 text-slate-700">
            <li>Account details: your name, email address, account role, and account creation date. Passwords are stored as password hashes, not as plain text.</li>
            <li>Student verification: school email address, school ID number, school ID photo, review status, and verification timestamps. Verification codes are stored as password hashes.</li>
            <li>Item reports: item title, category, description, location, event date, report status, and any image you upload with its alternative text.</li>
            <li>Found-item verification details: private details submitted with a found-item report and used by staff to verify ownership.</li>
            <li>Claims: the item being claimed, the claimant account, proof details supplied by the claimant, claim status, review account, and submission date.</li>
            <li>Customer support: conversation references, messages, sender accounts, timestamps, and conversation status used to handle support requests.</li>
            <li>A session cookie used by PHP to maintain the current session, including the signed-in state. This cookie supports session-related features.</li>
        </ul>
    </section>
    <section class="mt-8" aria-labelledby="use-title">
        <h2 id="use-title" class="text-xl font-bold">How information is used and shown</h2>
        <p class="mt-3 text-slate-700">The application uses account details to provide sign-in and role-based pages, reports to let people browse and search for items, and claim details to let staff review a request.</p>
        <p class="mt-3 text-slate-700">Reports awaiting review or rejected by staff are not shown in public listings. Approved lost and found reports can appear on public item pages. Images may also be shown; images for found items in the Personal items category are replaced with a privacy placeholder on public pages. Claim proof, private found-item verification details, and student ID photos are intended for staff review and are not shown on public item pages.</p>
    </section>
    <section class="mt-8" aria-labelledby="sharing-title">
        <h2 id="sharing-title" class="text-xl font-bold">Who can see the information</h2>
        <p class="mt-3 text-slate-700">Public item listings can be viewed by people visiting the application. A signed-in student can view their own reports and claims. Staff pages provide staff accounts with access to found-item management, claim review, and student ID photos and details for verification.</p>
    </section>
    <section class="mt-8" aria-labelledby="retention-title">
        <h2 id="retention-title" class="text-xl font-bold">How long information is kept</h2>
        <p class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-950"><strong>School action required:</strong> Fill in the school-approved retention period and deletion process before using this application: <strong>[FILL IN RETENTION PERIOD AND DELETION PROCESS]</strong>.</p>
    </section>
    <section class="mt-8" aria-labelledby="contact-title">
        <h2 id="contact-title" class="text-xl font-bold">Questions about privacy</h2>
        <p class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-950"><strong>School action required:</strong> Fill in the responsible school contact before using this application: <strong>[FILL IN SCHOOL NAME, OFFICE, EMAIL ADDRESS, AND/OR PHONE NUMBER]</strong>.</p>
    </section>
    <p class="mt-8 text-sm text-slate-600">This draft describes the application as currently built. The school should review it and complete the marked details before real student information is entered.</p>
</article>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
