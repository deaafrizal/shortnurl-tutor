<?php

namespace App;

class Shorten
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public static function getClientIp(): string
    {
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

        if ($ip !== '' && $this->countByIp($ip) >= 10) {
            throw new \RuntimeException('You have reached the maximum limit of 10 shortened URLs.');
        }

        $shortCode = $this->generateUniqueCode();

        $stmt = $this->db->prepare(
            'INSERT INTO urls (original_url, short_code, ip_address) VALUES (?, ?, ?)'
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
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function getAllIps(): array
    {
        $stmt = $this->db->query(
            'SELECT ip_address, COUNT(*) as url_count, MAX(created_at) as last_active
             FROM urls WHERE ip_address != \'\'
             GROUP BY ip_address
             ORDER BY url_count DESC, last_active DESC'
        );
        return $stmt->fetchAll();
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
        return $stmt->fetchAll();
    }

    public function incrementClick(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE urls SET click_count = click_count + 1 WHERE id = ?');
        $stmt->execute([$id]);
    }
}
