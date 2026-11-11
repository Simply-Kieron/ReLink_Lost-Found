<?php

require_once __DIR__ . '/functions.php';

function current_user(): ?array
{
    start_app_session();

    if (
        !isset($_SESSION['user'])
        || !is_array($_SESSION['user'])
        || !isset($_SESSION['user']['id'], $_SESSION['user']['role'], $_SESSION['user']['name'])
        || !is_int($_SESSION['user']['id'])
        || !in_array($_SESSION['user']['role'], ['student', 'staff'], true)
        || !is_string($_SESSION['user']['name'])
    ) {
        return null;
    }

    return $_SESSION['user'];
}

function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        set_flash('error', 'Please log in to continue.');
        redirect('login.php');
    }

    global $pdo;
    require_once __DIR__ . '/db.php';
    try {
        $statement = $pdo->prepare('SELECT account_status, must_change_password, avatar_path FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $user['id']]);
        $account = $statement->fetch();
    } catch (PDOException $exception) {
        error_log('Account status check failed: ' . $exception->getMessage());
        http_response_code(500);
        exit('Account status is temporarily unavailable. Please try again later.');
    }

    if (!is_array($account) || $account['account_status'] !== 'active') {
        $_SESSION = [];
        session_destroy();
        set_flash('error', 'This account is not active. Contact a staff member for help.');
        redirect('login.php');
    }

    $_SESSION['user']['must_change_password'] = (bool) $account['must_change_password'];
    $_SESSION['user']['avatar_path'] = $account['avatar_path'];
    $user['avatar_path'] = $account['avatar_path'];
    $currentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($_SESSION['user']['must_change_password'] && !in_array($currentPage, ['profile.php', 'logout.php'], true)) {
        set_flash('error', 'A staff member issued a temporary password. Change it in your Profile before continuing.');
        redirect('profile.php');
    }

    return $user;
}

function require_role(string $role): array
{
    if (!in_array($role, ['student', 'staff'], true)) {
        throw new InvalidArgumentException('The requested role is invalid.');
    }

    $user = require_login();

    if ($user['role'] !== $role) {
        http_response_code(403);
        exit('You do not have permission to view this page.');
    }

    return $user;
}

function require_verified_student(): array
{
    $user = require_role('student');
    global $pdo;
    require_once __DIR__ . '/db.php';

    try {
        $statement = $pdo->prepare(
            "SELECT status FROM student_verifications WHERE user_id = :user_id LIMIT 1"
        );
        $statement->execute(['user_id' => $user['id']]);
        $status = $statement->fetchColumn();
    } catch (PDOException $exception) {
        error_log('Student verification check failed: ' . $exception->getMessage());
        http_response_code(500);
        exit('Verification status is temporarily unavailable. Please try again later.');
    }

    if ($status !== 'verified') {
        redirect('verification-required.php');
    }

    return $user;
}
