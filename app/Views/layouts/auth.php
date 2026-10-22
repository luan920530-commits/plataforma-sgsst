<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f5f5f7">
    <title><?= e($title ?? 'Acceso') ?> · <?= e($config['name']) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script defer src="<?= e(asset('js/app.js')) ?>"></script>
</head>
<body class="min-h-screen bg-ios text-slate-900 antialiased">
<?php $flashes = pull_flashes(); ?>
<?php require $contentView; ?>
</body>
</html>
