<?php

namespace Auth;

class AuthChecker {
    private $permissions;

    public function __construct() {
        # Check file exists
        if (!file_exists(__DIR__.'/../config/permissions.php')) {
            throw new \Exception('Permissions file not found. Ensure you have created a config/permissions.php file. See config/permissions-example.php for an example.');
        }
        $this->permissions = require __DIR__.'/../config/permissions.php';
    }

    public function isAllowed($email, $subdomain) {
        return in_array($email, $this->permissions[$subdomain] ?? []);
    }

    public function generateLink($email, $subdomain) {
        // Implement link or one-time code generation logic here
        // For example, create a hashed token and store it with a session or in a database
        return 'http://'.$subdomain.'/auth?token=...';
    }
}
