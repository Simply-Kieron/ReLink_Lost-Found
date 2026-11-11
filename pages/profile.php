<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/student_verification.php';
require_once __DIR__ . '/../includes/profile_avatar.php';

$user = require_login();
require_once __DIR__ . '/../includes/db.php';

$profileErrors = [];
$passwordErrors = [];
$profileSuccess = null;
$passwordSuccess = null;
$profileName = $user['name'];
$profileEmail = $user['email'] ?? '';
$schoolEmail = '';
$schoolIdNumber = '';
$avatarPath = $user['avatar_path'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && request_body_exceeds_post_limit()) {
    http_response_code(413);
    $profileErrors[] = 'The uploaded form exceeded the server request limit. Choose an image within the stated size limit and try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'save-avatar' || $action === 'remove-avatar') {
        $newAvatar = null;
        if ($action === 'save-avatar') {
            $avatarUpload = validate_profile_avatar_upload($_FILES['avatar'] ?? []);
            if ($avatarUpload['error'] !== null) {
                $profileErrors[] = $avatarUpload['error'];
            } else {
                $newAvatar = $avatarUpload['path'];
            }
        }

        if ($profileErrors === []) {
            try {
                $avatarStatement = $pdo->prepare('UPDATE users SET avatar_path = :avatar_path WHERE id = :id');
                $avatarStatement->execute(['avatar_path' => $newAvatar, 'id' => $user['id']]);
                delete_profile_avatar($avatarPath);
                $avatarPath = $newAvatar;
                $_SESSION['user']['avatar_path'] = $avatarPath;
                $user['avatar_path'] = $avatarPath;
                $profileSuccess = $newAvatar === null ? 'Your profile photo has been removed.' : 'Your profile photo has been updated.';
            } catch (PDOException $exception) {
                if ($newAvatar !== null) {
                    delete_profile_avatar($newAvatar);
                }
                error_log('Profile photo update failed: ' . $exception->getMessage());
                http_response_code(500);
                $profileErrors[] = 'Your profile photo could not be updated right now. Please try again later.';
            }
        }
    } elseif ($action === 'update-profile') {
        $postedName = $_POST['name'] ?? '';
        $profileName = is_string($postedName) ? trim($postedName) : '';
        if ($user['role'] === 'staff') {
            $postedEmail = $_POST['email'] ?? '';
            $profileEmail = is_string($postedEmail) ? strtolower(trim($postedEmail)) : '';
        }

        if ($profileName === '' || preg_match('/\A[\P{Cc}]{1,150}\z/u', $profileName) !== 1) {
            $profileErrors[] = 'Enter your name using no more than 150 characters.';
        }
        if (
            $user['role'] === 'staff'
            && (
                $profileEmail === ''
                || strlen($profileEmail) > 191
                || filter_var($profileEmail, FILTER_VALIDATE_EMAIL) === false
            )
        ) {
            $profileErrors[] = 'Enter a valid email address using no more than 191 characters.';
        }

        if ($profileErrors === []) {
            try {
                if ($user['role'] === 'staff') {
                    $update = $pdo->prepare('UPDATE users SET name = :name, email = :email WHERE id = :id');
                    $update->execute([
                        'name' => $profileName,
                        'email' => $profileEmail,
                        'id' => $user['id'],
                    ]);
                    $_SESSION['user']['email'] = $profileEmail;
                    $user['email'] = $profileEmail;
                    $profileSuccess = 'Your name and email have been updated.';
                } else {
                    $update = $pdo->prepare('UPDATE users SET name = :name WHERE id = :id');
                    $update->execute([
                        'name' => $profileName,
                        'id' => $user['id'],
                    ]);
                    $profileSuccess = 'Your name has been updated. Your sign-up email cannot be changed here.';
                }
                $_SESSION['user']['name'] = $profileName;
                $user['name'] = $profileName;
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000' && $user['role'] === 'staff') {
                    $profileErrors[] = 'That email address is already in use. Choose another email address.';
                } else {
                    error_log('Profile update failed: ' . $exception->getMessage());
                    http_response_code(500);
                    $profileErrors[] = 'Your profile could not be updated right now. Please try again later.';
                }
            }
        }
    } elseif ($action === 'request-verification-code' && $user['role'] === 'student') {
        $schoolEmailInput = $_POST['school_email'] ?? '';
        $schoolEmail = is_string($schoolEmailInput) ? strtolower(trim($schoolEmailInput)) : '';
        if (
            $schoolEmail === ''
            || strlen($schoolEmail) > 191
            || filter_var($schoolEmail, FILTER_VALIDATE_EMAIL) === false
        ) {
            $profileErrors[] = 'Enter a valid school email address.';
        }

        if ($profileErrors === []) {
            try {
                $pdo->beginTransaction();
                $existingStatement = $pdo->prepare(
                    'SELECT status, id_image_path FROM student_verifications WHERE user_id = :user_id FOR UPDATE'
                );
                $existingStatement->execute(['user_id' => $user['id']]);
                $existingVerification = $existingStatement->fetch();

                if (
                    is_array($existingVerification)
                    && !in_array($existingVerification['status'], ['rejected', 'code_sent'], true)
                ) {
                    $pdo->rollBack();
                    $profileErrors[] = 'Your verification request is already waiting for a code, ID review, or has been approved.';
                } else {
                    $statement = is_array($existingVerification)
                        ? $pdo->prepare(
                            "UPDATE student_verifications
                            SET school_email = :school_email, school_id_number = NULL, id_image_path = NULL,
                                status = 'code_requested', code_hash = NULL, code_expires_at = NULL, code_attempts = 0,
                                reviewed_by = NULL, submitted_at = CURRENT_TIMESTAMP, reviewed_at = NULL, verified_at = NULL
                            WHERE user_id = :user_id"
                        )
                        : $pdo->prepare(
                            "INSERT INTO student_verifications (user_id, school_email, status)
                            VALUES (:user_id, :school_email, 'code_requested')"
                        );
                    $statement->execute(['user_id' => $user['id'], 'school_email' => $schoolEmail]);
                    $pdo->commit();
                    if (is_array($existingVerification)) {
                        delete_student_id_photo($existingVerification['id_image_path']);
                    }
                    $schoolIdNumber = '';
                    $profileSuccess = 'Your code request was submitted. Staff will send the code to your school email. After you receive it, enter the code and submit it with your school ID details.';
                }
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($exception->getCode() === '23000') {
                    $profileErrors[] = 'That school email is already associated with another account.';
                } else {
                    error_log('Verification code request failed: ' . $exception->getMessage());
                    http_response_code(500);
                    $profileErrors[] = 'Your code request could not be submitted. Please try again later.';
                }
            }
        }
    } elseif ($action === 'submit-verification' && $user['role'] === 'student') {
        $codeInput = $_POST['verification_code'] ?? '';
        $code = is_string($codeInput) ? trim($codeInput) : '';
        $schoolIdInput = $_POST['school_id_number'] ?? '';
        $schoolIdNumber = is_string($schoolIdInput) ? trim($schoolIdInput) : '';
        if ($schoolIdNumber === '' || preg_match('/\A[\P{Cc}]{1,100}\z/u', $schoolIdNumber) !== 1) {
            $profileErrors[] = 'Enter your school ID number using no more than 100 characters.';
        }
        if (preg_match('/\A[0-9]{8}\z/', $code) !== 1) {
            $profileErrors[] = 'Enter the 8-digit code sent to your school email.';
        }
        $idPhoto = ['path' => null, 'error' => null];
        if ($profileErrors === []) {
            $idPhoto = validate_student_id_upload($_FILES['school_id_photo'] ?? []);
            if ($idPhoto['error'] !== null) {
                $profileErrors[] = $idPhoto['error'];
            }
        }
        try {
            if ($profileErrors === [] && $idPhoto['path'] !== null) {
                $pdo->beginTransaction();
                $verificationStatement = $pdo->prepare(
                    'SELECT status, code_hash, code_expires_at, code_attempts FROM student_verifications WHERE user_id = :user_id FOR UPDATE'
                );
                $verificationStatement->execute(['user_id' => $user['id']]);
                $verification = $verificationStatement->fetch();

                if (
                    !is_array($verification)
                    || $verification['status'] !== 'code_sent'
                    || !is_string($verification['code_hash'])
                    || !is_string($verification['code_expires_at'])
                    || strtotime($verification['code_expires_at']) < time()
                    || (int) $verification['code_attempts'] >= 5
                ) {
                    $pdo->rollBack();
                    delete_student_id_photo($idPhoto['path']);
                    $profileErrors[] = 'There is no active verification code. Request a code or ask staff to send a new one.';
                } elseif (!password_verify($code, $verification['code_hash'])) {
                    $attempts = (int) $verification['code_attempts'] + 1;
                    $updateAttempts = $pdo->prepare(
                        'UPDATE student_verifications SET code_attempts = :attempts WHERE user_id = :user_id'
                    );
                    $updateAttempts->execute(['attempts' => $attempts, 'user_id' => $user['id']]);
                    $pdo->commit();
                    delete_student_id_photo($idPhoto['path']);
                    $profileErrors[] = $attempts >= 5
                        ? 'Too many incorrect codes. Request a new code.'
                        : 'That code is incorrect. Please check your school email and try again.';
                } else {
                    $complete = $pdo->prepare(
                        "UPDATE student_verifications
                        SET school_id_number = :school_id_number, id_image_path = :id_image_path,
                            status = 'code_verified', code_hash = NULL, code_expires_at = NULL,
                            code_attempts = 0, reviewed_by = NULL, reviewed_at = NULL
                        WHERE user_id = :user_id AND status = 'code_sent'"
                    );
                    $complete->execute([
                        'school_id_number' => $schoolIdNumber,
                        'id_image_path' => $idPhoto['path'],
                        'user_id' => $user['id'],
                    ]);
                    $pdo->commit();
                    $profileSuccess = 'Your code and school ID were submitted. Staff will review your ID before your account is verified.';
                }
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($idPhoto['path'] !== null) {
                delete_student_id_photo($idPhoto['path']);
            }
            error_log('Student verification submission failed: ' . $exception->getMessage());
            http_response_code(500);
            $profileErrors[] = 'Your verification submission could not be saved. Please try again later.';
        }
    } elseif ($action === 'change-password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!is_string($currentPassword) || $currentPassword === '') {
            $passwordErrors[] = 'Enter your current password.';
        }

        if (!is_string($newPassword) || strlen($newPassword) < 8 || strlen($newPassword) > 72) {
            $passwordErrors[] = 'Your new password must be between 8 and 72 bytes.';
        }

        if (!is_string($confirmPassword) || !is_string($newPassword) || !hash_equals($newPassword, $confirmPassword)) {
            $passwordErrors[] = 'The new password confirmation does not match.';
        }

        if ($passwordErrors === []) {
            try {
                $passwordStatement = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
                $passwordStatement->execute(['id' => $user['id']]);
                $passwordHash = $passwordStatement->fetchColumn();

                if (!is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)) {
                    $passwordErrors[] = 'The current password is incorrect.';
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updatePassword = $pdo->prepare('UPDATE users SET password_hash = :password_hash, must_change_password = 0 WHERE id = :id');
                    $updatePassword->execute([
                        'password_hash' => $newHash,
                        'id' => $user['id'],
                    ]);
                    $_SESSION['user']['must_change_password'] = false;
                    $passwordSuccess = 'Your password has been changed.';
                }
            } catch (PDOException $exception) {
                error_log('Password change failed: ' . $exception->getMessage());
                http_response_code(500);
                $passwordErrors[] = 'Your password could not be changed right now. Please try again later.';
            }
        }
    } else {
        http_response_code(400);
        exit('Choose a valid profile action.');
    }
}

