<div class="mx-auto max-w-2xl">
    <section class="ios-card p-6 text-center sm:p-9">
        <span class="empty-icon mx-auto"><?= icon('lock', 'h-7 w-7') ?></span>
        <span class="eyebrow mt-5 block"><?= e(App\Services\ModuleAccess::shortLabel($module)) ?></span>
        <h1 class="mt-3 text-2xl font-semibold tracking-tight">Tu empresa tiene un módulo especializado.</h1>
        <p class="mt-3 text-sm leading-6 text-slate-500">El plan asignado a <?= e($tenant['razonsocial'] ?? 'tu empresa') ?> no incluye el portal SG-SST general para trabajadores. No se han cargado inducciones, autoreportes, capacitaciones ni políticas de otros módulos.</p>
        <p class="mt-3 text-xs leading-5 text-slate-400">Si necesitas gestionar este módulo, solicita al administrador una cuenta de Responsable SST o una revisión del plan.</p>
        <a href="<?= e(url('/cuenta/seguridad')) ?>" class="btn-secondary mt-6"><?= icon('lock', 'h-4 w-4') ?> Seguridad de mi cuenta</a>
    </section>
</div>
