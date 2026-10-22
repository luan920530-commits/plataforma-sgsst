<div class="page-heading">
    <div><span class="eyebrow">Administración multi-tenant</span><h1>Empresas</h1><p>Gestiona el ciclo de vida, la clasificación y el plan de cada organización.</p></div>
    <a href="<?= e(url('/admin/empresas/nueva')) ?>" class="btn-primary"><?= icon('plus', 'h-4 w-4') ?> Configurar empresa</a>
</div>

<section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <?php foreach ([['Registradas', (int) $stats['total'], 'building'], ['Activas', (int) $stats['activas'], 'check-circle'], ['Trabajadores', number_format((int) $stats['trabajadores']), 'users'], ['Grupo de 60', (int) $stats['grupo_60'], 'shield-plain']] as $item): ?>
        <div class="ios-card flex items-center gap-3 p-4"><span class="metric-icon h-10 w-10 bg-slate-100 text-slate-600"><?= icon($item[2], 'h-4 w-4') ?></span><div><p class="text-xl font-semibold tracking-tight"><?= e($item[1]) ?></p><p class="text-[11px] text-slate-400"><?= e($item[0]) ?></p></div></div>
    <?php endforeach; ?>
</section>

<section class="ios-card mt-6 overflow-hidden">
    <div class="border-b border-slate-100 p-4 sm:p-5">
        <form method="get" action="<?= e(url('/admin/empresas')) ?>" class="flex flex-col gap-3 md:flex-row">
            <div class="input-with-icon flex-1"><?= icon('search', 'h-4 w-4') ?><input type="search" name="q" value="<?= e($search) ?>" class="input h-10 pl-10" placeholder="Buscar por razón social, NIT o actividad…"></div>
            <select name="estado" class="input h-10 md:w-44" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="Activa"<?= selected($status, 'Activa') ?>>Activas</option><option value="Suspendida"<?= selected($status, 'Suspendida') ?>>Suspendidas</option></select>
            <button type="submit" class="btn-secondary h-10"><?= icon('filter', 'h-4 w-4') ?> Filtrar</button>
            <?php if ($search !== '' || $status !== ''): ?><a href="<?= e(url('/admin/empresas')) ?>" class="btn-ghost h-10">Limpiar</a><?php endif; ?>
        </form>
    </div>
    <?php if ($tenants === []): ?>
        <div class="empty-state"><span class="empty-icon"><?= icon('building', 'h-7 w-7') ?></span><h3>No hay empresas para mostrar</h3><p>Ajusta los filtros o configura la primera empresa para iniciar.</p><a href="<?= e(url('/admin/empresas/nueva')) ?>" class="btn-primary mt-4"><?= icon('plus', 'h-4 w-4') ?> Nueva empresa</a></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Empresa</th><th>Clasificación</th><th>Plan</th><th>Usuarios</th><th>Estado</th><th>Creada</th><th><span class="sr-only">Acciones</span></th></tr></thead>
                <tbody>
                <?php foreach ($tenants as $tenant): ?>
                    <tr>
                        <td><div class="flex items-center gap-3"><span class="company-avatar"><?= e(initials($tenant['razonsocial'])) ?></span><div><a href="<?= e(url('/admin/empresas/' . $tenant['id'])) ?>" class="font-semibold text-slate-800 hover:text-blue-600"><?= e($tenant['razonsocial']) ?></a><p class="mt-1 text-[11px] text-slate-400"><?= e($tenant['nit']) ?> · <?= e($tenant['ciudad']) ?></p></div></div></td>
                        <td><div class="flex items-center gap-2"><span class="badge badge-blue"><?= (int) $tenant['grupo_estandares'] ?></span><span class="badge <?= risk_class($tenant['nivel_riesgo']) ?>">Riesgo <?= e($tenant['nivel_riesgo']) ?></span></div><p class="mt-1.5 text-[11px] text-slate-400"><?= (int) $tenant['numero_trabajadores'] ?> trabajadores</p></td>
                        <td><p class="text-xs font-medium text-slate-600"><?= e($tenant['plan_nombre'] ?? 'Sin plan') ?></p></td>
                        <td><span class="inline-flex items-center gap-1.5 text-xs text-slate-600"><?= icon('users', 'h-3.5 w-3.5 text-slate-400') ?> <?= (int) $tenant['total_usuarios'] ?></span></td>
                        <td><span class="badge <?= status_class($tenant['estado']) ?>"><span class="badge-dot"></span><?= e($tenant['estado']) ?></span></td>
                        <td><time class="text-xs text-slate-500"><?= format_date($tenant['created_at']) ?></time></td>
                        <td><a href="<?= e(url('/admin/empresas/' . $tenant['id'])) ?>" class="btn-secondary h-8 px-3 text-xs">Gestionar <?= icon('chevron-right', 'h-3.5 w-3.5') ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-3 text-xs text-slate-400">Mostrando <?= count($tenants) ?> empresa<?= count($tenants) === 1 ? '' : 's' ?>.</div>
    <?php endif; ?>
</section>
