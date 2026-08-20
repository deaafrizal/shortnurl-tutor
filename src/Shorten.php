<?php

namespace App;

class Shorten
{
    private \PDO $db;
    private RateLimiter $rateLimiter;
    private const MAX_URLS_PER_IP = 50; // Total limit per IP

    public function __construct(\PDO $db)
    {
        $this->db = $db;
        $this->rateLimiter = new RateLimiter($db);
    }

    private static function isCloudflareRequest(): bool
    {
        $cfRanges = [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22',
            '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
            '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22',
            '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        ];
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
        foreach ($cfRanges as $range) {
            if (self::ipInRange($remoteIp, $range)) {
                return true;
            }
        }
        return false;
    }

    private static function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        [$subnet, $bits] = explode('/', $range);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask = -1 << (32 - (int) $bits);
        $subnetLong &= $mask;
        return ($ipLong & $mask) === $subnetLong;
    }

    public static function getClientIp(): string
    {
        if (self::isCloudflareRequest() && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function generateUniqueCode(int $length = 6): string
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $max = strlen($chars) - 1;

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $chars[random_int(0, $max)];
            }
        } while ($this->codeExists($code));

        return $code;
    }

    private function codeExists(string $code): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM urls WHERE short_code = ? LIMIT 1');
        $stmt->execute([$code]);
        return (bool) $stmt->fetchColumn();
    }

    public function countByIp(string $ip): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM urls WHERE ip_address = ?');
        $stmt->execute([$ip]);
        return (int) $stmt->fetchColumn();
    }

    public function createShortUrl(string $originalUrl, string $ip = ''): array
    {
        $originalUrl = trim($originalUrl);

        if (!filter_var($originalUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid URL format.');
        }

        $parsed = parse_url($originalUrl);
        if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
            throw new \InvalidArgumentException('URL must start with http:// or https://.');
        }

        // Check rate limiting (hourly limit)
        if ($ip !== '' && $this->rateLimiter->isRateLimited($ip)) {
            $remaining = $this->rateLimiter->getRemainingQuota($ip);
            throw new \RuntimeException(
                'Rate limit exceeded. You can create ' . $remaining . ' more URL(s) within the next hour.'
            );
        }

        // Check total URLs per IP (hard limit)
        if ($ip !== '' && $this->countByIp($ip) >= 10) {
            throw new \RuntimeException(
                'You have reached the maximum limit of 10 URLs for this IP address'
            );
        }

        $shortCode = $this->generateUniqueCode();

        $stmt = $this->db->prepare(
            'INSERT INTO urls (original_url, short_code, ip_address, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$originalUrl, $shortCode, $ip]);

        return [
            'id'           => (int) $this->db->lastInsertId(),
            'original_url' => $originalUrl,
            'short_code'   => $shortCode,
            'click_count'  => 0,
            'created_at'   => date('Y-m-d H:i:s'),
        ];
    }

    public function getUrlByCode(string $code): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, original_url, short_code, click_count, created_at FROM urls WHERE short_code = ? LIMIT 1'
        );
        $stmt->execute([$code]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Get all IPs with anonymized IP addresses
     */
    public function getAllIps(): array
    {
        $stmt = $this->db->query(
            'SELECT ip_address, COUNT(*) AS url_count, MAX(created_at) AS last_active
             FROM urls WHERE ip_address != \'\'
             GROUP BY ip_address
             ORDER BY url_count DESC, last_active DESC, ip_address ASC'
        );
        $ips = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (class_exists('App\IpAnonymizer')) {
            foreach ($ips as &$row) {
                $row['ip_address'] = IpAnonymizer::anonymize($row['ip_address']);
            }
        }
        return $ips;
    }

    /**
     * Return one bounded page for the public activity list.
     * Fetching one extra row lets callers know whether another request is needed.
     */
    public function getIpsPage(int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = min(50, max(1, $perPage));
        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare(
            'SELECT ip_address, COUNT(*) AS url_count, MAX(created_at) AS last_active
             FROM urls WHERE ip_address != \'\'
             GROUP BY ip_address
             ORDER BY url_count DESC, last_active DESC, ip_address ASC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $perPage + 1, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $ips = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $hasMore = count($ips) > $perPage;
        if ($hasMore) {
            array_pop($ips);
        }

        if (class_exists('App\IpAnonymizer')) {
            foreach ($ips as &$row) {
                $row['ip_address'] = IpAnonymizer::anonymize($row['ip_address']);
            }
        }

        return ['items' => $ips, 'has_more' => $hasMore];
    }

    public function getAllUrls(?string $ip = null): array
    {
        if ($ip !== null && $ip !== '') {
            $stmt = $this->db->prepare(
                'SELECT id, original_url, short_code, click_count, created_at FROM urls WHERE ip_address = ? ORDER BY created_at DESC'
            );
            $stmt->execute([$ip]);
        } else {
            $stmt = $this->db->query(
                'SELECT id, original_url, short_code, click_count, created_at FROM urls ORDER BY created_at DESC'
            );
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function deleteUrlByCode(string $code, string $ip): void
    {
        $stmt = $this->db->prepare('DELETE FROM urls WHERE short_code = ? AND ip_address = ?');
        $stmt->execute([$code, $ip]);

        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('URL not found or you do not have permission to delete it.');
        }
    }

    public function incrementClick(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE urls SET click_count = click_count + 1 WHERE id = ?');
        $stmt->execute([$id]);
    }
}
