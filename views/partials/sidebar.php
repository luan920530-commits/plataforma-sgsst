<?php
$rutaActual = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$esActivo = fn(string $prefijo): string => str_contains($rutaActual, $prefijo) ? 'active' : '';
$item = function (string $url, string $activo, string $icono, string $color, string $texto) {
    return '<a href="' . $url . '" class="mac-item ' . $activo . '"><span class="mac-ico" style="background:' . $color . '"><i class="fa-solid ' . $icono . '"></i></span><span>' . $texto . '</span></a>';
};
?>
<aside class="mac-sidebar w-64 min-w-64 p-3.5 hidden md:block sticky top-[57px] h-[calc(100vh-57px)] overflow-y-auto">
    <nav class="space-y-0.5">
        <?= $item(base_url('dashboard'), $esActivo('dashboard'), 'fa-gauge', 'linear-gradient(180deg,#0a84ff,#0040dd)', 'Dashboard') ?>
        <?php if (\App\Database::first('SELECT id FROM empresa_accesos WHERE usuario_id = ?', [\App\Auth::id()])): ?>
        <?= $item(base_url('mi-empresa'), $esActivo('mi-empresa'), 'fa-building-user', 'linear-gradient(180deg,#34c759,#0f7a2e)', '🏢 Mi empresa') ?>
        <?php endif; ?>

        <div class="pt-4 pb-1.5 px-2 text-[11px] font-bold uppercase tracking-wider mac-muted">SG-SST integral</div>
        <?= $item(base_url('sgsst'), $esActivo('sgsst'), 'fa-clipboard-check', 'linear-gradient(180deg,#34c759,#248a3d)', 'Centro de cumplimiento') ?>
        <?php if (\App\Auth::hasRole('superadmin')): ?>
        <?= $item(base_url('usuarios'), $esActivo('usuarios'), 'fa-user-gear', 'linear-gradient(180deg,#bf5af2,#8e2de0)', 'Usuarios y roles') ?>
        <?php endif; ?>

        <div class="pt-4 pb-1.5 px-2 text-[11px] font-bold uppercase tracking-wider mac-muted">Empresas y emergencias</div>
        <?= $item(base_url('empresas'), $esActivo('empresas'), 'fa-building', 'linear-gradient(180deg,#5e5ce6,#3634a3)', 'Empresas') ?>
        <?= $item(base_url('analisis'), $esActivo('analisis'), 'fa-chart-line', 'linear-gradient(180deg,#ff9500,#c93400)', 'Análisis PPPRAE') ?>
        <?= $item(base_url('documentos'), $esActivo('documentos'), 'fa-file-word', 'linear-gradient(180deg,#64d2ff,#0a84ff)', 'Documentos') ?>

        <div class="pt-4 pb-1.5 px-2 text-[11px] font-bold uppercase tracking-wider mac-muted">Personal</div>
        <?= $item(base_url('personal'), $esActivo('personal'), 'fa-users', 'linear-gradient(180deg,#ff6482,#d70015)', 'Personal') ?>
        <?= $item(base_url('medevac'), $esActivo('medevac'), 'fa-notes-medical', 'linear-gradient(180deg,#30d158,#0f7a2e)', 'MEDEVAC') ?>
        <?= $item(base_url('brigadistas'), $esActivo('brigadistas'), 'fa-user-shield', 'linear-gradient(180deg,#ffd60a,#b25000)', 'Brigadistas') ?>
    </nav>

    <div class="mt-8 mac-card p-3.5" style="border-radius:14px">
        <div class="flex items-center gap-2 text-xs font-semibold"><span class="traffic" style="background:#28c840"></span> Plataforma SG-SST</div>
        <div class="text-[11px] mac-muted mt-1">v3.0 · Fase 2<br>Decreto 1072/2015 · Res. 0312/2019 · GTC 45</div>
    </div>
</aside>
