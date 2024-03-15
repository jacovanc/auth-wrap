<?php

namespace App\Core;

use PDO;

class RateLimit {
    private $db;
    private $maxAttempts = 5;
    private $windowTime = 300; // 5 minutes in seconds

    public function __construct($pdo) {
        $this->initializeDatabase($pdo);
    }

    private function initializeDatabase($pdo) {
        $this->db = $pdo;
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec("CREATE TABLE IF NOT EXISTS rate_limit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45),
            action_key VARCHAR(100),
            attempt_time DATETIME
        )");
    }

    public function isRateLimited($ip, $key) {
        // Remove old entries
        $cutoff = new \DateTime();
        $cutoff->modify("-{$this->windowTime} seconds");
        $stmt = $this->db->prepare("DELETE FROM rate_limit WHERE attempt_time < :cutoff");
        $stmt->execute(['cutoff' => $cutoff->format('Y-m-d H:i:s')]);

        // Count current attempts
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM rate_limit WHERE ip_address = :ip AND action_key = :key");
        $stmt->execute(['ip' => $ip, 'key' => $key]);
        $attempts = $stmt->fetchColumn();

        if ($attempts >= $this->maxAttempts) {
            return true; // Rate limited
        }

        // Record new attempt
        $stmt = $this->db->prepare("INSERT INTO rate_limit (ip_address, action_key, attempt_time) VALUES (:ip, :key, datetime('now')");
        $stmt->execute(['ip' => $ip, 'key' => $key]);

        return false; // Not rate limited
    }
}
