<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? ($document['nombre_documento'] ?? 'Documento')) ?> · <?= e($tenant['razonsocial']) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script defer src="<?= e(asset('js/app.js')) ?>"></script>
</head>
<body class="bg-slate-200 text-slate-900 antialiased print:bg-white">
<div class="print-toolbar mx-auto flex max-w-[900px] items-center justify-between px-5 py-4">
    <a href="<?= e(url($backUrl ?? ('/documentos/' . ($document['id'] ?? '')))) ?>" class="btn-secondary"><?= icon('arrow-left', 'h-4 w-4') ?> Volver</a>
    <button type="button" class="btn-primary" data-print><?= icon('download', 'h-4 w-4') ?> Imprimir / Guardar PDF</button>
</div>
<main class="mx-auto max-w-[900px] p-0 pb-10 sm:px-5">
    <?php require $contentView; ?>
</main>
</body>
</html>
