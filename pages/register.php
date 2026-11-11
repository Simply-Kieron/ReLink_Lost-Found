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
$pageDesign = 'register';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-shell auth-page register-page">
    <aside class="auth-intro-panel">
        <span class="auth-intro-mark" aria-hidden="true">⌕</span>
        <p class="eyebrow">Campus lost &amp; found</p>
        <h2>Make it easier to find your things.</h2>
        <p>Create a student account to report lost items and securely follow claim updates.</p>
        <a href="found-items.php">Browse found items <span aria-hidden="true">→</span></a>
    </aside>
    <div class="surface-card auth-card">
        <div class="stack">
            <div>
                <p class="eyebrow">New account</p>
                <h1 id="register-title">Create a student account</h1>
                <p>Choose the sign-up email you plan to keep. You will submit your school email and ID from Profile to unlock posting and claims.</p>
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

            <form class="form-panel" method="post" action="register.php">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="name">Name</label>
                    <input id="name" name="name" type="text" maxlength="150" autocomplete="name" value="<?= e($name) ?>" required>
                </div>

                <div class="field">
                    <label for="email">Sign-up email</label>
                    <input id="email" name="email" type="email" maxlength="191" autocomplete="email" value="<?= e($email) ?>" required>
                    <span class="field-note">This email is fixed after you create your account. Your school email will be submitted separately for verification.</span>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
                    <span class="field-note">Use 8 to 72 characters.</span>
                </div>

                <div class="field">
                    <label for="password_confirm">Confirm password</label>
                    <input id="password_confirm" name="password_confirm" type="password" minlength="8" maxlength="72" autocomplete="new-password" data-password-toggle="false" required>
                </div>

                <div class="checkbox-field">
                    <input id="consent" name="consent" type="checkbox" value="1" required>
                    <label for="consent">I agree to the <a href="privacy-policy.php">Privacy Policy</a> and <a href="terms.php">Terms</a>.</label>
                </div>

                <button class="primary-button w-full" type="submit">Create account</button>
            </form>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
