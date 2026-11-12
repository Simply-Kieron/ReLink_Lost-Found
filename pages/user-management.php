<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/student_verification.php';
require_once __DIR__ . '/../includes/item_uploads.php';
require_once __DIR__ . '/../includes/profile_avatar.php';

$staff = require_role('staff');
require_once __DIR__ . '/../includes/db.php';

$errors = [];
$notice = null;
$generatedCode = null;
$codeRetrieved = false;
$temporaryPassword = null;
$codeSchoolEmail = null;
$query = $_GET['q'] ?? '';
$query = is_string($query) ? trim(substr($query, 0, 100)) : '';
$roleFilter = $_GET['role'] ?? 'all';
$roleFilter = is_string($roleFilter) && in_array($roleFilter, ['all', 'student', 'staff'], true) ? $roleFilter : 'all';
$verificationFilter = $_GET['verification'] ?? 'all';
$verificationOptions = ['all', 'not_submitted', 'pending', 'code_requested', 'code_sent', 'code_verified', 'verified', 'rejected'];
$verificationFilter = is_string($verificationFilter) && in_array($verificationFilter, $verificationOptions, true) ? $verificationFilter : 'all';
$accountFilter = $_GET['account'] ?? 'all';
$accountFilter = is_string($accountFilter) && in_array($accountFilter, ['all', 'active', 'suspended'], true) ? $accountFilter : 'all';
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$perPage = 25;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $targetId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = $_POST['action'] ?? '';
    $returnQuery = $_POST['return_query'] ?? '';
    $returnRole = $_POST['return_role'] ?? 'all';
    $returnVerification = $_POST['return_verification'] ?? 'all';
    $returnAccount = $_POST['return_account'] ?? 'all';
    $returnPage = $_POST['return_page'] ?? '1';

    if (
        $targetId === false
        || $targetId === null
        || !is_string($action)
        || !in_array($action, ['approve', 'reject', 'view_code', 'suspend', 'activate', 'revoke', 'reset_password', 'delete'], true)
        || !is_string($returnQuery)
        || !is_string($returnRole)
        || !is_string($returnVerification)
        || !is_string($returnAccount)
        || !is_string($returnPage)
    ) {
        http_response_code(400);
        exit('Choose a valid user action.');
    }

    $query = trim(substr($returnQuery, 0, 100));
    $roleFilter = in_array($returnRole, ['all', 'student', 'staff'], true) ? $returnRole : 'all';
    $verificationFilter = in_array($returnVerification, $verificationOptions, true) ? $returnVerification : 'all';
    $accountFilter = in_array($returnAccount, ['all', 'active', 'suspended'], true) ? $returnAccount : 'all';
    $page = ctype_digit($returnPage) && (int) $returnPage > 0 ? (int) $returnPage : 1;

    try {
        $pdo->beginTransaction();
        $targetStatement = $pdo->prepare(
            'SELECT id, name, email, role, account_status, avatar_path FROM users WHERE id = :id FOR UPDATE'
        );
        $targetStatement->execute(['id' => $targetId]);
        $target = $targetStatement->fetch();

        if (!is_array($target)) {
            throw new RuntimeException('The selected user was not found.');
        }

        if ($target['role'] !== 'student') {
            throw new RuntimeException('Staff can only use these account controls for student accounts.');
        }
        if ($targetId === $staff['id']) {
            throw new RuntimeException('You cannot apply this action to your own account.');
        }

        if (in_array($action, ['approve', 'reject'], true)) {
            $verificationStatement = $pdo->prepare(
                'SELECT id, status, school_email FROM student_verifications WHERE user_id = :user_id FOR UPDATE'
            );
            $verificationStatement->execute(['user_id' => $targetId]);
            $verification = $verificationStatement->fetch();
            if (!is_array($verification) || !in_array($verification['status'], ['pending', 'code_requested', 'code_sent', 'code_verified'], true)) {
                throw new RuntimeException('This student does not have a request awaiting review.');
            }

            if ($action === 'approve') {
                if ($verification['status'] === 'code_verified') {
                    $update = $pdo->prepare(
                        "UPDATE student_verifications
                        SET status = 'verified', reviewed_by = :reviewed_by, reviewed_at = CURRENT_TIMESTAMP,
                            verified_at = CURRENT_TIMESTAMP
                        WHERE id = :id AND status = 'code_verified'"
                    );
                    $update->execute(['reviewed_by' => $staff['id'], 'id' => $verification['id']]);
                    $notice = 'The school ID was approved and the student account is verified.';
                } else {
                    $encryptionKey = student_verification_code_encryption_key($config);
                    $generatedCode = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
                    $codeSchoolEmail = $verification['school_email'];
                    $codeCiphertext = encrypt_student_verification_code($generatedCode, $encryptionKey, (int) $targetId);
                    $update = $pdo->prepare(
                        "UPDATE student_verifications
                        SET status = 'code_sent', code_hash = :code_hash, code_ciphertext = :code_ciphertext,
                            code_expires_at = :expires_at,
                            code_attempts = 0, reviewed_by = :reviewed_by, reviewed_at = CURRENT_TIMESTAMP
                        WHERE id = :id"
                    );
                    $update->execute([
                        'code_hash' => password_hash($generatedCode, PASSWORD_DEFAULT),
                        'code_ciphertext' => $codeCiphertext,
                        'expires_at' => date('Y-m-d H:i:s', time() + 86400),
                        'reviewed_by' => $staff['id'],
                        'id' => $verification['id'],
                    ]);
                    $notice = 'The code was issued. Send it manually to the school email shown below; you can retrieve this same active code later.';
                }
            } else {
                $update = $pdo->prepare(
                    "UPDATE student_verifications
                    SET status = 'rejected', code_hash = NULL, code_ciphertext = NULL,
                        code_expires_at = NULL, code_attempts = 0,
                        reviewed_by = :reviewed_by, reviewed_at = CURRENT_TIMESTAMP
                    WHERE id = :id"
                );
                $update->execute(['reviewed_by' => $staff['id'], 'id' => $verification['id']]);
                $notice = 'The student verification request was rejected.';
            }
        } elseif ($action === 'view_code') {
            $verificationStatement = $pdo->prepare(
                'SELECT school_email, status, code_ciphertext, code_expires_at, code_attempts
                FROM student_verifications WHERE user_id = :user_id FOR UPDATE'
            );
            $verificationStatement->execute(['user_id' => $targetId]);
            $verification = $verificationStatement->fetch();
            if (
                !is_array($verification)
                || $verification['status'] !== 'code_sent'
                || !is_string($verification['code_ciphertext'])
                || !is_string($verification['code_expires_at'])
                || strtotime($verification['code_expires_at']) < time()
                || (int) $verification['code_attempts'] >= 5
            ) {
                throw new RuntimeException('There is no active code to retrieve. Issue a replacement only if the student needs a new code.');
            }

            $generatedCode = decrypt_student_verification_code(
                $verification['code_ciphertext'],
                student_verification_code_encryption_key($config),
                (int) $targetId
            );
            $codeSchoolEmail = $verification['school_email'];
            $codeRetrieved = true;
            $notice = 'The current active code was retrieved. No replacement was issued.';
        } elseif ($action === 'suspend' || $action === 'activate') {
            $newStatus = $action === 'suspend' ? 'suspended' : 'active';
            $update = $pdo->prepare('UPDATE users SET account_status = :status WHERE id = :id');
            $update->execute(['status' => $newStatus, 'id' => $targetId]);
            $notice = $newStatus === 'suspended' ? 'The student account was suspended.' : 'The account was reactivated.';
        } elseif ($action === 'revoke') {
            $update = $pdo->prepare(
                "UPDATE student_verifications
                SET status = 'rejected', code_hash = NULL, code_ciphertext = NULL,
                    code_expires_at = NULL, code_attempts = 0,
                    reviewed_by = :reviewed_by, reviewed_at = CURRENT_TIMESTAMP, verified_at = NULL
                WHERE user_id = :user_id AND status = 'verified'"
            );
            $update->execute(['reviewed_by' => $staff['id'], 'user_id' => $targetId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('This student does not currently have verified status.');
            }
            $notice = 'Student verification was revoked. The student must submit verification again.';
        } elseif ($action === 'reset_password') {
            $temporaryPassword = bin2hex(random_bytes(12));
            $update = $pdo->prepare(
                'UPDATE users SET password_hash = :password_hash, must_change_password = 1 WHERE id = :id'
            );
            $update->execute([
                'password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
                'id' => $targetId,
            ]);
            $notice = 'A temporary password was issued. Give it to the student through a trusted channel; it will be shown once.';
        } elseif ($action === 'delete') {
            if (($_POST['confirm_delete'] ?? '') !== '1') {
                throw new RuntimeException('Confirm the permanent account deletion before continuing.');
            }

            $itemImagesStatement = $pdo->prepare('SELECT image_path FROM items WHERE reported_by = :user_id');
            $itemImagesStatement->execute(['user_id' => $targetId]);
            $itemImages = $itemImagesStatement->fetchAll(PDO::FETCH_COLUMN);
            $verificationImageStatement = $pdo->prepare(
                'SELECT id_image_path FROM student_verifications WHERE user_id = :user_id'
            );
            $verificationImageStatement->execute(['user_id' => $targetId]);
            $verificationImage = $verificationImageStatement->fetchColumn();
            $avatarPath = $target['avatar_path'];

            $deleteClaims = $pdo->prepare('DELETE FROM claims WHERE claimant_id = :user_id');
            $deleteClaims->execute(['user_id' => $targetId]);
            $deleteItems = $pdo->prepare('DELETE FROM items WHERE reported_by = :user_id');
            $deleteItems->execute(['user_id' => $targetId]);
            $deleteUser = $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'student'");
            $deleteUser->execute(['id' => $targetId]);
            if ($deleteUser->rowCount() !== 1) {
                throw new RuntimeException('The student account could not be deleted.');
            }
            $pdo->commit();

            foreach ($itemImages as $imagePath) {
                delete_item_image(is_string($imagePath) ? $imagePath : null);
            }
            delete_student_id_photo(is_string($verificationImage) ? $verificationImage : null);
            delete_profile_avatar(is_string($avatarPath) ? $avatarPath : null);
            $notice = 'The student account and its associated reports, claims, verification data, and uploaded images were deleted.';
        }

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $generatedCode = null;
        $temporaryPassword = null;
        if ($exception instanceof PDOException) {
            error_log('User management action failed: ' . $exception->getMessage());
            http_response_code(500);
            $errors[] = 'The account action could not be completed. Please try again.';
        } else {
            $errors[] = $exception->getMessage();
        }
    }
}

