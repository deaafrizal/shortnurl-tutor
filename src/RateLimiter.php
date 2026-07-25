<?php

namespace App;

class RateLimiter
{
    private \PDO $db;
    private const RATE_LIMIT_WINDOW = 3600; // 1 hour in seconds
    private const RATE_LIMIT_MAX_REQUESTS = 30; // Max 30 URLs per hour per IP

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Check if an IP has exceeded the rate limit
     */
    public function isRateLimited(string $ip): bool
    {
        $cutoffTime = time() - self::RATE_LIMIT_WINDOW;
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as count FROM urls WHERE ip_address = ? AND created_at >= FROM_UNIXTIME(?)',
        );
        $stmt->execute([$ip, $cutoffTime]);
        $result = $stmt->fetch();
        
        return (int) ($result['count'] ?? 0) >= self::RATE_LIMIT_MAX_REQUESTS;
    }

    /**
     * Get remaining quota for an IP
     */
    public function getRemainingQuota(string $ip): int
    {
        $cutoffTime = time() - self::RATE_LIMIT_WINDOW;
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as count FROM urls WHERE ip_address = ? AND created_at >= FROM_UNIXTIME(?)'
        );
        $stmt->execute([$ip, $cutoffTime]);
        $result = $stmt->fetch();
        
        $remaining = self::RATE_LIMIT_MAX_REQUESTS - (int) ($result['count'] ?? 0);
        return max(0, $remaining);
    }
}
