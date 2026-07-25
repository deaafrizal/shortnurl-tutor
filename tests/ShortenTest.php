<?php

namespace Tests;

use App\Shorten;
use PHPUnit\Framework\TestCase;

class ShortenTest extends TestCase
{
    private function createMockPdoStatement(): \PDOStatement
    {
        return $this->createMock(\PDOStatement::class);
    }

    private function createMockPdo(): \PDO
    {
        return $this->createMock(\PDO::class);
    }

    public function testGenerateUniqueCodeReturnsSixCharacters(): void
    {
        $stmt = $this->createMockPdoStatement();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(false);

        $pdo = $this->createMockPdo();
        $pdo->method('prepare')->willReturn($stmt);

        $shorten = new Shorten($pdo);
        $code = $shorten->generateUniqueCode();

        $this->assertEquals(6, strlen($code));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{6}$/', $code);
    }

    public function testGenerateUniqueCodeRespectsLengthParameter(): void
    {
        $stmt = $this->createMockPdoStatement();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(false);

        $pdo = $this->createMockPdo();
        $pdo->method('prepare')->willReturn($stmt);

        $shorten = new Shorten($pdo);
        $code = $shorten->generateUniqueCode(8);

        $this->assertEquals(8, strlen($code));
    }

    public function testCreateShortUrlWithValidUrlReturnsArray(): void
    {
        $checkStmt = $this->createMockPdoStatement();
        $checkStmt->method('execute')->willReturn(true);
        $checkStmt->method('fetchColumn')->willReturn(false);

        $countStmt = $this->createMockPdoStatement();
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(0);

        $insertStmt = $this->createMockPdoStatement();
        $insertStmt->method('execute')->willReturn(true);

        $pdo = $this->createMockPdo();
        $pdo->method('prepare')->willReturnCallback(function ($query) use ($checkStmt, $countStmt, $insertStmt) {
            if (str_contains($query, 'COUNT(*)')) {
                return $countStmt;
            }
            return str_starts_with($query, 'SELECT') ? $checkStmt : $insertStmt;
        });
        $pdo->method('lastInsertId')->willReturn('1');

        $shorten = new Shorten($pdo);
        $result = $shorten->createShortUrl('https://example.com', '127.0.0.1');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('short_code', $result);
        $this->assertArrayHasKey('original_url', $result);
        $this->assertEquals('https://example.com', $result['original_url']);
        $this->assertEquals(6, strlen($result['short_code']));
    }

    public function testCreateShortUrlWithInvalidUrlThrowsException(): void
    {
        $pdo = $this->createMockPdo();
        $shorten = new Shorten($pdo);

        $this->expectException(\InvalidArgumentException::class);
        $shorten->createShortUrl('not-a-url');
    }

    public function testCreateShortUrlWithoutSchemeThrowsException(): void
    {
        $pdo = $this->createMockPdo();
        $shorten = new Shorten($pdo);

        $this->expectException(\InvalidArgumentException::class);
        $shorten->createShortUrl('ftp://example.com');
    }

    public function testGetUrlByCodeWithExistingCodeReturnsData(): void
    {
        $expected = [
            'id' => '1',
            'original_url' => 'https://example.com',
            'short_code' => 'abc123',
            'click_count' => '0',
            'created_at' => '2026-07-23 12:00:00',
        ];

        $stmt = $this->createMockPdoStatement();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($expected);

        $pdo = $this->createMockPdo();
        $pdo->method('prepare')->willReturn($stmt);

        $shorten = new Shorten($pdo);
        $result = $shorten->getUrlByCode('abc123');

        $this->assertIsArray($result);
        $this->assertEquals('https://example.com', $result['original_url']);
    }

    public function testGetUrlByCodeWithNonExistingCodeReturnsNull(): void
    {
        $stmt = $this->createMockPdoStatement();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMockPdo();
        $pdo->method('prepare')->willReturn($stmt);

        $shorten = new Shorten($pdo);
        $result = $shorten->getUrlByCode('nonexist');

        $this->assertNull($result);
    }

