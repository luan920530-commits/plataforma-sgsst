<?php
$rutaActual = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$esActivo = fn(string $prefijo): string => str_contains($rutaActual, $prefijo) ? 'active' : '';
$item = function (string $url, string $activo, string $icono, string $color, string $texto, string $badge = '') {
    return '<a href="' . $url . '" class="mac-item ' . $activo . '"><span class="mac-ico" style="background:' . $color . '"><i class="fa-solid ' . $icono . '"></i></span><span class="flex-1">' . $texto . '</span>' . $badge . '</a>';
};
$badgeCrit = ($criticas ?? 0) > 0 ? '<span class="min-w-[20px] h-5 px-1 rounded-full text-white text-[10px] font-bold inline-flex items-center justify-center" style="background:var(--mac-red)">' . $criticas . '</span>' : '';
$miEmpresaId = null;
if (\App\Auth::hasRole('cliente') || !empty($misEmpresas)) {
    $f = \App\Database::first('SELECT empresa_id FROM empresa_accesos WHERE usuario_id = ? ORDER BY empresa_id LIMIT 1', [\App\Auth::id()]);
    $miEmpresaId = $f ? (int) $f['empresa_id'] : null;
}
?>
<aside class="mac-sidebar w-64 min-w-64 p-3.5 hidden md:block sticky top-[57px] h-[calc(100vh-57px)] overflow-y-auto">
    <?php if (\App\Auth::hasRole('cliente')): ?>
    <nav class="space-y-0.5">
        <div class="pt-1 pb-1.5 px-2 text-[11px] font-bold uppercase tracking-wider mac-muted">Mi empresa</div>
        <?= $item(base_url('mi-empresa'), $esActivo('mi-empresa'), 'fa-gauge', 'linear-gradient(180deg,#34c759,#0f7a2e)', '📊 Tablero de mi empresa', $badgeCrit) ?>
        <?php if ($miEmpresaId): ?>
        <?= $item(base_url("mi-empresa/{$miEmpresaId}#datos"), '', 'fa-building', 'linear-gradient(180deg,#0a84ff,#0040dd)', '1. Datos de mi empresa') ?>
        <?= $item(base_url("mi-empresa/{$miEmpresaId}#cumplimiento"), '', 'fa-list-check', 'linear-gradient(180deg,#30d158,#0f7a2e)', '2. Mi cumplimiento') ?>
        <?= $item(base_url("mi-empresa/{$miEmpresaId}#documentos"), '', 'fa-folder-open', 'linear-gradient(180deg,#64d2ff,#0a84ff)', '3. Mis documentos') ?>
        <?= $item(base_url("mi-empresa/{$miEmpresaId}#registros"), '', 'fa-clipboard-list', 'linear-gradient(180deg,#ff9500,#c93400)', '4. Registros y seguimiento') ?>
        <?= $item(base_url("sgsst/empresa/{$miEmpresaId}/formularios"), $esActivo('formularios'), 'fa-pen-to-square', 'linear-gradient(180deg,#bf5af2,#8e2de0)', 'Formularios web') ?>
        <?= $item(base_url("sgsst/empresa/{$miEmpresaId}/matriz"), $esActivo('matriz'), 'fa-triangle-exclamation', 'linear-gradient(180deg,#ffd60a,#b25000)', 'Matriz de peligros') ?>
        <?php endif; ?>
        <div class="pt-4 pb-1.5 px-2 text-[11px] font-bold uppercase tracking-wider mac-muted">Mi cuenta</div>
        <?= $item(base_url('cambiar-password'), $esActivo('cambiar-password'), 'fa-key', 'linear-gradient(180deg,#8e8e93,#48484a)', 'Contraseña y recuperación') ?>
    </nav>
    <div class="mt-8 mac-card p-3.5" style="border-radius:14px">
        <div class="text-xs font-semibold">🏢 Tu tablero SG-SST</div>
        <div class="text-[11px] mac-muted mt-1">Cumplimiento, documentos, vencimientos y reportes de tus operarios, solo de tu empresa.</div>
    </div>
    <?php else: ?>
    <nav class="space-y-0.5">
        <div class="pt-1 pb-1.5 px-2 text-[11px] font-bold uppercase tracking-wider mac-muted">Principal</div>
        <?= $item(base_url('dashboard'), $esActivo('dashboard'), 'fa-gauge', 'linear-gradient(180deg,#0a84ff,#0040dd)', '📊 Dashboard general') ?>
        <?= $item(base_url('sgsst'), $esActivo('sgsst') && !$esActivo('formularios'), 'fa-clipboard-check', 'linear-gradient(180deg,#34c759,#248a3d)', 'Centro de cumplimiento', $badgeCrit) ?>
        <?php if (!empty($misEmpresas)): ?>
        <?= $item(base_url('mi-empresa'), $esActivo('mi-empresa'), 'fa-building-user', 'linear-gradient(180deg,#34c759,#0f7a2e)', '🏢 Portal cliente (vista)') ?>
        <?php endif; ?>

        <div class="pt-4 pb-1.5 px-2 text-[11px] font-bold uppercase tracking-wider mac-muted">Gestión de empresas</div>
        <?= $item(base_url('empresas'), $esActivo('empresas'), 'fa-building', 'linear-gradient(180deg,#5e5ce6,#3634a3)', 'Empresas') ?>
        <?= $item(base_url('empresas/nueva'), $esActivo('empresas/nueva'), 'fa-circle-plus', 'linear-gradient(180deg,#30d158,#0f7a2e)', 'Nueva empresa + paquete') ?>
        <?= $item(base_url('personal'), $esActivo('personal'), 'fa-users', 'linear-gradient(180deg,#ff6482,#d70015)', 'Personal y roles SG-SST') ?>
        <?php if (\App\Auth::hasRole('superadmin')): ?>
        <?= $item(base_url('usuarios'), $esActivo('usuarios'), 'fa-user-gear', 'linear-gradient(180deg,#bf5af2,#8e2de0)', 'Usuarios, roles y accesos') ?>
        <?php endif; ?>

        <div class="pt-4 pb-1.5 px-2 text-[11px] font-bold uppercase tracking-wider mac-muted">Plan de emergencias · PPPRAE</div>
        <?= $item(base_url('analisis'), $esActivo('analisis'), 'fa-chart-line', 'linear-gradient(180deg,#ff9500,#c93400)', 'Análisis de vulnerabilidad') ?>
        <?= $item(base_url('documentos'), $esActivo('documentos') && !$esActivo('sgsst'), 'fa-file-word', 'linear-gradient(180deg,#64d2ff,#0a84ff)', 'Documentos del plan') ?>
        <?= $item(base_url('brigadistas'), $esActivo('brigadistas'), 'fa-user-shield', 'linear-gradient(180deg,#ffd60a,#b25000)', 'Brigadistas') ?>
        <?= $item(base_url('medevac'), $esActivo('medevac'), 'fa-notes-medical', 'linear-gradient(180deg,#30d158,#0f7a2e)', 'MEDEVAC') ?>

        <div class="pt-4 pb-1.5 px-2 text-[11px] font-bold uppercase tracking-wider mac-muted">Mi cuenta</div>
        <?= $item(base_url('cambiar-password'), $esActivo('cambiar-password'), 'fa-key', 'linear-gradient(180deg,#8e8e93,#48484a)', 'Contraseña y recuperación') ?>
    </nav>
    <div class="mt-8 mac-card p-3.5" style="border-radius:14px">
        <div class="flex items-center gap-2 text-xs font-semibold"><span class="traffic" style="background:#28c840"></span> Plataforma SG-SST</div>
        <div class="text-[11px] mac-muted mt-1">Decreto 1072/2015 · Res. 0312/2019 · GTC 45</div>
    </div>
    <?php endif; ?>
</aside>
