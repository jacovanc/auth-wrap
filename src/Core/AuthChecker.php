<?php

namespace App\Core;

class AuthChecker {
    private $permissions;

    public function __construct() {
        $path = __DIR__ . '/../..' . $_ENV['PERMISSIONS_FILE'] ?? getenv('PERMISSIONS_FILE');
        
        # Check file exists
        if (!file_exists($path)) {
            throw new \Exception('Permissions file not found. Ensure you have created a config/permissions.php file. See config/permissions-example.php for an example.');
        }
        $this->permissions = require $path;
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
