<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

start_app_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookieParameters = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $cookieParameters['path'],
            'domain' => $cookieParameters['domain'],
            'secure' => $cookieParameters['secure'],
            'httponly' => $cookieParameters['httponly'],
            'samesite' => $cookieParameters['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
    redirect('../public/index.php');
}

$user = require_login();
$pageTitle = 'Log out';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="mx-auto max-w-xl" aria-labelledby="logout-title">
    <h1 id="logout-title" class="text-3xl font-bold tracking-tight">Log out</h1>
    <p class="mt-3 text-slate-700">You are logged in as <?= e($user['name']) ?>. Log out of this account?</p>
    <form class="mt-6" method="post" action="logout.php">
        <?= csrf_field() ?>
        <button class="min-h-12 rounded-lg bg-blue-800 px-5 py-3 font-semibold text-white hover:bg-blue-900 focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-blue-700" type="submit">Log out</button>
    </form>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
