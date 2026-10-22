<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f5f5f7">
    <meta name="description" content="Gestión de Seguridad y Salud en el Trabajo para empresas colombianas.">
    <title><?= e($title ?? 'Inicio') ?> · <?= e($config['name']) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script defer src="<?= e(asset('js/app.js')) ?>"></script>
</head>
<body class="min-h-screen bg-ios text-slate-900 antialiased">
<a href="#main-content" class="skip-link">Saltar al contenido</a>
<div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
    <div class="absolute -right-40 -top-40 h-96 w-96 rounded-full bg-blue-200/25 blur-3xl"></div>
    <div class="absolute -bottom-48 left-1/3 h-96 w-96 rounded-full bg-indigo-100/40 blur-3xl"></div>
</div>
<?php $flashes = pull_flashes(); ?>
<div id="sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-slate-950/25 backdrop-blur-sm lg:hidden" data-sidebar-close></div>
<?php require BASE_PATH . '/app/Views/partials/sidebar.php'; ?>
<div class="min-h-screen lg:pl-[272px]">
    <?php require BASE_PATH . '/app/Views/partials/header.php'; ?>
    <main id="main-content" class="mx-auto w-full max-w-[1540px] px-4 pb-12 pt-6 sm:px-6 lg:px-9 lg:pt-8">
        <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
        <?php require $contentView; ?>
        <footer class="mt-10 flex flex-col gap-2 border-t border-slate-200/70 pt-5 text-[11px] leading-5 text-slate-400 sm:flex-row sm:items-center sm:justify-between">
            <p>Normia SST · Gestión documental y operativa del SG-SST.</p>
            <p>Decreto 1072 de 2015 · Resolución 0312 de 2019 · Referencia voluntaria ISO 45001</p>
        </footer>
    </main>
</div>
</body>
</html>
