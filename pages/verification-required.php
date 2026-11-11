<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('student');
require_once __DIR__ . '/../includes/db.php';

$user = current_user();
$statement = $pdo->prepare('SELECT status FROM student_verifications WHERE user_id = :user_id LIMIT 1');
$statement->execute(['user_id' => $user['id']]);
$verificationStatus = $statement->fetchColumn();

$message = match ($verificationStatus) {
    'pending' => 'Your school ID is waiting for staff review. You can report lost or found items and claim found items after staff approves it.',
    'code_requested' => 'Your code request is waiting for staff. They will send the code to your school email.',
    'code_sent' => 'Enter the code sent to your school email and submit it with your school ID number and photo from your Profile. Staff must review the ID before you can post or claim.',
    'code_verified' => 'Your code and school ID were submitted. Staff must review your ID before you can post or claim.',
    'rejected' => 'Your student verification was not approved. Request a new code and submit it with your school ID details from your Profile.',
    'verified' => 'Your account is verified. You can now post lost items and claim found items.',
    default => 'Your account is not verified yet. Request a code using your school email from your Profile. After staff sends it, submit the code together with your school ID number and photo.',
};

$pageTitle = 'Student verification required';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="mx-auto max-w-2xl" aria-labelledby="verification-required-title">
    <div class="rounded-xl border border-amber-300 bg-amber-50 p-6 shadow-sm sm:p-8">
        <p class="eyebrow">Student account</p>
        <h1 id="verification-required-title" class="text-3xl font-bold tracking-tight">Verification required</h1>
        <p class="mt-4 text-slate-800"><?= e($message) ?></p>
        <?php if ($verificationStatus !== 'verified'): ?>
            <p class="mt-3 text-slate-700">This is why the lost-item report or claim could not be submitted. Your account and item are still available; complete verification, then try again.</p>
            <a class="primary-button mt-5 inline-flex" href="profile.php">Open Profile and verify</a>
        <?php else: ?>
            <a class="primary-button mt-5 inline-flex" href="found-items.php">Return to found items</a>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
