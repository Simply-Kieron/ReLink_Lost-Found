<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

start_app_session();

$continueInput = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['continue'] ?? '') : ($_GET['continue'] ?? '');
$continue = is_string($continueInput)
    && preg_match('/\Areport-found-lost\.php\?id=[1-9][0-9]*\z/', $continueInput) === 1
    ? $continueInput
    : '';

if (current_user() !== null) {
    redirect($continue !== '' ? $continue : 'dashboard.php');
}

$pageTitle = 'Login';
$pageDesign = 'login';
$errors = [];
$email = '';
$attemptLimit = 5;
$lockDuration = 300;
$attempts = isset($_SESSION['login_attempts']) && is_int($_SESSION['login_attempts'])
    ? $_SESSION['login_attempts']
    : 0;
$lockedUntil = isset($_SESSION['login_locked_until']) && is_int($_SESSION['login_locked_until'])
    ? $_SESSION['login_locked_until']
    : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    if ($lockedUntil > time()) {
        http_response_code(429);
        $errors[] = 'Unable to log in right now. Please wait a few minutes and try again.';
    } else {
        if ($lockedUntil !== 0) {
            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
            $attempts = 0;
        }

        $emailInput = $_POST['email'] ?? '';
        $email = is_string($emailInput) ? strtolower(trim($emailInput)) : '';
        $password = $_POST['password'] ?? '';
        $user = false;

        if (
            $email !== ''
            && strlen($email) <= 191
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && is_string($password)
            && $password !== ''
        ) {
            require_once __DIR__ . '/../includes/db.php';

            try {
                $statement = $pdo->prepare(
                    'SELECT id, name, email, password_hash, role, account_status, must_change_password FROM users WHERE email = :email LIMIT 1'
                );
                $statement->execute(['email' => $email]);
                $user = $statement->fetch();
            } catch (PDOException $exception) {
                error_log('Login query failed: ' . $exception->getMessage());
                http_response_code(500);
                $errors[] = 'Login is temporarily unavailable. Please try again later.';
            }
        }

        if ($errors === [] && is_array($user) && password_verify($password, $user['password_hash'])) {
            if (!in_array($user['role'], ['student', 'staff'], true) || !in_array($user['account_status'], ['active', 'suspended'], true)) {
                error_log('Login rejected because a user account has an invalid role.');
                http_response_code(500);
                $errors[] = 'Login is temporarily unavailable. Please contact the site administrator.';
            } elseif ($user['account_status'] !== 'active') {
                $errors[] = 'This account is not active. Contact a staff member for help.';
            } else {
                session_regenerate_id(true);
                unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
                $_SESSION['user'] = [
                    'id' => (int) $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'must_change_password' => (bool) $user['must_change_password'],
                ];
                if ($_SESSION['user']['must_change_password']) {
                    set_flash('error', 'A staff member issued a temporary password. Change it in your Profile before continuing.');
                    redirect('profile.php');
                }
                set_flash('success', 'You are now logged in.');
                redirect($continue !== '' ? $continue : 'dashboard.php');
            }
        } elseif ($errors === []) {
            $attempts++;
            $_SESSION['login_attempts'] = $attempts;

            if ($attempts >= $attemptLimit) {
                $_SESSION['login_locked_until'] = time() + $lockDuration;
                http_response_code(429);
                $errors[] = 'Unable to log in right now. Please wait a few minutes and try again.';
            } else {
                $errors[] = 'The email or password is incorrect.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-shell auth-page login-page">
    <aside class="auth-intro-panel">
        <span class="auth-intro-mark" aria-hidden="true">⌕</span>
        <p class="eyebrow">Campus lost &amp; found</p>
        <h2>Find what matters. Reunite what’s lost.</h2>
        <p>Browse found items, report a lost belonging, and follow claim updates from one place.</p>
        <a href="found-items.php">Browse found items <span aria-hidden="true">→</span></a>
    </aside>
    <div class="surface-card auth-card">
        <div class="stack">
            <div>
                <p class="eyebrow">Welcome back</p>
                <h1 id="login-title">Log in</h1>
                <p>Log in to report items and manage your account.</p>
            </div>

            <?php if ($errors !== []): ?>
                <div class="error-box" role="alert">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form class="form-panel" method="post" action="login.php">
                <?= csrf_field() ?>
                <?php if ($continue !== ''): ?><input type="hidden" name="continue" value="<?= e($continue) ?>"><?php endif; ?>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" maxlength="191" autocomplete="username" value="<?= e($email) ?>" required>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                </div>
                <button class="primary-button w-full" type="submit">Log in</button>
            </form>

            <p class="auth-alternate">
                Need an account?
                <a href="register.php">Register as a student</a>
            </p>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