if ($user['role'] === 'student') {
    $verificationStatement = $pdo->prepare(
        'SELECT school_email, school_id_number, id_image_path, status, code_expires_at, code_attempts FROM student_verifications WHERE user_id = :user_id LIMIT 1'
    );
    $verificationStatement->execute(['user_id' => $user['id']]);
    $verification = $verificationStatement->fetch() ?: null;
    if (is_array($verification)) {
        $schoolEmail = $verification['school_email'];
        if ($schoolIdNumber === '') {
            $schoolIdNumber = $verification['school_id_number'];
        }
    }
}

$pageTitle = 'Profile';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="profile-page mx-auto max-w-3xl" aria-labelledby="profile-title">
    <h1 id="profile-title" class="text-3xl font-bold tracking-tight">Profile</h1>
    <p class="mt-2 text-slate-700">Update your account details, verify your student status, and manage your password.</p>
    <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="account-title">
        <h2 id="account-title" class="text-xl font-bold">Account details</h2>
        <div class="profile-photo-panel">
            <?php $avatarSrc = profile_avatar_src($avatarPath); ?>
            <form class="profile-photo-form" method="post" action="profile.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save-avatar">
                <label class="profile-avatar-trigger">
                    <?php if ($avatarSrc !== null): ?>
                        <img class="profile-avatar profile-avatar-large" src="<?= e($avatarSrc) ?>" alt="">
                    <?php else: ?>
                        <span class="profile-avatar profile-avatar-large profile-avatar-placeholder" aria-hidden="true"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span>
                    <?php endif; ?>
                    <span class="profile-avatar-camera" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 7.5h3l1.5-2h7l1.5 2h3v11H4v-11Z" />
                            <circle cx="12" cy="13" r="3.25" />
                        </svg>
                    </span>
                    <span class="sr-only">Choose a profile photo</span>
                    <input class="profile-avatar-file" id="avatar" name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                </label>
                <div class="profile-photo-controls">
                    <button class="secondary-button" type="submit">Upload photo</button>
                </div>
            </form>
                <?php if ($avatarSrc !== null): ?>
                    <form class="mt-2" method="post" action="profile.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove-avatar">
                        <button class="ghost-button" type="submit">Remove photo</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($profileSuccess !== null): ?>
            <p class="mt-4 rounded-lg border border-green-300 bg-green-50 p-4 text-green-900" role="status"><?= e($profileSuccess) ?></p>
        <?php endif; ?>
        <?php if ($profileErrors !== []): ?>
            <div class="mt-4 rounded-lg border border-red-300 bg-red-50 p-4 text-red-900" role="alert">
                <ul class="list-inside list-disc">
                    <?php foreach ($profileErrors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form class="mt-5 space-y-5" method="post" action="profile.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update-profile">
            <div>
                <label class="mb-1 block font-medium" for="name">Name</label>
                <input class="min-h-11 w-full rounded-md border border-slate-400 px-3 focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-blue-700" id="name" name="name" type="text" maxlength="150" autocomplete="name" value="<?= e($profileName) ?>" required>
            </div>
            <?php if ($user['role'] === 'staff'): ?>
                <div>
                    <label class="mb-1 block font-medium" for="email">Email</label>
                    <input class="min-h-11 w-full rounded-md border border-slate-400 px-3 focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-blue-700" id="email" name="email" type="email" maxlength="191" autocomplete="email" value="<?= e($profileEmail) ?>" required>
                </div>
            <?php else: ?>
                <p class="text-sm text-slate-700"><strong>Sign-up email:</strong> <?= e($user['email'] ?? '') ?> <span>(cannot be changed here)</span></p>
            <?php endif; ?>
            <button class="min-h-11 rounded-lg bg-blue-800 px-5 py-3 font-semibold text-white hover:bg-blue-900 focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-blue-700" type="submit">Save account details</button>
        </form>
    </section>
    <?php if ($user['role'] === 'student'): ?>
        <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="student-verification-title">
            <h2 id="student-verification-title" class="text-xl font-bold">Student verification</h2>
            <p class="mt-2 text-slate-700">First request a code using your school email. After staff sends it, enter the code and submit your school ID number and photo together. Your ID photo is private and visible only to staff.</p>
            <?php if ($verification === null || $verification['status'] === 'rejected'): ?>
                <?php if ($verification !== null): ?><p class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-950">Your previous request was not approved. Please review your details and submit again.</p><?php endif; ?>
                <form class="mt-5 space-y-5" method="post" action="profile.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="request-verification-code">
                    <div>
                        <label class="mb-1 block font-medium" for="school_email">School email</label>
                        <input class="min-h-11 w-full rounded-md border border-slate-400 px-3" id="school_email" name="school_email" type="email" maxlength="191" autocomplete="email" value="<?= e($schoolEmail) ?>" required>
                    </div>
                    <button class="min-h-11 rounded-lg bg-blue-800 px-5 py-3 font-semibold text-white" type="submit">Request code</button>
                </form>
                <fieldset class="mt-5 space-y-5" disabled>
                    <div>
                        <label class="mb-1 block font-medium" for="verification_code_waiting">8-digit verification code</label>
                        <input class="min-h-14 w-full rounded-md border border-slate-300 bg-slate-100 px-4 text-center font-mono text-2xl font-bold tracking-[0.4em] text-slate-500" id="verification_code_waiting" type="text" placeholder="Enter after staff sends it">
                    </div>
                    <div>
                        <label class="mb-1 block font-medium" for="school_id_number_waiting">School ID number</label>
                        <input class="min-h-11 w-full rounded-md border border-slate-300 bg-slate-100 px-3" id="school_id_number_waiting" type="text" placeholder="Available after code is entered">
                    </div>
                    <div>
                        <label class="mb-1 block font-medium" for="school_id_photo_waiting">School ID photo</label>
                        <input class="min-h-11 w-full rounded-md border border-slate-300 bg-slate-100 px-3" id="school_id_photo_waiting" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <span class="text-sm text-slate-600">JPG, PNG, or WebP; maximum 15 MB.</span>
                    </div>
                    <button class="min-h-11 rounded-lg bg-blue-800 px-5 py-3 font-semibold text-white opacity-50" type="button" disabled>Submit code and school ID</button>
                </fieldset>
            <?php elseif ($verification['status'] === 'pending'): ?>
                <p class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-950" role="status">Your school ID submission is waiting for staff review.</p>
            <?php elseif ($verification['status'] === 'code_requested'): ?>
                <p class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-950" role="status">Your code request is waiting for staff. They will send the code to <?= e($verification['school_email']) ?>. After receiving it, return here to submit the code and your school ID details.</p>
                <fieldset class="mt-5 space-y-5" disabled>
                    <div>
                        <label class="mb-1 block font-medium" for="verification_code_waiting">8-digit verification code</label>
                        <input class="min-h-14 w-full rounded-md border border-slate-300 bg-slate-100 px-4 text-center font-mono text-2xl font-bold tracking-[0.4em] text-slate-500" id="verification_code_waiting" type="text" placeholder="Enter after staff sends it">
                    </div>
                    <div>
                        <label class="mb-1 block font-medium" for="school_id_number_waiting">School ID number</label>
                        <input class="min-h-11 w-full rounded-md border border-slate-300 bg-slate-100 px-3" id="school_id_number_waiting" type="text" placeholder="Available after code is entered">
                    </div>
                    <div>
                        <label class="mb-1 block font-medium" for="school_id_photo_waiting">School ID photo</label>
                        <input class="min-h-11 w-full rounded-md border border-slate-300 bg-slate-100 px-3" id="school_id_photo_waiting" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <span class="text-sm text-slate-600">JPG, PNG, or WebP; maximum 15 MB.</span>
                    </div>
                    <button class="min-h-11 rounded-lg bg-blue-800 px-5 py-3 font-semibold text-white opacity-50" type="button" disabled>Submit code and school ID</button>
                </fieldset>
            <?php elseif ($verification['status'] === 'code_sent'): ?>
                <p class="mt-4 rounded-lg border border-blue-300 bg-blue-50 p-4 text-blue-950">Enter the code sent to <?= e($verification['school_email']) ?>, then submit it with your school ID number and photo. Staff will review your ID before verifying your account. The code expires 24 hours after it was issued.</p>
                <?php if ((int) $verification['code_attempts'] < 5 && strtotime($verification['code_expires_at']) >= time()): ?>
                    <form class="mt-5 space-y-5" method="post" action="profile.php" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="submit-verification">
                        <p><strong>School email:</strong> <?= e($verification['school_email']) ?></p>
                        <div>
                            <label class="mb-1 block font-medium" for="verification_code">8-digit verification code</label>
                            <input class="min-h-14 w-full rounded-md border-2 border-blue-700 px-4 text-center font-mono text-2xl font-bold tracking-[0.4em] focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-blue-700" id="verification_code" name="verification_code" type="text" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" autocomplete="one-time-code" aria-describedby="verification-code-help verification-fields-help" placeholder="00000000" required>
                            <span id="verification-code-help" class="block text-sm text-slate-600">Enter the 8-digit code from your school email.</span>
                        </div>
                        <p id="verification-fields-help" class="text-sm text-slate-600" aria-live="polite">Enter all 8 code digits to unlock the school ID fields.</p>
                        <fieldset id="school-id-fields" class="space-y-5" disabled>
                            <div>
                                <label class="mb-1 block font-medium" for="school_id_number">School ID number</label>
                                <input class="min-h-11 w-full rounded-md border border-slate-400 px-3" id="school_id_number" name="school_id_number" type="text" maxlength="100" value="<?= e($schoolIdNumber) ?>" required>
                            </div>
                            <div>
                                <label class="mb-1 block font-medium" for="school_id_photo">School ID photo</label>
                                <input class="min-h-11 w-full rounded-md border border-slate-400 px-3" id="school_id_photo" name="school_id_photo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                                <span class="text-sm text-slate-600">JPG, PNG, or WebP; maximum 15 MB.</span>
                            </div>
                        </fieldset>
                        <button class="min-h-11 rounded-lg bg-blue-800 px-5 py-3 font-semibold text-white" type="submit">Submit code and school ID</button>
                    </form>
                <?php else: ?>
                    <p class="mt-3 text-sm text-slate-700">This code expired or reached its attempt limit. Request another code to continue.</p>
                    <form class="mt-4" method="post" action="profile.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="request-verification-code">
                        <input type="hidden" name="school_email" value="<?= e($verification['school_email']) ?>">
                        <button class="secondary-button" type="submit">Request another code</button>
                    </form>
                <?php endif; ?>
            <?php elseif ($verification['status'] === 'code_verified'): ?>
                <p class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-950" role="status">Your code and school ID were submitted. Staff will review your ID before your account is verified.</p>
            <?php else: ?>
                <p class="mt-4 rounded-lg border border-green-300 bg-green-50 p-4 text-green-900" role="status">Your student account is verified. You can post lost items and claim found items.</p>
            <?php endif; ?>
        </section>
        <?php if ($verification !== null && $verification['status'] === 'code_sent' && (int) $verification['code_attempts'] < 5 && strtotime($verification['code_expires_at']) >= time()): ?>
            <script>
                const verificationCodeInput = document.getElementById('verification_code');
                const schoolIdFields = document.getElementById('school-id-fields');
                const verificationFieldsHelp = document.getElementById('verification-fields-help');
                const updateVerificationFields = () => {
                    const codeComplete = /^[0-9]{8}$/.test(verificationCodeInput.value);
                    schoolIdFields.disabled = !codeComplete;
                    verificationFieldsHelp.textContent = codeComplete
                        ? 'Code entered. Complete your school ID details, then submit them together.'
                        : 'Enter all 8 code digits to unlock the school ID fields.';
                };
                verificationCodeInput.addEventListener('input', updateVerificationFields);
                updateVerificationFields();
            </script>
        <?php endif; ?>
    <?php endif; ?>
    <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="password-title">
        <h2 id="password-title" class="text-xl font-bold">Change password</h2>
        <?php if ($passwordSuccess !== null): ?>
            <p class="mt-4 rounded-lg border border-green-300 bg-green-50 p-4 text-green-900" role="status"><?= e($passwordSuccess) ?></p>
        <?php endif; ?>
        <?php if ($passwordErrors !== []): ?>
            <div class="mt-4 rounded-lg border border-red-300 bg-red-50 p-4 text-red-900" role="alert">
                <ul class="list-inside list-disc">
                    <?php foreach ($passwordErrors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form class="mt-5 space-y-5" method="post" action="profile.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change-password">
            <div>
                <label class="mb-1 block font-medium" for="current_password">Current password</label>
                <input class="min-h-11 w-full rounded-md border border-slate-400 px-3 focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-blue-700" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
            </div>
            <div>
                <label class="mb-1 block font-medium" for="new_password">New password</label>
                <input class="min-h-11 w-full rounded-md border border-slate-400 px-3 focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-blue-700" id="new_password" name="new_password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
            </div>
            <div>
                <label class="mb-1 block font-medium" for="confirm_password">Confirm new password</label>
                <input class="min-h-11 w-full rounded-md border border-slate-400 px-3 focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-blue-700" id="confirm_password" name="confirm_password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
            </div>
            <button class="min-h-11 rounded-lg bg-blue-800 px-5 py-3 font-semibold text-white hover:bg-blue-900 focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-blue-700" type="submit">Change password</button>
        </form>
    </section>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
