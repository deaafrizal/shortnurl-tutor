<?php

namespace App;

class Redirect
{
    private Shorten $shorten;

    public function __construct(Shorten $shorten)
    {
        $this->shorten = $shorten;
    }

    public function handle(string $code): void
    {
        $code = trim($code);

        if (!preg_match('/^[a-zA-Z0-9]{1,10}$/', $code)) {
            $this->notFound();
            return;
        }

        $url = $this->shorten->getUrlByCode($code);

        if (!$url) {
            $this->notFound();
            return;
        }

        $this->shorten->incrementClick((int) $url['id']);

        header('Location: ' . $url['original_url'], true, 302);
        exit;
    }

    private function notFound(): void
    {
        header('HTTP/1.1 404 Not Found');
        echo '<h1>404 - Short URL not found</h1>';
        exit;
    }
}
