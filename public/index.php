<?php

require_once __DIR__ . '/../config/database.php';

$appName = getenv('APP_NAME') ?: 'ShortnURL';

echo "<h1>$appName</h1>";
echo "<p>URL Shortener is running.</p>";
