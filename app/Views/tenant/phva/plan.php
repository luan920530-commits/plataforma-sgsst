<div class="page-heading">
    <div><span class="eyebrow">Planear · Ejecutar · Verificar</span><h1>Plan Anual de Trabajo</h1><p>Objetivos convertidos en actividades con responsables, recursos y fechas.</p></div>
    <a href="#nueva-actividad" class="btn-primary"><?= icon('plus', 'h-4 w-4') ?> Nueva actividad</a>
</div>
<section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <?php foreach ([['Actividades', (int) $stats['total'], 'calendar'], ['Completadas', (int) $stats['completadas'], 'check-circle'], ['Vencidas', (int) $stats['vencidas'], 'alert'], ['Presupuesto', money($stats['presupuesto']), 'chart']] as $item): ?>
        <div class="ios-card p-4"><div class="flex items-center gap-2 text-slate-400"><?= icon($item[2], 'h-4 w-4') ?><p class="text-[11px] font-medium"><?= e($item[0]) ?></p></div><p class="mt-3 truncate text-xl font-semibold tracking-tight"><?= e($item[1]) ?></p></div>
    <?php endforeach; ?>
</section>
<section class="ios-card mt-6 overflow-hidden">
    <div class="card-heading">
        <div><h2>Cronograma de actividades</h2><p>Abre cada registro para consultar su detalle, editarlo o eliminarlo.</p></div>
        <form method="get" action="<?= e(url('/plan-anual')) ?>" class="flex flex-wrap items-center gap-2">
            <select name="estado" class="input h-9 !w-auto text-xs" aria-label="Filtrar por estado"><option value="">Todos</option><?php foreach (['Pendiente','En Proceso','Completado'] as $state): ?><option<?= selected($status, $state) ?>><?= $state ?></option><?php endforeach; ?></select>
            <button class="btn-secondary h-9 px-3" type="submit">Filtrar</button>
            <?php if ($status !== ''): ?><a href="<?= e(url('/plan-anual')) ?>" class="text-link text-xs">Limpiar</a><?php endif; ?>
        </form>
    </div>
    <?php if ($activities === []): ?>
        <div class="empty-state"><span class="empty-icon"><?= icon('calendar', 'h-7 w-7') ?></span><h3>No hay actividades en esta vista</h3><p>Agrega una actividad o cambia el filtro seleccionado.</p></div>
    <?php else: ?>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Actividad</th><th>Fase</th><th>Responsable</th><th>Fechas</th><th>Presupuesto</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody><?php foreach ($activities as $activity): ?>
                <tr class="<?= is_overdue($activity) ? 'bg-rose-50/30' : '' ?>">
                    <td><a href="<?= e(url('/plan-anual/' . $activity['id'])) ?>" class="block max-w-[330px] whitespace-pre-line text-xs font-medium leading-5 text-slate-700 hover:text-blue-700 hover:underline"><?= e($activity['actividad']) ?></a><span class="mt-1 block text-[10px] text-slate-400">Registro #<?= (int) $activity['id'] ?></span></td>
                    <td><span class="phase-badge phase-<?= strtolower($activity['fase_phva']) ?>"><?= e($activity['fase_phva']) ?></span></td>
                    <td><p class="max-w-[170px] text-xs leading-5 text-slate-600"><?= e($activity['responsable']) ?></p></td>
                    <td><time class="whitespace-nowrap text-xs <?= is_overdue($activity) ? 'font-semibold text-rose-600' : 'text-slate-600' ?>"><?= format_date($activity['fecha_programada']) ?></time><?php if (is_overdue($activity)): ?><span class="mt-1 block text-[10px] text-rose-500">Vencida</span><?php endif; ?><?php if ($activity['fecha_ejecucion']): ?><span class="mt-1 block whitespace-nowrap text-[10px] text-emerald-600">Ejecutada: <?= format_date($activity['fecha_ejecucion']) ?></span><?php endif; ?></td>
                    <td><span class="whitespace-nowrap text-xs text-slate-600"><?= money($activity['presupuesto']) ?></span></td>
                    <td><form method="post" action="<?= e(url('/plan-anual/' . $activity['id'] . '/estado')) ?>" class="flex min-w-[190px] gap-2"><?= csrf_field() ?><select name="estado" class="input h-8 !w-auto flex-1 px-2 text-[11px]" aria-label="Estado de la actividad <?= (int) $activity['id'] ?>"><?php foreach (['Pendiente','En Proceso','Completado'] as $state): ?><option<?= selected($activity['estado'], $state) ?>><?= $state ?></option><?php endforeach; ?></select><button type="submit" class="btn-secondary h-8 px-2.5 text-[11px]">Guardar</button></form></td>
                    <td><div class="flex min-w-[165px] flex-wrap items-center gap-x-3 gap-y-2 text-[11px] font-medium"><a href="<?= e(url('/plan-anual/' . $activity['id'])) ?>" class="inline-flex items-center gap-1 text-slate-600 hover:text-blue-700 hover:underline"><?= icon('eye', 'h-3.5 w-3.5') ?> Ver</a><a href="<?= e(url('/plan-anual/' . $activity['id'] . '/editar')) ?>" class="inline-flex items-center gap-1 text-blue-600 hover:underline"><?= icon('edit', 'h-3.5 w-3.5') ?> Editar</a><form method="post" action="<?= e(url('/plan-anual/' . $activity['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar esta actividad del Plan Anual de Trabajo? Conserva por separado los soportes que deban mantenerse."><?= csrf_field() ?><button type="submit" class="inline-flex items-center gap-1 text-rose-600 hover:underline"><?= icon('x', 'h-3.5 w-3.5') ?> Eliminar</button></form></div></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>
