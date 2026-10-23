<div class="page-heading">
    <div><div class="mb-2 flex items-center gap-2"><a href="<?= e(url('/admin/empresas')) ?>" class="text-link"><?= icon('arrow-left', 'h-3.5 w-3.5') ?> Empresas</a><span class="text-slate-300">/</span><span class="text-xs text-slate-400">Tenant #<?= (int) $tenant['id'] ?></span></div><div class="flex flex-wrap items-center gap-3"><h1><?= e($tenant['razonsocial']) ?></h1><span class="badge <?= status_class($tenant['estado']) ?>"><span class="badge-dot"></span><?= e($tenant['estado']) ?></span></div><p><?= e($tenant['actividad'] ?? $tenant['sector_economico']) ?> · NIT <?= e($tenant['nit']) ?></p></div>
    <form method="post" action="<?= e(url('/admin/empresas/' . $tenant['id'] . '/estado')) ?>" data-confirm="<?= $tenant['estado'] === 'Activa' ? '¿Suspender esta empresa? Sus usuarios no podrán iniciar sesión.' : '¿Reactivar el acceso de esta empresa?' ?>"><?= csrf_field() ?><input type="hidden" name="estado" value="<?= $tenant['estado'] === 'Activa' ? 'Suspendida' : 'Activa' ?>"><button type="submit" class="<?= $tenant['estado'] === 'Activa' ? 'btn-danger-soft' : 'btn-primary' ?>"><?= icon($tenant['estado'] === 'Activa' ? 'lock' : 'check-circle', 'h-4 w-4') ?><?= $tenant['estado'] === 'Activa' ? 'Suspender empresa' : 'Reactivar empresa' ?></button></form>
</div>

