<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo ?? 'Plataforma SG-SST') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50:  '#eff6ff', 100: '#dbeafe', 500: '#3b82f6',
                            600: '#2563eb', 700: '#1d4ed8', 900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', system-ui, sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <?php if (\App\Auth::check()): ?>
        <?php require __DIR__ . '/partials/navbar.php'; ?>
        <div class="flex">
            <?php require __DIR__ . '/partials/sidebar.php'; ?>
            <main class="flex-1 p-6 max-w-7xl mx-auto">
    <?php else: ?>
        <main class="min-h-screen flex items-center justify-center">
    <?php endif; ?>

        <?php foreach (get_flash() as $f): ?>
            <div class="mb-4 p-4 rounded-lg border
                <?= $f['tipo'] === 'error'   ? 'bg-red-50 border-red-200 text-red-800' : '' ?>
                <?= $f['tipo'] === 'success' ? 'bg-green-50 border-green-200 text-green-800' : '' ?>
                <?= $f['tipo'] === 'warning' ? 'bg-yellow-50 border-yellow-200 text-yellow-800' : '' ?>">
                <?= e($f['mensaje']) ?>
            </div>
        <?php endforeach; ?>

        <?php require __DIR__ . '/../views/' . $template . '.php'; ?>

    <?php if (\App\Auth::check()): ?>
            </main>
        </div>
    <?php else: ?>
        </main>
    <?php endif; ?>
</body>
</html>
