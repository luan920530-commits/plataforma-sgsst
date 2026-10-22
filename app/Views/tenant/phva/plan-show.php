<?php $statusValue = old('estado', $activity['estado']); $executionValue = old('fecha_ejecucion', $activity['fecha_ejecucion'] ?: date('Y-m-d')); ?>
<div class="page-heading">
    <div><a href="<?= e(url('/plan-anual')) ?>" class="text-link mb-3"><?= icon('arrow-left', 'h-3.5 w-3.5') ?> Plan Anual de Trabajo</a><span class="eyebrow">Actividad PAT #<?= (int) $activity['id'] ?></span><div class="flex flex-wrap items-center gap-3"><h1>Detalle de actividad</h1><span class="badge <?= status_class($activity['estado']) ?>"><?= e($activity['estado']) ?></span></div><p>Consulta independiente de la programación, los recursos y el avance registrado.</p></div>
    <a href="<?= e(url('/plan-anual/' . $activity['id'] . '/editar')) ?>" class="btn-primary"><?= icon('edit', 'h-4 w-4') ?> Editar actividad</a>
</div>
<div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
    <section class="ios-card p-5 sm:p-6">
        <div class="flex flex-wrap items-center gap-2"><span class="phase-badge phase-<?= strtolower($activity['fase_phva']) ?>"><?= e($activity['fase_phva']) ?></span><?php if (is_overdue($activity)): ?><span class="badge badge-red">Vencida</span><?php endif; ?></div>
        <h2 class="mt-4 text-[10px] font-bold uppercase tracking-wider text-slate-400">Actividad</h2>
        <p class="mt-2 whitespace-pre-line text-base font-medium leading-7 text-slate-800"><?= e($activity['actividad']) ?></p>
        <dl class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-[11px] text-slate-400">Responsable</dt><dd class="mt-1.5 text-sm font-medium leading-6 text-slate-700"><?= e($activity['responsable']) ?></dd></div>
            <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-[11px] text-slate-400">Presupuesto asignado</dt><dd class="mt-1.5 text-sm font-semibold text-slate-700"><?= money($activity['presupuesto']) ?></dd></div>
            <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-[11px] text-slate-400">Fecha programada</dt><dd class="mt-1.5 text-sm font-medium text-slate-700"><?= format_date($activity['fecha_programada'], true) ?></dd></div>
            <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-[11px] text-slate-400">Fecha real de ejecución</dt><dd class="mt-1.5 text-sm font-medium <?= $activity['fecha_ejecucion'] ? 'text-emerald-700' : 'text-slate-400' ?>"><?= format_date($activity['fecha_ejecucion'], true) ?></dd></div>
            <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-[11px] text-slate-400">Fase PHVA</dt><dd class="mt-1.5 text-sm font-medium text-slate-700"><?= e($activity['fase_phva']) ?></dd></div>
            <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-[11px] text-slate-400">Fecha de creación</dt><dd class="mt-1.5 text-sm font-medium text-slate-700"><?= format_date($activity['created_at'], true) ?></dd></div>
        </dl>
        <div class="mt-5 rounded-2xl bg-blue-50 p-4 text-[11px] leading-5 text-blue-800 ring-1 ring-blue-100">Los cambios de datos, avance y eliminación quedan registrados en auditoría. Los soportes de ejecución se conservan en el archivo documental de la empresa.</div>
    </section>
    <aside class="space-y-5">
        <section class="ios-card p-5">
            <h2 class="text-sm font-semibold">Actualizar avance</h2><p class="mt-1 text-[11px] leading-5 text-slate-400">Registra el estado y, cuando corresponda, la fecha real de ejecución.</p>
            <form method="post" action="<?= e(url('/plan-anual/' . $activity['id'] . '/estado')) ?>" class="mt-4 space-y-4">
                <?= csrf_field() ?><input type="hidden" name="origen" value="detalle">
                <div><label class="label" for="estado">Estado</label><select id="estado" name="estado" class="input"><?php foreach (['Pendiente','En Proceso','Completado'] as $state): ?><option<?= selected($statusValue, $state) ?>><?= $state ?></option><?php endforeach; ?></select><?= field_error('estado') ?></div>
                <div><label class="label" for="fecha_ejecucion">Fecha de ejecución</label><input id="fecha_ejecucion" name="fecha_ejecucion" type="date" class="input" value="<?= e($executionValue) ?>"><?= field_error('fecha_ejecucion') ?><p class="mt-2 text-[10px] leading-4 text-slate-400">Se aplica al completar. Si la actividad vuelve a Pendiente o En Proceso, la fecha de ejecución se retira.</p></div>
                <button type="submit" class="btn-primary w-full"><?= icon('check', 'h-4 w-4') ?> Guardar avance</button>
            </form>
        </section>
        <section class="ios-card p-5">
            <h2 class="text-sm font-semibold">Gestión del registro</h2>
            <div class="mt-4 space-y-2"><a href="<?= e(url('/plan-anual/' . $activity['id'] . '/editar')) ?>" class="btn-secondary w-full"><?= icon('edit', 'h-4 w-4') ?> Editar todos los datos</a><a href="<?= e(url('/documentos')) ?>" class="btn-secondary w-full"><?= icon('documents', 'h-4 w-4') ?> Abrir archivo documental</a></div>
            <form method="post" action="<?= e(url('/plan-anual/' . $activity['id'] . '/eliminar')) ?>" class="mt-4 border-t border-slate-100 pt-4" data-confirm="¿Eliminar definitivamente esta actividad del Plan Anual de Trabajo? Conserva por separado los soportes que deban mantenerse."><?= csrf_field() ?><button type="submit" class="btn-danger-soft w-full"><?= icon('x', 'h-4 w-4') ?> Eliminar actividad</button></form>
        </section>
    </aside>
</div>
