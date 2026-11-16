<?php

// PHP holds the session lock through commit, so repeated POSTs with the same
// token are serialized and redirect to the original result, including in iframes.
function support_submission_field(string $action, int $conversationId = 0): string
{
    $now = time();
    $tokens = $_SESSION['support_submissions'] ?? [];
    foreach ($tokens as $key => $entry) {
        if (($entry['expires'] ?? 0) < $now) {
            unset($tokens[$key]);
        }
    }
    if (count($tokens) >= 200) {
        $tokens = array_slice($tokens, -199, null, true);
    }
    $token = bin2hex(random_bytes(24));
    $tokens[$token] = ['action' => $action, 'conversation_id' => $conversationId, 'result' => null, 'expires' => $now + 7200];
    $_SESSION['support_submissions'] = $tokens;
    return '<input type="hidden" name="submission_token" value="' . e($token) . '">';
}

function support_submission_check(string $action, string $root, string $separator): string
{
    $token = $_POST['submission_token'] ?? '';
    $entry = is_string($token) ? ($_SESSION['support_submissions'][$token] ?? null) : null;
    $conversationId = filter_var($_POST['conversation_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!is_array($entry) || $entry['expires'] < time() || $entry['action'] !== $action
        || ($action === 'reply' && $entry['conversation_id'] !== $conversationId)) {
        http_response_code(409);
        return 'This message form has expired. Reload the conversation before sending again.';
    }
    if ($entry['result'] !== null) {
        redirect($root . $separator . 'id=' . (int) $entry['result']);
    }
    return '';
}

function support_submission_complete(int $conversationId): void
{
    $token = $_POST['submission_token'];
    $_SESSION['support_submissions'][$token]['result'] = $conversationId;
}
