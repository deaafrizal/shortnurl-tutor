<?php

namespace App;

class Redirect
{
    private Shorten $shorten;

    public function __construct(Shorten $shorten)
    {
        $this->shorten = $shorten;
    }

    public function handle(string $code): string
    {
        $code = trim($code);

        if (!preg_match('/^[a-zA-Z0-9]{1,10}$/', $code)) {
            throw new NotFoundException('Short URL not found');
        }

        $url = $this->shorten->getUrlByCode($code);

        if (!$url) {
            throw new NotFoundException('Short URL not found');
        }

        $this->shorten->incrementClick((int) $url['id']);

        return $url['original_url'];
    }
}
