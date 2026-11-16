<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

start_app_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $nameInput = $_POST['name'] ?? '';
    $emailInput = $_POST['email'] ?? '';
    $name = is_string($nameInput) ? trim($nameInput) : '';
    $email = is_string($emailInput) ? strtolower(trim($emailInput)) : '';
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $consent = $_POST['consent'] ?? '';
    $errors = [];

    if ($name === '' || preg_match('/\A[\P{Cc}]{1,150}\z/u', $name) !== 1) {
        $errors[] = 'Enter your name using no more than 150 characters.';
    }

    if (
        $email === ''
        || strlen($email) > 191
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    ) {
        $errors[] = 'Enter a valid email address.';
    }

    if (!is_string($password) || strlen($password) < 8 || strlen($password) > 72) {
        $errors[] = 'Your password must be between 8 and 72 bytes.';
    }

    if (!is_string($passwordConfirm) || !is_string($password) || !hash_equals($password, $passwordConfirm)) {
        $errors[] = 'The password confirmation does not match.';
    }

    if ($consent !== '1') {
        $errors[] = 'You must agree to the Privacy Policy and Terms to create an account.';
    }

    if ($errors === []) {
        require_once __DIR__ . '/../includes/db.php';

        try {
            $statement = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)'
            );
            $statement->execute([
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'student',
            ]);
            set_flash('success', 'Account created successfully. You can now log in with your email and password.');
            redirect('login.php');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $errors[] = 'An account could not be created with those details. Check whether the email address is already registered.';
            } else {
                error_log('Registration failed: ' . $exception->getMessage());
                http_response_code(500);
                $errors[] = 'We could not create your account right now. Please try again later.';
            }
        }
    }
} else {
    $name = '';
    $email = '';
    $errors = [];
}

$pageTitle = 'Register';
$pageDesign = 'zeta';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-shell zeta-auth-shell zeta-register-shell">
    <div class="zeta-auth-card zeta-register-card">
        <span class="zeta-auth-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.72-1.71"></path><path d="m8 12 8 0"></path></svg>
        </span>
        <div class="zeta-auth-header zeta-register-header">
            <p class="zeta-eyebrow">Create account</p>
            <h1 id="register-title">Create Account</h1>
            <p>Create an account to report lost items, claim found items, and track updates.</p>
        </div>

        <?php if ($errors !== []): ?>
            <div class="error-box" role="alert">
                <p style="margin: 0; font-weight: 700;">Please review the following:</p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="zeta-auth-form zeta-register-form" method="post" action="register.php">
            <?= csrf_field() ?>

            <div class="zeta-field-grid">
                <div class="zeta-field">
                    <label for="name">Full Name</label>
                    <input id="name" name="name" type="text" maxlength="150" autocomplete="name" value="<?= e($name) ?>" placeholder="Enter your full name" required>
                </div>

<p class="relink-verification-note"><strong>Student ID verification</strong><br>After creating your account, open Profile to verify your school email and submit your Student ID number and ID photo. Verification is required for protected student actions.</p>
            </div>

            <div class="zeta-field-grid">
                <div class="zeta-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" maxlength="191" autocomplete="email" value="<?= e($email) ?>" placeholder="Enter your email" required>
                </div>


            </div>

            <div class="zeta-field-grid">
                <div class="zeta-field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" placeholder="Create a password" required>
                </div>

                <div class="zeta-field">
                    <label for="password_confirm">Confirm Password</label>
                    <input id="password_confirm" name="password_confirm" type="password" minlength="8" maxlength="72" autocomplete="new-password" placeholder="Re-enter your password" required>
                </div>
            </div>

            <div class="checkbox-field zeta-consent-field">
                <input id="consent" name="consent" type="checkbox" value="1" required>
                <label for="consent">I agree to the <a href="privacy-policy.php">Privacy Policy</a> and <a href="terms.php">Terms</a>.</label>
            </div>

            <button class="zeta-submit-button" type="submit">Create Account</button>
        </form>

        <p class="zeta-auth-switch">
            Already have an account? <a href="login.php">Log in</a>
        </p>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
