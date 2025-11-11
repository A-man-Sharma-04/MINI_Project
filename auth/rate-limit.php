<?php
// auth/rate-limit.php
require_once 'config.php';
require_once 'db.php';

class RateLimiter {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Check if request is rate limited
     * @param string $key Unique identifier (email, IP, etc.)
     * @param string $type Type of request (otp, login, google)
     * @param int $max_requests Max requests allowed
     * @param int $time_window_seconds Time window in seconds
     * @return bool True if rate limited
     */
    public function isRateLimited($key, $type, $max_requests, $time_window_seconds) {
        $cutoff = date('Y-m-d H:i:s', time() - $time_window_seconds);
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM rate_limits 
            WHERE key_id = ? AND type = ? AND created_at > ?
        ");
        $stmt->execute([$key, $type, $cutoff]);
        $count = $stmt->fetch()['count'];
        
        return $count >= $max_requests;
    }
    
    /**
     * Record a request
     */
    public function recordRequest($key, $type) {
        $this->db->prepare("
            INSERT INTO rate_limits (key_id, type, created_at) 
            VALUES (?, ?, NOW())
        ")->execute([$key, $type]);
    }
    
    /**
     * Clean old records (run this periodically)
     */
    public function cleanup() {
        $cutoff = date('Y-m-d H:i:s', time() - (24 * 3600)); // 24 hours ago
        $this->db->prepare("DELETE FROM rate_limits WHERE created_at < ?")->execute([$cutoff]);
    }
}