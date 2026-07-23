<?php

namespace Tests;

use App\NotFoundException;
use App\Redirect;
use App\Shorten;
use PHPUnit\Framework\TestCase;

class RedirectTest extends TestCase
{
    public function testHandleWithValidCodeReturnsOriginalUrl(): void
    {
        $urlData = [
            'id' => 1,
            'original_url' => 'https://example.com',
            'short_code' => 'abc123',
            'click_count' => 0,
            'created_at' => '2026-07-23 12:00:00',
        ];

        $shorten = $this->createMock(Shorten::class);
        $shorten->expects($this->once())
                ->method('getUrlByCode')
                ->with('abc123')
                ->willReturn($urlData);
        $shorten->expects($this->once())
                ->method('incrementClick')
                ->with(1);

        $redirect = new Redirect($shorten);
        $result = $redirect->handle('abc123');

        $this->assertEquals('https://example.com', $result);
    }

    public function testHandleWithInvalidCodeThrowsNotFoundException(): void
    {
        $shorten = $this->createMock(Shorten::class);
        $shorten->expects($this->once())
                ->method('getUrlByCode')
                ->with('invalid')
                ->willReturn(null);
        $shorten->expects($this->never())
                ->method('incrementClick');

        $redirect = new Redirect($shorten);

        $this->expectException(NotFoundException::class);
        $redirect->handle('invalid');
    }

    public function testHandleWithMalformedCodeThrowsNotFoundException(): void
    {
        $shorten = $this->createMock(Shorten::class);
        $shorten->expects($this->never())
                ->method('getUrlByCode');
        $shorten->expects($this->never())
                ->method('incrementClick');

        $redirect = new Redirect($shorten);

        $this->expectException(NotFoundException::class);
        $redirect->handle('invalid!@#');
    }

    public function testHandleWithEmptyCodeThrowsNotFoundException(): void
    {
        $shorten = $this->createMock(Shorten::class);
        $shorten->expects($this->never())
                ->method('getUrlByCode');
        $shorten->expects($this->never())
                ->method('incrementClick');

        $redirect = new Redirect($shorten);

        $this->expectException(NotFoundException::class);
        $redirect->handle('');
    }
}
