<?php

return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'lost_and_found',
    'username' => 'root',
    'password' => '',
    // Generate a 64-character hexadecimal key with PHP: bin2hex(random_bytes(32)).
    // Keep this secret stable; changing it makes active verification codes unreadable.
    'verification_code_encryption_key' => '',
];
