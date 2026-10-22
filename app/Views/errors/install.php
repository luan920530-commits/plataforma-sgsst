<div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-blue-50 text-blue-600"><?= icon('database', 'h-8 w-8') ?: icon('grid', 'h-8 w-8') ?></div>
<h1 class="mt-6 text-3xl font-bold tracking-tight text-slate-950">La plataforma está lista para instalarse</h1>
<p class="mx-auto mt-4 max-w-xl text-sm leading-7 text-slate-500">No se encontró una base de datos inicializada. Desde la raíz del proyecto, ejecuta el instalador con PHP 8.1 o superior y la extensión <code>pdo_sqlite</code>.</p>
<div class="mx-auto mt-7 max-w-xl rounded-2xl bg-slate-950 p-5 text-left font-mono text-sm leading-7 text-blue-100 shadow-lg">
    <p>php scripts/install.php</p>
    <p class="text-slate-500"># o, para un entorno local con datos de prueba:</p>
    <p>php scripts/install.php --demo</p>
</div>
<p class="mt-6 text-xs leading-6 text-slate-400">Después inicia el servidor con <code>php -S 0.0.0.0:8000 -t public public/index.php</code>. La base de datos se creará en <code>database/sgsst.db</code>.</p>