<section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ([
        ['Estándares asignados', (int) $tenant['grupo_estandares'], 'Riesgo ' . $tenant['nivel_riesgo'] . ' · ' . (int) $tenant['numero_trabajadores'] . ' trabajadores', 'book'],
        ['Documentos aprobados', (int) $documentStats['aprobados'] . '/' . (int) $documentStats['total'], (int) $documentStats['borradores'] . ' pendientes de revisión', 'documents'],
        ['Avance del PAT', (int) $planStats['completadas'] . '/' . (int) $planStats['total'], money($planStats['presupuesto']) . ' presupuestados', 'calendar'],
        ['Peligros priorizados', (int) $hazardStats['prioritarios'], (int) $openFindings . ' hallazgos ACPA abiertos', 'alert'],
    ] as $metric): ?><article class="metric-card"><span class="metric-icon bg-blue-50 text-blue-600"><?= icon($metric[3], 'h-5 w-5') ?></span><p class="mt-4 text-2xl font-semibold tracking-tight"><?= e($metric[1]) ?></p><p class="mt-1 text-xs font-semibold text-slate-600"><?= e($metric[0]) ?></p><p class="mt-1 text-[11px] text-slate-400"><?= e($metric[2]) ?></p></article><?php endforeach; ?>
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(320px,0.75fr)]">
    <div class="space-y-6">
        <section class="ios-card p-5 sm:p-6">
            <div class="card-heading !border-0 !p-0"><div><h2>Identidad y clasificación</h2><p>Datos utilizados por el motor de configuración.</p></div><span class="badge badge-blue"><?= e($tenant['metodo_configuracion']) ?></span></div>
            <dl class="mt-6 grid gap-x-6 gap-y-5 sm:grid-cols-2">
                <?php foreach ([['Razón social', $tenant['razonsocial']], ['NIT', $tenant['nit']], ['Actividad CIIU', ($tenant['codigo_ciuu'] ?? 'Sin código') . ' · ' . ($tenant['actividad'] ?? 'No asignada')], ['Sector', $tenant['sector_economico']], ['Representante legal', $tenant['representante_legal']], ['Contacto', $tenant['correo_contacto']], ['Ubicación', $tenant['ciudad'] . ' · ' . $tenant['direccion']], ['Configuración generada', format_date($tenant['configuracion_generada_at'], true)]] as $item): ?><div><dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400"><?= e($item[0]) ?></dt><dd class="mt-1.5 text-sm leading-6 text-slate-700"><?= e($item[1]) ?></dd></div><?php endforeach; ?>
            </dl>
            <div class="mt-6 rounded-2xl bg-slate-50 p-4"><p class="text-xs font-semibold text-slate-600">Regla aplicada</p><p class="mt-1.5 text-xs leading-5 text-slate-500"><?= (int) $tenant['grupo_estandares'] === 7 ? 'Hasta 10 trabajadores con riesgo I, II o III: 7 estándares.' : ((int) $tenant['grupo_estandares'] === 21 ? 'Entre 11 y 50 trabajadores con riesgo I, II o III: 21 estándares.' : 'Más de 50 trabajadores o clase de riesgo IV/V: 60 estándares.') ?></p></div>
        </section>

        <section class="ios-card overflow-hidden">
            <div class="card-heading"><div><h2>Usuarios y roles</h2><p>Accesos existentes en esta empresa.</p></div><span class="badge badge-gray"><?= count($users) ?> cuentas</span></div>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Usuario</th><th>Rol</th><th>Último ingreso</th><th>Estado</th></tr></thead><tbody><?php foreach ($users as $account): ?><tr><td><div class="flex items-center gap-3"><span class="avatar h-8 w-8 text-[10px]"><?= e(initials($account['nombre'])) ?></span><div><p class="text-xs font-semibold text-slate-700"><?= e($account['nombre']) ?></p><p class="mt-0.5 text-[11px] text-slate-400"><?= e($account['email']) ?></p></div></div></td><td><span class="badge badge-gray"><?= e(role_label($account['rol'])) ?></span></td><td class="text-xs text-slate-500"><?= $account['last_login_at'] ? format_date($account['last_login_at']) : 'Sin ingresos' ?></td><td><span class="badge <?= status_class($account['activo'] ? 'Activo' : 'Suspendida') ?>"><?= $account['activo'] ? 'Activo' : 'Inactivo' ?></span></td></tr><?php endforeach; ?></tbody></table></div>
        </section>
    </div>

    <div class="space-y-6">
        <section class="ios-card p-5">
            <div class="flex items-center gap-3"><span class="metric-icon h-10 w-10 bg-violet-50 text-violet-600"><?= icon('grid', 'h-4 w-4') ?></span><div><h2 class="text-base font-semibold">Plan asignado</h2><p class="text-xs text-slate-400"><?= e($tenant['plan_nombre'] ?? 'Sin plan asignado') ?> · <?= e($tenant['plan_condicion'] ?? 'Pagado') ?></p></div></div>
            <p class="mt-3 text-[11px] font-semibold text-blue-600"><?= e(App\Services\ModuleAccess::label($tenant['plan_modulo'] ?? 'sgsst')) ?></p>
            <form method="post" action="<?= e(url('/admin/empresas/' . $tenant['id'] . '/plan')) ?>" class="mt-5 space-y-3">
                <?= csrf_field() ?>
                <div><label class="label" for="plan_id">Cambiar plan</label><select id="plan_id" name="plan_id" class="input"><?php foreach ($plans as $plan): ?><option value="<?= (int) $plan['id'] ?>"<?= selected($tenant['plan_id'], $plan['id']) ?>><?= e($plan['nombre']) ?> · <?= money($plan['precio_mensual']) ?>/mes</option><?php endforeach; ?></select></div>
                <div><label class="label" for="plan_condicion">Condición</label><select id="plan_condicion" name="plan_condicion" class="input"><option value="Pagado"<?= selected($tenant['plan_condicion'] ?? 'Pagado', 'Pagado') ?>>Pagado</option><option value="Sugerido"<?= selected($tenant['plan_condicion'] ?? 'Pagado', 'Sugerido') ?>>Sugerido</option></select><p class="mt-1 text-[10px] text-slate-400">La condición no altera el módulo habilitado.</p></div>
                <button class="btn-secondary w-full" type="submit">Actualizar plan y alcance</button>
            </form>
        </section>
        <section class="ios-card p-5">
            <h2 class="text-base font-semibold">Trazabilidad reciente</h2><p class="mt-1 text-xs text-slate-400">Actividad asociada exclusivamente a este tenant.</p>
            <div class="mt-5 space-y-4"><?php if ($audit === []): ?><p class="text-xs text-slate-400">Aún no hay eventos de auditoría.</p><?php endif; ?><?php foreach ($audit as $entry): ?><div class="relative border-l border-slate-200 pl-4"><span class="absolute -left-[4px] top-1 h-2 w-2 rounded-full bg-blue-400"></span><p class="text-xs font-semibold text-slate-700"><?= e($entry['accion']) ?></p><p class="mt-1 text-[10px] leading-4 text-slate-400"><?= e($entry['usuario'] ?? 'Sistema') ?> · <?= format_date($entry['created_at'], true) ?></p></div><?php endforeach; ?></div>
        </section>
        <div class="rounded-2xl bg-amber-50 p-4 text-[11px] leading-5 text-amber-800 ring-1 ring-amber-100"><div class="flex items-center gap-2 font-semibold"><?= icon('info', 'h-4 w-4') ?> Separación de datos</div><p class="mt-2 text-amber-700/80">Este resumen global no habilita la edición operativa desde la consola SaaS. Los documentos y registros se administran desde el portal de la empresa.</p></div>
    </div>
</div>