    public function testCreateShortUrlExceedsIpLimitThrowsException(): void
    {
        $checkStmt = $this->createMockPdoStatement();
        $checkStmt->method('execute')->willReturn(true);
        $checkStmt->method('fetchColumn')->willReturn(false);

        $countStmt = $this->createMockPdoStatement();
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(10);

        $pdo = $this->createMockPdo();
        $pdo->method('prepare')->willReturnCallback(function ($query) use ($checkStmt, $countStmt) {
            if (str_contains($query, 'COUNT(*)')) {
                return $countStmt;
            }
            return $checkStmt;
        });

        $shorten = new Shorten($pdo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('maximum limit of 10');
        $shorten->createShortUrl('https://example.com', '127.0.0.1');
    }

    public function testGetAllUrlsByIpReturnsFilteredResults(): void
    {
        $expected = [
            [
                'id' => '1',
                'original_url' => 'https://example.com',
                'short_code' => 'abc123',
                'click_count' => '3',
                'created_at' => '2026-07-23 12:00:00',
            ],
        ];

        $stmt = $this->createMockPdoStatement();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($expected);

        $pdo = $this->createMockPdo();
        $pdo->method('prepare')->willReturn($stmt);

        $shorten = new Shorten($pdo);
        $result = $shorten->getAllUrls('127.0.0.1');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('abc123', $result[0]['short_code']);
    }

    public function testGetAllIpsReturnsArray(): void
    {
        $expected = [
            [
                'ip_address' => '192.168.1.1',
                'url_count' => '5',
                'last_active' => '2026-07-25 12:00:00',
            ],
            [
                'ip_address' => '10.0.0.1',
                'url_count' => '2',
                'last_active' => '2026-07-24 18:00:00',
            ],
        ];

        $stmt = $this->createMockPdoStatement();
        $stmt->method('fetchAll')->willReturnCallback(function ($fetchMode = \PDO::FETCH_BOTH) {
            // Return the expected data regardless of fetch mode
            return [
                [
                    'ip_address' => '192.168.1.1',
                    'url_count' => '5',
                    'last_active' => '2026-07-25 12:00:00',
                ],
                [
                    'ip_address' => '10.0.0.1',
                    'url_count' => '2',
                    'last_active' => '2026-07-24 18:00:00',
                ],
            ];
        });

        $pdo = $this->createMockPdo();
        $pdo->method('query')->willReturn($stmt);

        $shorten = new Shorten($pdo);
        $result = $shorten->getAllIps();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('192.168.1.xxx', $result[0]['ip_address']);
        $this->assertEquals(5, (int) $result[0]['url_count']);
    }

    public function testDeleteUrlByCodeSuccess(): void
    {
        $stmt = $this->createMockPdoStatement();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(1);

        $pdo = $this->createMockPdo();
        $pdo->method('prepare')->willReturn($stmt);

        $shorten = new Shorten($pdo);
        $shorten->deleteUrlByCode('abc123', '127.0.0.1');
        $this->assertTrue(true);
    }

    public function testDeleteUrlByCodeNotOwnedThrowsException(): void
    {
        $stmt = $this->createMockPdoStatement();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);

        $pdo = $this->createMockPdo();
        $pdo->method('prepare')->willReturn($stmt);

        $shorten = new Shorten($pdo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not found or you do not have permission');
        $shorten->deleteUrlByCode('abc123', 'other-ip');
    }

    public function testIncrementClickExecutesUpdate(): void
    {
        $stmt = $this->createMockPdoStatement();
        $stmt->expects($this->once())
             ->method('execute')
             ->with([1]);

        $pdo = $this->createMockPdo();
        $pdo->method('prepare')->willReturn($stmt);

        $shorten = new Shorten($pdo);
        $shorten->incrementClick(1);
        $this->assertTrue(true);
    }
}
