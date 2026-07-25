<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Shorten.php';
require_once __DIR__ . '/../src/Redirect.php';
require_once __DIR__ . '/../src/NotFoundException.php';
require_once __DIR__ . '/../src/Csrf.php';
require_once __DIR__ . '/../src/RateLimiter.php';
require_once __DIR__ . '/../src/IpAnonymizer.php';

use App\Shorten;
use App\Redirect;
use App\NotFoundException;
use App\Csrf;

Csrf::startSession();

$db = getDbConnection();
$shorten = new Shorten($db);

$code = $_GET['c'] ?? '';
if ($code !== '') {
    $redirect = new Redirect($shorten);
    try {
        $originalUrl = $redirect->handle($code);
        header('Location: ' . $originalUrl, true, 302);
        exit;
    } catch (NotFoundException $e) {
        header('HTTP/1.1 404 Not Found');
        echo '<h1>404 - Short URL not found</h1>';
        exit;
    }
}

$baseUrl = rtrim(getenv('BASE_URL') ?: 'http://localhost', '/');
$myIp = Shorten::getClientIp();
$viewIp = $_GET['ip'] ?? '';
$showIpList = $viewIp === '';
$success = null;
$error = null;
$newUrl = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf_token'] ?? '')) {
        $error = 'Invalid form token. Please try again.';
    } elseif (($_POST['_action'] ?? '') === 'delete') {
        try {
            $shorten->deleteUrlByCode($_POST['code'] ?? '', $myIp);
            $success = 'URL deleted successfully!';
            Csrf::rotateToken();
        } catch (\RuntimeException $e) {
            $error = $e->getMessage();
        }
    } else {
        $originalUrl = $_POST['url'] ?? '';

        try {
            $created = $shorten->createShortUrl($originalUrl, $myIp);
            $newUrl = $baseUrl . '/?c=' . $created['short_code'];
            $success = 'URL shortened successfully!';
            Csrf::rotateToken();
        } catch (\InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (\RuntimeException $e) {
            $error = $e->getMessage();
        } catch (\Exception $e) {
            $error = 'An error occurred. Please try again.';
        }
    }
}