<details id="nueva-actividad" class="ios-card mt-6 overflow-hidden"<?= $errors !== [] ? ' open' : '' ?>>
    <summary class="cursor-pointer list-none p-5"><div class="section-heading"><span class="section-icon"><?= icon('plus', 'h-5 w-5') ?></span><div><h2>Programar una nueva actividad</h2><p>La actividad se creará como Pendiente y quedará en auditoría.</p></div></div></summary>
    <form method="post" action="<?= e(url('/plan-anual')) ?>" class="grid gap-5 border-t border-slate-100 p-5 sm:grid-cols-2">
        <?= csrf_field() ?>
        <div class="sm:col-span-2"><label class="label" for="actividad">Actividad *</label><textarea id="actividad" name="actividad" class="input min-h-24" maxlength="300" placeholder="Describe un resultado verificable" required><?= e(old('actividad')) ?></textarea><?= field_error('actividad') ?></div>
        <div><label class="label" for="responsable">Responsable *</label><input id="responsable" name="responsable" class="input" maxlength="250" value="<?= e(old('responsable', $user['nombre'])) ?>" required><?= field_error('responsable') ?></div>
        <div><label class="label" for="fecha_programada">Fecha programada *</label><input id="fecha_programada" name="fecha_programada" type="date" class="input" value="<?= e(old('fecha_programada', date('Y-m-d', strtotime('+30 days')))) ?>" required><?= field_error('fecha_programada') ?></div>
        <div><label class="label" for="presupuesto">Presupuesto COP</label><input id="presupuesto" name="presupuesto" type="number" min="0" step="0.01" class="input" value="<?= e(old('presupuesto', 0)) ?>"><?= field_error('presupuesto') ?></div>
        <div><label class="label" for="fase_phva">Fase PHVA *</label><select id="fase_phva" name="fase_phva" class="input"><?php foreach (['Planear','Hacer','Verificar','Actuar'] as $phase): ?><option<?= selected(old('fase_phva', 'Planear'), $phase) ?>><?= $phase ?></option><?php endforeach; ?></select><?= field_error('fase_phva') ?></div>
        <div class="sm:col-span-2 flex justify-end"><button type="submit" class="btn-primary"><?= icon('plus', 'h-4 w-4') ?> Agregar al PAT</button></div>
    </form>
</details>
<div class="mt-5 rounded-2xl bg-blue-50 p-4 text-[11px] leading-5 text-blue-800 ring-1 ring-blue-100"><strong>Buena práctica:</strong> al marcar una actividad como completada, conserva los soportes de ejecución y revisa si logró el resultado esperado. Desde el detalle puedes registrar o corregir la fecha real de ejecución.</div>
