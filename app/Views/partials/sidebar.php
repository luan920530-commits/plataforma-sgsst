<?php
$role = $user['rol'] ?? '';
$planModule = $role === 'SuperAdmin' ? App\Services\ModuleAccess::SGST : App\Services\ModuleAccess::current();
if ($role === 'SuperAdmin') {
    $navigation = [
        ['label' => 'PLATAFORMA', 'items' => [
            ['Resumen global', '/dashboard', 'dashboard', true],
            ['Empresas', '/admin/empresas', 'building'],
        ]],
        ['label' => 'CONFIGURACIÓN', 'items' => [
            ['Actividades y riesgos', '/admin/actividades', 'briefcase'],
            ['Estándares mínimos', '/admin/estandares', 'book'],
            ['Planes SaaS', '/admin/planes', 'grid'],
        ]],
        ['label' => 'CONTROL', 'items' => [
            ['Auditoría general', '/admin/auditoria', 'shield-plain'],
        ]],
    ];
} elseif ($role === 'Trabajador') {
    if ($planModule === App\Services\ModuleAccess::SGST) {
        $navigation = [
            ['label' => 'MI ESPACIO', 'items' => [
                ['Mi portal', '/portal', 'dashboard', true],
                ['Mi inducción', '/portal/induccion', 'cap'],
                ['Crear autoreporte', '/portal#reportar', 'clipboard'],
                ['Políticas vigentes', '/portal#politicas', 'documents'],
            ]],
        ];
    } else {
        $navigation = [
            ['label' => 'MI CUENTA', 'items' => [
                ['Alcance del plan', '/dashboard', 'dashboard', true],
                ['Seguridad de la cuenta', '/cuenta/seguridad', 'lock'],
            ]],
        ];
    }
} else {
    if ($planModule === App\Services\ModuleAccess::PESV) {
        $navigation = [
            ['label' => 'PESVIAL', 'items' => [
                ['Resumen PESVial', '/dashboard', 'dashboard', true],
                ['Kit PESV · 24 pasos', '/pesv', 'shield-plain'],
                ['Documentos PESV', '/documentos', 'documents'],
                ['Centro de evidencias', '/evidencias', 'upload'],
            ]],
            ['label' => 'ADMINISTRACIÓN', 'items' => [
                ['Equipo y accesos', '/equipo', 'users'],
            ]],
        ];
    } elseif ($planModule === App\Services\ModuleAccess::EMERGENCIAS) {
        $navigation = [
            ['label' => 'EMERGENCIAS Y VIGILANCIA', 'items' => [
                ['Resumen del módulo', '/dashboard', 'dashboard', true],
                ['Planes de emergencia', '/emergencias', 'shield'],
                ['Programas y vigilancia', '/programas-prevencion', 'activity'],
                ['Documentos del módulo', '/documentos', 'documents'],
                ['Centro de evidencias', '/evidencias', 'upload'],
                ['Documentación y evidencias', '/cumplimiento-documental', 'shield-plain'],
            ]],
            ['label' => 'ADMINISTRACIÓN', 'items' => [
                ['Equipo y accesos', '/equipo', 'users'],
            ]],
        ];
    } else {
    $navigation = [
        ['label' => 'MI EMPRESA', 'items' => [
            ['Resumen SG-SST', '/dashboard', 'dashboard', true],
        ]],
        ['label' => 'GESTIÓN', 'items' => [
            ['Documentos', '/documentos', 'documents'],
            ['Centro de evidencias', '/evidencias', 'upload'],
            ['Procedimientos y formatos', '/procedimientos', 'book'],
            ['Biblioteca SG-SST', '/biblioteca-sgsst', 'documents'],
            ['PESV · Seguridad vial', '/pesv', 'shield-plain'],
            ['Documentación y evidencias', '/cumplimiento-documental', 'shield-plain'],
            ['Cumplimiento IVC', '/ivc', 'clipboard'],
            ['Gestión sanitaria', '/sanitario', 'shield'],
            ['Ciclo PHVA', '/phva', 'phva', true],
            ['Matriz y autoevaluación', '/autoevaluacion', 'clipboard'],
            ['Gestión operativa', '/operacion/responsables', 'settings'],
            ['Plan Anual de Trabajo', '/plan-anual', 'calendar'],
            ['Matriz IPEVR', '/matriz-ipevr', 'grid'],
            ['Programas de prevención', '/programas-prevencion', 'activity'],
            ['Permisos de trabajo', '/permisos-trabajo', 'clipboard'],
            ['Planes de emergencia', '/emergencias', 'shield'],
            ['Capacitaciones', '/capacitaciones', 'cap'],
            ['Indicadores', '/indicadores', 'chart'],
        ]],
        ['label' => 'MEJORA Y PERSONAS', 'items' => [
            ['Hallazgos y ACPA', '/hallazgos', 'clipboard'],
            ['Autoreportes', '/autoreportes', 'activity'],
            ['Incidentes, AT y EL', '/eventos', 'alert'],
            ['COPASST y Vigía', '/comites', 'users'],
            ['Enlaces y QR de reportes', '/autoreportes/accesos', 'shield-plain'],
            ['Equipo y accesos', '/equipo', 'users'],
        ]],
    ];
    }
    if ($planModule === App\Services\ModuleAccess::SGST) {
        $reservedItems = [];
        if (App\Support\AccessControl::can('salud.reservada')) $reservedItems[] = ['Salud reservada', '/salud', 'heart'];
        if (App\Support\AccessControl::can('convivencia.reservada')) $reservedItems[] = ['Convivencia y CCL', '/convivencia', 'lock'];
        if ($reservedItems !== []) $navigation[] = ['label' => 'EXPEDIENTES RESERVADOS', 'items' => $reservedItems];
    }
    $adminItems = [];
    if ($planModule === App\Services\ModuleAccess::SGST && App\Support\AccessControl::can(App\Models\Permission::INTEGRATIONS)) {
        $adminItems[] = ['Integraciones externas', '/integraciones', 'settings'];
    }
    if ($role === 'AdminEmpresa') $adminItems[] = ['Configuración de empresa', '/empresa/configuracion', 'settings'];
    if ($adminItems !== []) $navigation[] = ['label' => 'ADMINISTRACIÓN', 'items' => $adminItems];
}
?>
<aside id="app-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-[280px] -translate-x-full flex-col bg-white/95 px-4 py-5 shadow-2xl ring-1 ring-black/5 backdrop-blur-2xl transition-transform duration-300 lg:inset-y-3 lg:left-3 lg:w-[252px] lg:translate-x-0 lg:rounded-[24px] lg:shadow-ios" aria-label="Navegación principal">
    <div class="flex items-center justify-between px-2">
        <a href="<?= e(url('/dashboard')) ?>" class="flex items-center gap-2.5" aria-label="Normia SST, inicio">
            <span class="brand-mark"><?= icon('shield', 'h-[22px] w-[22px]') ?></span>
            <span class="text-[19px] font-bold tracking-[-0.04em] text-slate-950">normia<span class="ml-1 font-medium text-slate-400">sst</span></span>
        </a>
        <button type="button" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 lg:hidden" data-sidebar-close aria-label="Cerrar menú"><?= icon('x', 'h-5 w-5') ?></button>
    </div>

    <div class="mx-1 mt-6 rounded-2xl bg-slate-50 p-3 ring-1 ring-slate-200/60">
        <div class="flex items-center gap-2.5">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white text-blue-600 shadow-sm ring-1 ring-slate-200/70"><?= icon($role === 'SuperAdmin' ? 'shield-plain' : 'building', 'h-4 w-4') ?></span>
            <div class="min-w-0">
                <p class="truncate text-xs font-semibold text-slate-800"><?= e($role === 'SuperAdmin' ? 'Consola SaaS' : ($user['razonsocial'] ?? 'Mi empresa')) ?></p>
                <p class="mt-0.5 text-[10px] font-medium uppercase tracking-wider text-slate-400"><?= $role === 'SuperAdmin' ? 'Vista global' : e($role === 'Trabajador' ? 'Portal de persona' : App\Services\ModuleAccess::shortLabel($planModule)) ?></p>
            </div>
        </div>
    </div>

    <nav class="mt-5 flex-1 space-y-5 overflow-y-auto px-1 pb-4">
        <?php foreach ($navigation as $group): ?>
            <div>
                <p class="mb-2 px-3 text-[9px] font-bold uppercase tracking-[0.16em] text-slate-400"><?= e($group['label']) ?></p>
                <div class="space-y-1">
                    <?php foreach ($group['items'] as $item): ?>
                        <?php $path = strtok($item[1], '#'); ?>
                        <a href="<?= e(url($item[1])) ?>" class="sidebar-link<?= nav_active($path, $item[3] ?? false) ?>">
                            <?= icon($item[2], 'h-[18px] w-[18px] shrink-0') ?>
                            <span><?= e($item[0]) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </nav>

    <div class="mx-1 rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-50/70 p-3.5 ring-1 ring-blue-100/70">
        <div class="flex items-center gap-2 text-blue-700"><?= icon('info', 'h-4 w-4') ?><p class="text-[11px] font-semibold">Gestión con criterio técnico</p></div>
        <p class="mt-2 text-[10px] leading-4 text-blue-900/55">Los borradores deben validarse con los riesgos reales y los requisitos vigentes aplicables.</p>
    </div>
</aside>
