<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Shorten.php';
require_once __DIR__ . '/../src/Redirect.php';
require_once __DIR__ . '/../src/NotFoundException.php';
require_once __DIR__ . '/../src/Csrf.php';
require_once __DIR__ . '/../src/RateLimiter.php';
require_once __DIR__ . '/../src/IpAnonymizer.php';

use App\{Shorten, Redirect, NotFoundException, Csrf};

Csrf::startSession();
$shorten = new Shorten(getDbConnection());
$code = $_GET['c'] ?? '';
if ($code !== '') {
    try {
        header('Location: ' . (new Redirect($shorten))->handle($code), true, 302);
        exit;
    } catch (NotFoundException $e) {
        http_response_code(404);
        echo '<h1>404 - Short URL not found</h1>';
        exit;
    }
}

$baseUrl = rtrim(getenv('BASE_URL') ?: 'http://localhost', '/');
$myIp = Shorten::getClientIp();
$showMine = ($_GET['view'] ?? '') === 'mine';
$success = $error = $newUrl = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf_token'] ?? '')) {
        $error = 'Token formulir tidak valid. Silakan coba lagi.';
    } elseif (($_POST['_action'] ?? '') === 'delete') {
        try {
            $shorten->deleteUrlByCode($_POST['code'] ?? '', $myIp);
            $success = 'Tautan berhasil dihapus.';
            Csrf::rotateToken();
        } catch (RuntimeException $e) { $error = $e->getMessage(); }
    } else {
        try {
            $created = $shorten->createShortUrl($_POST['url'] ?? '', $myIp);
            $newUrl = $baseUrl . '/?c=' . $created['short_code'];
            $success = 'Tautan pendek berhasil dibuat.';
            Csrf::rotateToken();
        } catch (InvalidArgumentException | RuntimeException $e) { $error = $e->getMessage(); }
        catch (Exception $e) { $error = 'Terjadi kesalahan. Silakan coba lagi.'; }
    }
}

$rows = $showMine ? $shorten->getAllUrls($myIp) : $shorten->getAllIps();
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Pemendek URL cepat, sederhana, dan berfokus pada privasi.">
    <meta name="theme-color" content="#09090b">
    <title>DeaShortn — Tautan ringkas, tanpa ribet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes enter { from { opacity:0; transform:translateY(12px) } }
        .enter { animation:enter .45s cubic-bezier(.2,.8,.2,1) both }
        .glow { background-image:radial-gradient(circle at 50% -10%,rgba(99,102,241,.22),transparent 38%) }
        :focus-visible { outline:3px solid rgba(129,140,248,.65); outline-offset:3px }
        @media(prefers-reduced-motion:reduce){*{animation:none!important}}
    </style>
</head>
<body class="glow min-h-screen bg-zinc-950 text-zinc-100 antialiased selection:bg-indigo-500/30">
<nav class="border-b border-white/5 bg-zinc-950/75 backdrop-blur-xl">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-5 sm:px-8">
        <a href="/" class="flex items-center gap-2.5 font-bold tracking-tight" aria-label="DeaShortn beranda"><span class="grid h-8 w-8 place-items-center rounded-lg bg-indigo-500 text-sm shadow-lg shadow-indigo-500/25">D</span>DeaShortn</a>
        <div class="flex rounded-full border border-white/10 bg-white/[.03] p-1 text-sm">
            <a href="/" class="rounded-full px-4 py-1.5 <?= !$showMine ? 'bg-white/10 text-white' : 'text-zinc-400 hover:text-white' ?>">Beranda</a>
            <a href="/?view=mine" class="rounded-full px-4 py-1.5 <?= $showMine ? 'bg-white/10 text-white' : 'text-zinc-400 hover:text-white' ?>">Tautan saya</a>
        </div>
    </div>
</nav>