$urls = null;
$ips = null;
if ($showIpList) {
    $ips = $shorten->getAllIps();
} else {
    $urls = $shorten->getAllUrls($viewIp);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DEASHORTN - URL Shortener Tutorial</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(24px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease-out both; }
        .animate-fade-in { animation: fadeIn 0.4s ease-out both; }
        .animate-slide-down { animation: slideDown 0.4s ease-out both; }
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
    </style>
</head>
<body class="bg-slate-900 min-h-screen flex items-start justify-center pt-24 px-4">

    <nav class="fixed top-0 left-0 right-0 z-50 bg-slate-900/80 backdrop-blur-md border-b border-slate-800 animate-slide-down">
        <div class="max-w-5xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="/" class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-400 font-extrabold text-xl tracking-tight">
                DEASHORTN
            </a>
            <div class="flex items-center gap-6 text-sm">
                <a href="/" class="text-slate-300 hover:text-indigo-400 transition-colors duration-200">Home</a>
                <a href="/?ip=<?= htmlspecialchars($myIp) ?>" class="text-slate-300 hover:text-indigo-400 transition-colors duration-200">My URLs</a>
            </div>
        </div>
    </nav>

    <div class="w-full max-w-3xl">
        <!-- Disclaimer Banner -->
        <div class="bg-amber-900/50 border border-amber-700 text-amber-300 px-5 py-3 rounded-lg mb-6 text-sm flex items-start gap-3 animate-fade-in">
            <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <div>
                <strong>Educational Purpose:</strong> This is a tutorial application for learning PHP security practices. 
                Not recommended for production use without significant security enhancements.
            </div>
        </div>

        <div class="text-center mb-10 animate-fade-in-up">
            <h1 class="text-5xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-400">
                DEASHORTN
            </h1>
            <p class="text-slate-400 mt-2 text-lg">Paste a long URL and make it short</p>
        </div>

        <?php if ($success): ?>
            <div class="bg-emerald-900/50 border border-emerald-700 text-emerald-300 px-5 py-3 rounded-lg mb-6 text-sm flex items-center gap-2 animate-fade-in">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-900/50 border border-red-700 text-red-300 px-5 py-3 rounded-lg mb-6 text-sm flex items-center gap-2 animate-fade-in">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="bg-slate-800/70 backdrop-blur-sm border border-slate-700 rounded-2xl p-6 mb-8 shadow-xl hover:shadow-2xl hover:-translate-y-0.5 transition-all duration-300 animate-fade-in-up">
            <form method="POST" action="" class="flex flex-col sm:flex-row gap-3">
                <input type="hidden" name="_csrf_token" value="<?= Csrf::getToken() ?>">
                <input
                    type="url"
                    name="url"
                    placeholder="https://example.com/very-long-url..."
                    required
                    class="flex-1 bg-slate-900 border border-slate-600 rounded-xl px-4 py-3 text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                >
                <button
                    type="submit"
                    class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 hover:scale-105 text-white font-semibold px-6 py-3 rounded-xl transition-all duration-200 flex items-center gap-2 whitespace-nowrap"
                >
                    Shorten
                </button>
            </form>
        </div>

        <?php if ($showIpList && $ips): ?>
            <div class="bg-slate-800/70 backdrop-blur-sm border border-slate-700 rounded-2xl p-6 shadow-xl overflow-x-auto hover:shadow-2xl transition-all duration-300 animate-fade-in-up delay-300">
                <div class="mb-4">
                    <h2 class="text-slate-200 text-lg font-semibold mb-2">Active Users</h2>
                    <p class="text-slate-400 text-xs">IP addresses are anonymized for privacy protection</p>
                </div>
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-700">
                            <th class="pb-3 pr-3">#</th>
                            <th class="pb-3 pr-3">Anonymized IP</th>
                            <th class="pb-3 pr-3 text-center">URLs</th>
                            <th class="pb-3 pr-3 hidden sm:table-cell">Last Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($ips as $row): ?>
                            <tr class="border-b border-slate-700/50 hover:bg-slate-700/30 transition">
                                <td class="py-3 pr-3 text-slate-500"><?= $i++ ?></td>
                                <td class="py-3 pr-3 text-slate-300 font-mono text-xs">
                                    <?= htmlspecialchars($row['ip_address']) ?>
                                </td>
                                <td class="py-3 pr-3 text-center">
                                    <span class="bg-slate-700 text-slate-300 text-xs font-medium px-2 py-0.5 rounded-full">
                                        <?= (int) $row['url_count'] ?>
                                    </span>
                                </td>
                                <td class="py-3 pr-3 text-slate-400 text-xs hidden sm:table-cell">
                                    <?= htmlspecialchars(date('M j, g:ia', strtotime($row['last_active']))) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (!$showIpList && $urls): ?>
            <div class="bg-slate-800/70 backdrop-blur-sm border border-slate-700 rounded-2xl p-6 shadow-xl overflow-x-auto hover:shadow-2xl transition-all duration-300 animate-fade-in-up delay-300">
                <div class="flex items-center gap-3 mb-4">
                    <a href="/" class="text-indigo-400 hover:text-indigo-300 text-sm">&larr; Back</a>
                    <h2 class="text-slate-200 text-lg font-semibold">My URLs</h2>
                </div>
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-700">
                            <th class="pb-3 pr-3">#</th>
                            <th class="pb-3 pr-3 hidden md:table-cell">Original URL</th>
                            <th class="pb-3 pr-3">Short URL</th>
                            <th class="pb-3 pr-3 text-center">Clicks</th>
                            <th class="pb-3 pr-3 hidden sm:table-cell">Created</th>
                            <th class="pb-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($urls as $url): ?>
                            <?php
                                $shortUrl = $baseUrl . '/?c=' . htmlspecialchars($url['short_code']);
                                $displayUrl = mb_strlen($url['original_url']) > 60
                                    ? mb_substr($url['original_url'], 0, 60) . '...'
                                    : $url['original_url'];
                            ?>
                            <tr class="border-b border-slate-700/50 hover:bg-slate-700/30 transition">
                                <td class="py-3 pr-3 text-slate-500"><?= $i++ ?></td>
                                <td class="py-3 pr-3 hidden md:table-cell text-slate-300 max-w-[250px] truncate" title="<?= htmlspecialchars($url['original_url']) ?>">
                                    <?= htmlspecialchars($displayUrl) ?>
                                </td>
                                <td class="py-3 pr-3">
                                    <a href="<?= $shortUrl ?>" target="_blank"
                                       class="text-indigo-400 hover:text-indigo-300 font-mono text-xs">
                                        <?= htmlspecialchars($url['short_code']) ?>
                                    </a>
                                </td>
                                <td class="py-3 pr-3 text-center">
                                    <span class="bg-slate-700 text-slate-300 text-xs font-medium px-2 py-0.5 rounded-full">
                                        <?= (int) $url['click_count'] ?>
                                    </span>
                                </td>
                                <td class="py-3 pr-3 text-slate-400 text-xs hidden sm:table-cell">
                                    <?= htmlspecialchars(date('M j, g:ia', strtotime($url['created_at']))) ?>
                                </td>
                                <td class="py-3 text-right flex items-center gap-1">
                                    <button onclick="copyToClipboard('<?= htmlspecialchars($shortUrl) ?>', this)"
                                            class="bg-slate-700 hover:bg-slate-600 hover:scale-105 text-slate-300 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 flex items-center gap-1"
                                            data-copied="Copied!">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                        Copy
                                    </button>
                                    <form method="POST" action="" class="inline" onsubmit="return confirm('Delete this short URL?')">
                                        <input type="hidden" name="_csrf_token" value="<?= Csrf::getToken() ?>">
                                        <input type="hidden" name="_action" value="delete">
                                        <input type="hidden" name="code" value="<?= htmlspecialchars($url['short_code']) ?>">
                                        <button type="submit" class="bg-red-800 hover:bg-red-700 hover:scale-105 text-red-300 px-2 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($newUrl): ?>
            <div class="bg-slate-800/70 backdrop-blur-sm border border-slate-700 rounded-2xl p-6 mb-8 shadow-xl animate-fade-in-up delay-200">
                <p class="text-slate-400 text-sm mb-2">Your shortened URL:</p>
                <div class="flex items-center gap-3">
                    <a href="<?= htmlspecialchars($newUrl) ?>" target="_blank"
                       class="text-indigo-400 hover:text-indigo-300 underline break-all font-mono text-lg">
                        <?= htmlspecialchars($newUrl) ?>
                    </a>
                    <button onclick="copyToClipboard('<?= htmlspecialchars($newUrl) ?>', this)"
                            class="shrink-0 bg-slate-700 hover:bg-slate-600 hover:scale-105 text-slate-200 px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center gap-2"
                            data-copied="Copied!">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                        Copy
                    </button>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <script>
    function copyToClipboard(text, btn) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => showCopied(btn));
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showCopied(btn);
        }
    }

    function showCopied(btn) {
        const original = btn.innerHTML;
        btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Copied!';
        btn.classList.remove('bg-slate-700', 'hover:bg-slate-600');
        btn.classList.add('bg-emerald-700', 'hover:bg-emerald-600');
        setTimeout(() => {
            btn.innerHTML = original;
            btn.classList.remove('bg-emerald-700', 'hover:bg-emerald-600');
            btn.classList.add('bg-slate-700', 'hover:bg-slate-600');
        }, 2000);
    }
    </script>
</body>
</html>