$where = [];
$parameters = [];
if ($roleFilter !== 'all') {
    $where[] = 'users.role = :role';
    $parameters['role'] = $roleFilter;
}
if ($verificationFilter !== 'all') {
    if ($verificationFilter === 'not_submitted') {
        $where[] = "users.role = 'student' AND verifications.id IS NULL";
    } else {
        $where[] = 'verifications.status = :verification_status';
        $parameters['verification_status'] = $verificationFilter;
    }
}
if ($accountFilter !== 'all') {
    $where[] = 'users.account_status = :account_status';
    $parameters['account_status'] = $accountFilter;
}
if ($query !== '') {
    $where[] = '(users.name LIKE :query_name OR users.email LIKE :query_email OR verifications.school_email LIKE :query_school_email)';
    $search = '%' . $query . '%';
    $parameters['query_name'] = $search;
    $parameters['query_email'] = $search;
    $parameters['query_school_email'] = $search;
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

$countStatement = $pdo->prepare(
    'SELECT COUNT(*) FROM users LEFT JOIN student_verifications AS verifications ON verifications.user_id = users.id' . $whereSql
);
$countStatement->execute($parameters);
$totalUsers = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalUsers / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$listStatement = $pdo->prepare(
    "SELECT users.id AS user_id, users.name, users.email, users.role, users.account_status,
        users.must_change_password, users.avatar_path, verifications.id AS verification_id, verifications.school_email,
        verifications.school_id_number, verifications.id_image_path, verifications.code_attempts,
        verifications.code_ciphertext IS NOT NULL AS has_recoverable_code,
        CASE WHEN users.role = 'student' THEN COALESCE(verifications.status, 'not_submitted') ELSE 'not_applicable' END AS verification_status,
        verifications.submitted_at, verifications.code_expires_at
    FROM users
    LEFT JOIN student_verifications AS verifications ON verifications.user_id = users.id"
    . $whereSql .
    " ORDER BY FIELD(users.role, 'student', 'staff'), users.name ASC
    LIMIT :limit OFFSET :offset"
);
foreach ($parameters as $key => $value) {
    $listStatement->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$listStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStatement->execute();
$users = $listStatement->fetchAll();

$pageTitle = 'User Management';
header('Cache-Control: no-store');
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-shell user-management-page">
    <div class="surface-card user-management-panel">
        <p class="eyebrow">Staff tools</p>
        <h1>User management</h1>
        <p>Search and filter accounts, review student verification, and manage student access.</p>

        <?php if ($notice !== null): ?>
            <p class="mt-4 rounded-lg border border-green-300 bg-green-50 p-4 text-green-900" role="status"><?= e($notice) ?></p>
        <?php endif; ?>
        <?php if ($generatedCode !== null): ?>
            <div class="mt-4 rounded-lg border border-blue-300 bg-blue-50 p-4 text-blue-950" role="status">
                <strong><?= $codeRetrieved ? 'Current verification code for ' : 'Verification code for ' ?><?= e($codeSchoolEmail) ?>:</strong>
                <p class="my-2 font-mono text-2xl font-bold tracking-widest"><?= e($generatedCode) ?></p>
                <p><?= $codeRetrieved ? 'This is the existing active code. Viewing it does not replace it.' : 'Send this code manually to the school email. It expires in 24 hours; you can retrieve this same code later from User Management.' ?></p>
            </div>
        <?php endif; ?>
        <?php if ($temporaryPassword !== null): ?>
            <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-950" role="status">
                <strong>Temporary password — display once</strong>
                <p class="my-2 break-all font-mono text-xl font-bold"><?= e($temporaryPassword) ?></p>
                <p>The student must change this password in Profile before accessing other pages. Deliver it through a trusted channel.</p>
            </div>
        <?php endif; ?>
        <?php if ($errors !== []): ?>
            <div class="error-box mt-4" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form class="mt-5 grid gap-3 rounded-lg border border-slate-200 p-4 md:grid-cols-5" method="get" action="user-management.php">
            <div>
                <label for="q">Search</label>
                <input class="min-h-11 w-full rounded-md border border-slate-400 px-3" id="q" name="q" type="search" maxlength="100" value="<?= e($query) ?>" placeholder="Name or email">
            </div>
            <div>
                <label for="role">Role</label>
                <select class="min-h-11 w-full rounded-md border border-slate-400 px-3" id="role" name="role">
                    <?php foreach (['all' => 'All roles', 'student' => 'Students', 'staff' => 'Staff'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $roleFilter === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="verification">Verification</label>
                <select class="min-h-11 w-full rounded-md border border-slate-400 px-3" id="verification" name="verification">
                    <?php foreach (['all' => 'All statuses', 'not_submitted' => 'Not submitted', 'pending' => 'Pending ID review', 'code_requested' => 'Code requested', 'code_sent' => 'Code sent — awaiting student', 'code_verified' => 'Code matched — ID review', 'verified' => 'Verified', 'rejected' => 'Rejected'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $verificationFilter === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="account">Account</label>
                <select class="min-h-11 w-full rounded-md border border-slate-400 px-3" id="account" name="account">
                    <?php foreach (['all' => 'All account states', 'active' => 'Active', 'suspended' => 'Suspended'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $accountFilter === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end"><button class="primary-button" type="submit">Filter users</button></div>
        </form>

        <p class="mt-4 text-sm text-slate-600">Showing <?= e(count($users)) ?> of <?= e($totalUsers) ?> users · Page <?= e($page) ?> of <?= e($totalPages) ?></p>
        <?php if ($users === []): ?>
            <p class="empty-state mt-4">No users match these filters.</p>
        <?php else: ?>
            <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
                <table class="w-full min-w-[1050px] border-collapse text-left text-sm">
                    <thead class="bg-slate-100">
                        <tr>
                            <th class="p-3">User</th>
                            <th class="p-3">Role</th>
                            <th class="p-3">Student verification</th>
                            <th class="p-3">Account</th>
                            <th class="p-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php foreach ($users as $listedUser): ?>
                            <?php $verificationStatus = $listedUser['verification_status']; ?>
                            <?php $verificationStatusLabel = [
                                'not_submitted' => 'Not submitted',
                                'pending' => 'Pending ID review',
                                'code_requested' => 'Code requested',
                                'code_sent' => 'Code sent — awaiting student',
                                'code_verified' => 'Code matched — ID review pending',
                                'verified' => 'Verified',
                                'rejected' => 'Rejected',
                                'not_applicable' => 'Not applicable',
                            ][$verificationStatus] ?? str_replace('_', ' ', $verificationStatus); ?>
                            <tr>
                                <td class="p-3 align-top">
                                    <div class="user-management-identity">
                                        <?php $listedAvatar = profile_avatar_src($listedUser['avatar_path']); ?>
                                        <?php if ($listedAvatar !== null): ?><img class="profile-avatar profile-avatar-list" src="<?= e($listedAvatar) ?>" alt=""><?php endif; ?>
                                        <strong><?= e($listedUser['name']) ?></strong>
                                    </div>
                                    <div><?= e($listedUser['email']) ?></div>
                                    <?php if ($listedUser['school_email'] !== null && strcasecmp((string) $listedUser['school_email'], (string) $listedUser['email']) !== 0): ?><div class="text-slate-600"><?= e($listedUser['school_email']) ?></div><?php endif; ?>
                                </td>
                                <td class="p-3 align-top capitalize"><?= e($listedUser['role']) ?></td>
                                <td class="p-3 align-top">
                                    <span class="status-pill <?= $verificationStatus === 'verified' ? 'status-approved' : ($verificationStatus === 'rejected' || $verificationStatus === 'not_submitted' ? 'status-rejected' : ($verificationStatus === 'not_applicable' ? 'status-open' : 'status-pending')) ?>"><?= e($verificationStatusLabel) ?></span>
                                    <?php if ($verificationStatus === 'code_sent'): ?>
                                        <div class="mt-1 text-slate-600">Waiting for the student to submit the code with their school ID.</div>
                                    <?php elseif ($verificationStatus === 'code_verified'): ?>
                                        <div class="mt-1 text-slate-600">The student entered the issued code correctly. Review their school ID to complete verification.</div>
                                    <?php endif; ?>
                                    <?php if ($verificationStatus === 'code_sent'): ?>
                                        <?php
                                        $codeIsActive = is_string($listedUser['code_expires_at'])
                                            && strtotime($listedUser['code_expires_at']) >= time()
                                            && (int) $listedUser['code_attempts'] < 5;
                                        ?>
                                        <?php if ($codeIsActive && (int) $listedUser['has_recoverable_code'] === 1): ?>
                                            <form class="mt-2" method="post" action="user-management.php">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= e($listedUser['user_id']) ?>">
                                                <input type="hidden" name="action" value="view_code">
                                                <input type="hidden" name="return_query" value="<?= e($query) ?>"><input type="hidden" name="return_role" value="<?= e($roleFilter) ?>"><input type="hidden" name="return_verification" value="<?= e($verificationFilter) ?>"><input type="hidden" name="return_account" value="<?= e($accountFilter) ?>"><input type="hidden" name="return_page" value="<?= e($page) ?>">
                                                <button class="secondary-button" type="submit">View current code</button>
                                            </form>
                                        <?php elseif ($codeIsActive): ?>
                                            <div class="mt-1 text-amber-800">This code was issued before code recovery was enabled and cannot be retrieved. It remains valid; issue a replacement only if the student needs one.</div>
                                        <?php else: ?>
                                            <div class="mt-1 text-amber-800">This code has expired or reached its attempt limit. Issue a replacement only if the student needs one.</div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($listedUser['school_id_number'] !== null): ?><div class="mt-1">ID: <?= e($listedUser['school_id_number']) ?></div><?php endif; ?>
                                    <?php if ($listedUser['verification_id'] !== null && $listedUser['id_image_path'] !== null): ?>
                                        <a class="mt-2 inline-flex items-center gap-1 rounded-md border border-blue-300 px-2 py-1 text-sm font-medium text-blue-800 hover:bg-blue-50 focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-blue-700" href="verification-document.php?id=<?= e($listedUser['verification_id']) ?>" target="_blank" rel="noopener" aria-label="View <?= e($listedUser['name']) ?>'s school ID photo">
                                            <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-7 9.75-7 9.75 7 9.75 7-3.5 7-9.75 7-9.75-7-9.75-7Z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                            <span>View ID photo</span>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 align-top">
                                    <span class="status-pill <?= $listedUser['account_status'] === 'active' ? 'status-approved' : 'status-rejected' ?>"><?= e($listedUser['account_status']) ?></span>
                                    <?php if ((bool) $listedUser['must_change_password']): ?><div class="mt-1">Password change required</div><?php endif; ?>
                                </td>
                                <td class="p-3 align-top">
                                    <details>
                                        <summary class="cursor-pointer font-semibold text-blue-800">Manage account</summary>
                                        <div class="mt-3 grid gap-2">
                                            <?php if ($listedUser['role'] === 'student'): ?>
                                                <?php if (in_array($verificationStatus, ['pending', 'code_requested', 'code_sent', 'code_verified'], true)): ?>
                                                    <form method="post" action="user-management.php">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="user_id" value="<?= e($listedUser['user_id']) ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="return_query" value="<?= e($query) ?>"><input type="hidden" name="return_role" value="<?= e($roleFilter) ?>"><input type="hidden" name="return_verification" value="<?= e($verificationFilter) ?>"><input type="hidden" name="return_account" value="<?= e($accountFilter) ?>"><input type="hidden" name="return_page" value="<?= e($page) ?>">
                                                        <button class="secondary-button" type="submit"><?= $verificationStatus === 'code_verified' ? 'Approve ID and verify' : ($verificationStatus === 'pending' ? 'Approve and issue code' : ($verificationStatus === 'code_requested' ? 'Issue requested code' : 'Issue replacement code')) ?></button>
                                                    </form>
                                                    <form method="post" action="user-management.php">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="user_id" value="<?= e($listedUser['user_id']) ?>"><input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="return_query" value="<?= e($query) ?>"><input type="hidden" name="return_role" value="<?= e($roleFilter) ?>"><input type="hidden" name="return_verification" value="<?= e($verificationFilter) ?>"><input type="hidden" name="return_account" value="<?= e($accountFilter) ?>"><input type="hidden" name="return_page" value="<?= e($page) ?>">
                                                        <button class="ghost-button" type="submit">Reject verification</button>
                                                    </form>
                                                <?php elseif ($verificationStatus === 'verified'): ?>
                                                    <form method="post" action="user-management.php">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="user_id" value="<?= e($listedUser['user_id']) ?>"><input type="hidden" name="action" value="revoke">
                                                        <input type="hidden" name="return_query" value="<?= e($query) ?>"><input type="hidden" name="return_role" value="<?= e($roleFilter) ?>"><input type="hidden" name="return_verification" value="<?= e($verificationFilter) ?>"><input type="hidden" name="return_account" value="<?= e($accountFilter) ?>"><input type="hidden" name="return_page" value="<?= e($page) ?>">
                                                        <button class="ghost-button" type="submit">Revoke verification</button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="post" action="user-management.php">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="user_id" value="<?= e($listedUser['user_id']) ?>"><input type="hidden" name="action" value="reset_password">
                                                    <input type="hidden" name="return_query" value="<?= e($query) ?>"><input type="hidden" name="return_role" value="<?= e($roleFilter) ?>"><input type="hidden" name="return_verification" value="<?= e($verificationFilter) ?>"><input type="hidden" name="return_account" value="<?= e($accountFilter) ?>"><input type="hidden" name="return_page" value="<?= e($page) ?>">
                                                    <button class="secondary-button" type="submit">Issue temporary password</button>
                                                </form>
                                                <form method="post" action="user-management.php">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="user_id" value="<?= e($listedUser['user_id']) ?>"><input type="hidden" name="action" value="<?= $listedUser['account_status'] === 'active' ? 'suspend' : 'activate' ?>">
                                                    <input type="hidden" name="return_query" value="<?= e($query) ?>"><input type="hidden" name="return_role" value="<?= e($roleFilter) ?>"><input type="hidden" name="return_verification" value="<?= e($verificationFilter) ?>"><input type="hidden" name="return_account" value="<?= e($accountFilter) ?>"><input type="hidden" name="return_page" value="<?= e($page) ?>">
                                                    <button class="secondary-button" type="submit"><?= $listedUser['account_status'] === 'active' ? 'Suspend account' : 'Reactivate account' ?></button>
                                                </form>
                                                <form method="post" action="user-management.php">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="user_id" value="<?= e($listedUser['user_id']) ?>"><input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="return_query" value="<?= e($query) ?>"><input type="hidden" name="return_role" value="<?= e($roleFilter) ?>"><input type="hidden" name="return_verification" value="<?= e($verificationFilter) ?>"><input type="hidden" name="return_account" value="<?= e($accountFilter) ?>"><input type="hidden" name="return_page" value="<?= e($page) ?>">
                                                    <label class="flex items-start gap-2 text-sm text-red-900"><input class="mt-1 h-4 w-4 shrink-0" name="confirm_delete" type="checkbox" value="1" required> <span>Permanently delete this account and associated records</span></label>
                                                    <button class="ghost-button mt-2" type="submit">Delete student account</button>
                                                </form>
                                            <?php else: ?>
                                                <p class="text-slate-600">Student-specific controls are not available for staff accounts.</p>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <nav class="mt-4 flex justify-between" aria-label="User list pages">
                    <?php if ($page > 1): ?><a class="secondary-button" href="?<?= e(http_build_query(['q' => $query, 'role' => $roleFilter, 'verification' => $verificationFilter, 'account' => $accountFilter, 'page' => $page - 1])) ?>">Previous</a><?php else: ?><span></span><?php endif; ?>
                    <?php if ($page < $totalPages): ?><a class="secondary-button" href="?<?= e(http_build_query(['q' => $query, 'role' => $roleFilter, 'verification' => $verificationFilter, 'account' => $accountFilter, 'page' => $page + 1])) ?>">Next</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
