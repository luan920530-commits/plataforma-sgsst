<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="theme-color" content="#f5f5f7">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?= e($title ?? 'Autoreporte') ?> · <?= e($config['name']) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"><script defer src="<?= e(asset('js/app.js')) ?>"></script>
</head>
<body class="min-h-screen bg-ios text-slate-900 antialiased">
<a href="#main-content" class="skip-link">Saltar al contenido</a>
<header class="border-b border-slate-200/70 bg-white/90 backdrop-blur-xl"><div class="mx-auto flex max-w-5xl items-center justify-between px-5 py-4"><div class="flex items-center gap-2.5 font-semibold"><span class="brand-mark h-9 w-9"><?= icon('shield', 'h-5 w-5') ?></span><span>normia <span class="font-normal text-slate-400">sst</span></span></div><span class="badge badge-green"><?= icon('lock', 'h-3 w-3') ?> Formulario seguro</span></div></header>
<main id="main-content" class="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12">
    <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
    <?php require $contentView; ?>
    <footer class="mt-8 text-center text-[10px] leading-5 text-slate-400">Autoreportes para prevención y mejora · No uses este formulario para solicitar atención de una emergencia en curso.</footer>
</main>
</body>
</html>
