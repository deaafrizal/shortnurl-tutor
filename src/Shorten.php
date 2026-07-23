<?php

namespace App;

class Shorten
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
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

    public function createShortUrl(string $originalUrl): array
    {
        $originalUrl = trim($originalUrl);

        if (!filter_var($originalUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid URL format.');
        }

        $parsed = parse_url($originalUrl);
        if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
            throw new \InvalidArgumentException('URL must start with http:// or https://.');
        }

        $shortCode = $this->generateUniqueCode();

        $stmt = $this->db->prepare(
            'INSERT INTO urls (original_url, short_code) VALUES (?, ?)'
        );
        $stmt->execute([$originalUrl, $shortCode]);

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

    public function incrementClick(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE urls SET click_count = click_count + 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function getAllUrls(): array
    {
        $stmt = $this->db->query(
            'SELECT id, original_url, short_code, click_count, created_at FROM urls ORDER BY created_at DESC'
        );
        return $stmt->fetchAll();
    }
}
