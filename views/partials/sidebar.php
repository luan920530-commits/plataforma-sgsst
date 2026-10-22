<?php
$rutaActual = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$esActivo = function (string $prefijo) use ($rutaActual): string {
    return str_contains($rutaActual, $prefijo)
        ? 'bg-brand-50 text-brand-700 border-brand-600'
        : 'text-slate-600 hover:bg-slate-100 border-transparent';
};
?>

<aside class="w-64 bg-white border-r border-slate-200 min-h-[calc(100vh-64px)] p-4 hidden md:block">
    <nav class="space-y-1">
        <a href="<?= base_url('dashboard') ?>"
           class="flex items-center gap-3 px-3 py-2 rounded-lg border-l-4 transition <?= $esActivo('dashboard') ?>">
            <i class="fa-solid fa-gauge w-5 text-center"></i>
            <span class="font-medium text-sm">Dashboard</span>
        </a>

        <div class="pt-4 pb-2 px-3">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">SG-SST integral</span>
        </div>

        <a href="<?= base_url('sgsst') ?>"
           class="flex items-center gap-3 px-3 py-2 rounded-lg border-l-4 transition <?= $esActivo('sgsst') ?>">
            <i class="fa-solid fa-clipboard-check w-5 text-center"></i>
            <span class="font-medium text-sm">Centro de cumplimiento</span>
        </a>

        <?php if (\App\Auth::hasRole('superadmin')): ?>
        <a href="<?= base_url('usuarios') ?>"
           class="flex items-center gap-3 px-3 py-2 rounded-lg border-l-4 transition <?= $esActivo('usuarios') ?>">
            <i class="fa-solid fa-user-gear w-5 text-center"></i>
            <span class="font-medium text-sm">Usuarios y roles</span>
        </a>
        <?php endif; ?>

        <a href="<?= base_url('empresas') ?>"
           class="flex items-center gap-3 px-3 py-2 rounded-lg border-l-4 transition <?= $esActivo('empresas') ?>">
            <i class="fa-solid fa-building w-5 text-center"></i>
            <span class="font-medium text-sm">Empresas</span>
        </a>

        <a href="<?= base_url('analisis') ?>"
           class="flex items-center gap-3 px-3 py-2 rounded-lg border-l-4 transition <?= $esActivo('analisis/') ?>">
            <i class="fa-solid fa-chart-line w-5 text-center"></i>
            <span class="font-medium text-sm">Análisis</span>
        </a>

        <div class="pt-4 pb-2 px-3">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Recursos</span>
        </div>

        <a href="<?= base_url('documentos') ?>"
           class="flex items-center gap-3 px-3 py-2 rounded-lg border-l-4 transition <?= $esActivo('documentos') ?>">
            <i class="fa-solid fa-file-word w-5 text-center"></i>
            <span class="font-medium text-sm">Documentos</span>
        </a>

        <div class="pt-4 pb-2 px-3">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Personal</span>
        </div>

        <a href="<?= base_url('personal') ?>"
           class="flex items-center gap-3 px-3 py-2 rounded-lg border-l-4 transition <?= $esActivo('personal') ?>">
            <i class="fa-solid fa-users w-5 text-center"></i>
            <span class="font-medium text-sm">Personal</span>
        </a>

        <a href="<?= base_url('medevac') ?>"
           class="flex items-center gap-3 px-3 py-2 rounded-lg border-l-4 transition <?= $esActivo('medevac') ?>">
            <i class="fa-solid fa-notes-medical w-5 text-center"></i>
            <span class="font-medium text-sm">MEDEVAC</span>
        </a>

        <a href="<?= base_url('brigadistas') ?>"
           class="flex items-center gap-3 px-3 py-2 rounded-lg border-l-4 transition <?= $esActivo('brigadistas') ?>">
            <i class="fa-solid fa-user-shield w-5 text-center"></i>
            <span class="font-medium text-sm">Brigadistas</span>
        </a>
    </nav>

    <div class="mt-8 p-3 bg-slate-50 rounded-lg border border-slate-200">
        <div class="text-xs text-slate-500 mb-1">Versión</div>
        <div class="text-sm font-medium text-slate-700">v2.0.0 · SG-SST</div>
    </div>
</aside>