<main class="mx-auto max-w-6xl px-5 pb-16 pt-14 sm:px-8 sm:pt-20">
    <section class="mx-auto max-w-3xl text-center enter">
        <span class="inline-flex items-center gap-2 rounded-full border border-indigo-400/20 bg-indigo-400/10 px-3 py-1 text-xs font-medium text-indigo-300"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>Cepat · Privat · Gratis</span>
        <h1 class="mt-6 text-4xl font-black tracking-tight sm:text-6xl">Tautan panjang,<br><span class="bg-gradient-to-r from-indigo-400 to-violet-400 bg-clip-text text-transparent">dibuat lebih ringkas.</span></h1>
        <p class="mx-auto mt-5 max-w-xl leading-7 text-zinc-400 sm:text-lg">Tempel URL, dapatkan tautan pendek, lalu bagikan ke mana saja. Tanpa akun dan tanpa langkah yang membingungkan.</p>
    </section>

    <section class="mx-auto mt-10 max-w-3xl enter" aria-label="Form pemendek URL">
        <div class="rounded-2xl border border-white/10 bg-white/[.055] p-2 shadow-2xl shadow-indigo-950/30 sm:p-3">
            <form method="post" class="flex flex-col gap-2 sm:flex-row">
                <input type="hidden" name="_csrf_token" value="<?= $escape(Csrf::getToken()) ?>">
                <label for="url" class="sr-only">URL yang ingin dipendekkan</label>
                <input id="url" type="url" name="url" inputmode="url" autocomplete="url" placeholder="https://contoh.com/tautan-yang-panjang" required class="min-w-0 flex-1 rounded-xl border border-transparent bg-zinc-900 px-4 py-3.5 text-white placeholder-zinc-600 transition focus:border-indigo-400/60 focus:outline-none">
                <button class="rounded-xl bg-indigo-500 px-6 py-3.5 font-semibold shadow-lg shadow-indigo-500/20 transition hover:bg-indigo-400 active:scale-[.98]">Perpendek →</button>
            </form>
        </div>
        <p class="mt-3 text-center text-xs text-zinc-600">Gunakan layanan ini secara bertanggung jawab.</p>
    </section>

    <?php if ($success): ?><div role="status" class="mx-auto mt-6 flex max-w-3xl gap-3 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-300 enter">✓ <?= $escape($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div role="alert" class="mx-auto mt-6 flex max-w-3xl gap-3 rounded-xl border border-red-400/20 bg-red-400/10 px-4 py-3 text-sm text-red-300 enter">! <?= $escape($error) ?></div><?php endif; ?>

    <?php if ($newUrl): ?>
    <section class="mx-auto mt-5 max-w-3xl rounded-2xl border border-indigo-400/20 bg-indigo-400/[.08] p-5 enter" aria-labelledby="result-title">
        <p id="result-title" class="text-sm font-medium text-zinc-300">Tautan baru Anda siap</p>
        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center"><a href="<?= $escape($newUrl) ?>" target="_blank" rel="noopener noreferrer" class="min-w-0 flex-1 truncate rounded-lg bg-zinc-950/60 px-4 py-3 font-mono text-sm text-indigo-300"><?= $escape($newUrl) ?></a><button type="button" data-copy="<?= $escape($newUrl) ?>" class="copy-button shrink-0 rounded-lg bg-white px-4 py-3 text-sm font-semibold text-zinc-950 hover:bg-zinc-200">Salin tautan</button></div>
    </section>
    <?php endif; ?>

    <section class="mt-16 border-t border-white/5 pt-8 enter">
        <div class="mb-5 flex items-end justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-[.18em] text-indigo-400"><?= $showMine ? 'Dasbor pribadi' : 'Komunitas' ?></p><h2 class="mt-1 text-2xl font-bold"><?= $showMine ? 'Tautan saya' : 'Aktivitas terbaru' ?></h2><p class="mt-1 text-sm text-zinc-500"><?= $showMine ? 'Kelola tautan yang dibuat dari perangkat ini.' : 'Alamat IP disamarkan untuk menjaga privasi.' ?></p></div><?php if ($showMine): ?><a href="/" class="text-sm text-zinc-400 hover:text-white">← Kembali</a><?php endif; ?></div>

        <?php if ($rows): ?>
        <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/[.035]"><div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <?php if (!$showMine): ?>
            <thead><tr class="border-b border-white/10 bg-white/[.025] text-xs uppercase tracking-wider text-zinc-500"><th class="px-5 py-4">#</th><th class="px-5 py-4">Pengguna anonim</th><th class="px-5 py-4 text-center">Tautan</th><th class="hidden px-5 py-4 sm:table-cell">Terakhir aktif</th></tr></thead>
            <tbody><?php $i=1; foreach ($rows as $row): ?><tr class="border-b border-white/5 last:border-0 hover:bg-white/[.03]"><td class="px-5 py-4 text-zinc-600"><?= $i++ ?></td><td class="px-5 py-4 font-mono text-xs"><?= $escape($row['ip_address']) ?></td><td class="px-5 py-4 text-center"><span class="rounded-full bg-white/[.07] px-2.5 py-1 text-xs"><?= (int)$row['url_count'] ?></span></td><td class="hidden px-5 py-4 text-xs text-zinc-500 sm:table-cell"><?= $escape(date('d M Y, H:i',strtotime($row['last_active']))) ?></td></tr><?php endforeach; ?></tbody>
        <?php else: ?>
            <thead><tr class="border-b border-white/10 bg-white/[.025] text-xs uppercase tracking-wider text-zinc-500"><th class="px-5 py-4">Tujuan</th><th class="px-5 py-4">Tautan pendek</th><th class="px-5 py-4 text-center">Klik</th><th class="px-5 py-4 text-right">Aksi</th></tr></thead>
            <tbody><?php foreach ($rows as $row): $shortUrl=$baseUrl.'/?c='.$row['short_code']; $display=mb_strlen($row['original_url'])>42?mb_substr($row['original_url'],0,42).'…':$row['original_url']; ?><tr class="border-b border-white/5 last:border-0 hover:bg-white/[.03]"><td class="max-w-[160px] px-5 py-4 sm:max-w-xs"><div class="truncate" title="<?= $escape($row['original_url']) ?>"><?= $escape($display) ?></div><div class="mt-1 text-xs text-zinc-600"><?= $escape(date('d M Y',strtotime($row['created_at']))) ?></div></td><td class="px-5 py-4"><a href="<?= $escape($shortUrl) ?>" target="_blank" rel="noopener noreferrer" class="font-mono text-xs text-indigo-400"><?= $escape($row['short_code']) ?></a></td><td class="px-5 py-4 text-center"><?= (int)$row['click_count'] ?></td><td class="px-5 py-4"><div class="flex justify-end gap-2"><button type="button" data-copy="<?= $escape($shortUrl) ?>" class="copy-button rounded-lg bg-white/[.07] px-3 py-2 text-xs hover:bg-white/[.12]">Salin</button><form method="post" class="delete-form"><input type="hidden" name="_csrf_token" value="<?= $escape(Csrf::getToken()) ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="code" value="<?= $escape($row['short_code']) ?>"><button class="rounded-lg px-3 py-2 text-xs text-red-400 hover:bg-red-400/10">Hapus</button></form></div></td></tr><?php endforeach; ?></tbody>
        <?php endif; ?>
        </table></div></div>
        <?php else: ?><div class="rounded-2xl border border-dashed border-white/10 bg-white/[.02] px-6 py-14 text-center"><div class="mx-auto grid h-11 w-11 place-items-center rounded-full bg-white/[.05] text-zinc-500">↗</div><p class="mt-4 font-medium text-zinc-300">Belum ada tautan</p><p class="mt-1 text-sm text-zinc-600">Tautan yang dibuat akan muncul di sini.</p></div><?php endif; ?>
    </section>
    <footer class="mt-12 text-center text-xs text-zinc-700">Proyek edukasi keamanan web · Gunakan secara bertanggung jawab</footer>
</main>
<script>
function copyText(text,button){const done=()=>{const label=button.textContent;button.textContent='Tersalin ✓';button.classList.add('text-emerald-400');setTimeout(()=>{button.textContent=label;button.classList.remove('text-emerald-400')},2000)};if(navigator.clipboard?.writeText)navigator.clipboard.writeText(text).then(done);else{const field=document.createElement('textarea');field.value=text;document.body.appendChild(field);field.select();document.execCommand('copy');field.remove();done()}}
document.querySelectorAll('.copy-button').forEach(button=>button.addEventListener('click',()=>copyText(button.dataset.copy,button)));
document.querySelectorAll('.delete-form').forEach(form=>form.addEventListener('submit',event=>{if(!confirm('Hapus tautan pendek ini?'))event.preventDefault()}));
</script>
</body>
</html>
