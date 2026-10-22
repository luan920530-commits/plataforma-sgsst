<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f5f5f7">
    <title><?= e($title ?? 'Error') ?> · Normia SST</title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="min-h-screen bg-ios text-slate-900 antialiased">
<main class="relative flex min-h-screen items-center justify-center overflow-hidden px-5 py-12">
    <div class="absolute left-1/2 top-1/2 -z-10 h-[520px] w-[520px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-blue-100/50 blur-3xl"></div>
    <div class="w-full max-w-3xl text-center">
        <a href="<?= e(url('/')) ?>" class="mb-10 inline-flex items-center gap-2.5 font-semibold tracking-tight text-slate-950">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-white shadow-sm"><?= icon('shield', 'h-5 w-5') ?></span>
            Normia <span class="font-normal text-slate-400">SST</span>
        </a>
        <div class="ios-card px-6 py-12 sm:px-12">
            <?php require $contentView; ?>
        </div>
    </div>
</main>
</body>
</html>
