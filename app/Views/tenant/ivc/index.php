<div class="page-heading">
    <div><span class="eyebrow">Verificar · Actuar</span><h1>Cumplimiento sanitario IVC</h1><p>Gestiona actas, requerimientos y brechas documentales con evidencias y verificación humana.</p></div>
    <a href="<?= e(url('/ivc/nueva')) ?>" class="btn-primary"><?= icon('plus', 'h-4 w-4') ?> Registrar acta</a>
</div>
<div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <section class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Actas</p><p class="mt-2 text-2xl font-semibold"><?= (int) $stats['actas'] ?></p><p class="mt-1 text-[10px] text-slate-400">Inspecciones registradas</p></section>
    <section class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Seguimientos</p><p class="mt-2 text-2xl font-semibold"><?= (int) $stats['requerimientos'] ?></p><p class="mt-1 text-[10px] text-slate-400">Requerimientos y brechas</p></section>
    <section class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Por verificar</p><p class="mt-2 text-2xl font-semibold text-blue-600"><?= (int) $stats['por_verificar'] ?></p><p class="mt-1 text-[10px] text-slate-400">Con acciones en revisión</p></section>
    <section class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Cerrados</p><p class="mt-2 text-2xl font-semibold text-emerald-600"><?= (int) $stats['cerrados'] ?></p><p class="mt-1 text-[10px] text-slate-400">Con evidencia revisada</p></section>
</div>
<div class="mt-6 rounded-2xl bg-amber-50 p-4 text-[11px] leading-5 text-amber-900 ring-1 ring-amber-100"><div class="flex gap-2"><?= icon('alert', 'mt-0.5 h-4 w-4 shrink-0') ?><p><strong>Registrar no equivale a cumplir.</strong> Las obras, compras, capacitaciones, trámites y verificaciones deben ejecutarse realmente. Un estado interno no reemplaza el concepto ni la aceptación de la autoridad.</p></div></div>
<section class="mt-6">
    <div class="mb-4 flex items-center justify-between"><div><h2 class="text-base font-semibold">Actas registradas</h2><p class="mt-1 text-xs text-slate-400">Cada acta conserva sus propios hallazgos y trazabilidad.</p></div><a href="<?= e(url('/sanitario')) ?>" class="text-link">Abrir gestión sanitaria <?= icon('arrow-right', 'h-3.5 w-3.5') ?></a></div>
    <?php if ($actas === []): ?>
        <div class="ios-card empty-state"><span class="empty-icon"><?= icon('clipboard', 'h-7 w-7') ?></span><h3>Aún no hay actas IVC</h3><p>Registra la inspección y después agrega cada requerimiento sin alterar la fuente.</p><a href="<?= e(url('/ivc/nueva')) ?>" class="btn-primary mt-5">Registrar primera acta</a></div>
    <?php else: ?>
        <div class="space-y-4"><?php foreach ($actas as $acta): ?>
            <article class="ios-card p-5 sm:p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start">
                    <span class="metric-icon shrink-0 bg-blue-50 text-blue-600"><?= icon('clipboard', 'h-5 w-5') ?></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2"><span class="badge badge-blue"><?= e($acta['concepto']) ?></span><?php if ($acta['fecha_limite'] && strtotime($acta['fecha_limite']) < strtotime('today') && (int) $acta['pendientes'] > 0): ?><span class="badge badge-red">Plazo vencido</span><?php endif; ?></div>
                        <h3 class="mt-3 text-lg font-semibold tracking-tight"><a href="<?= e(url('/ivc/' . $acta['id'])) ?>" class="hover:text-blue-600">Acta <?= e($acta['numero_acta']) ?></a></h3>
                        <p class="mt-1 text-xs text-slate-500"><?= e($acta['autoridad']) ?> · Visita <?= e(format_date($acta['fecha_visita'])) ?></p>
                        <dl class="mt-4 grid gap-3 rounded-2xl bg-slate-50 p-4 text-xs sm:grid-cols-4">
                            <div><dt class="text-[10px] text-slate-400">Resultado</dt><dd class="mt-1 font-semibold text-slate-700"><?= $acta['porcentaje'] !== null ? e(number_format((float) $acta['porcentaje'], 2, ',', '.')) . ' %' : 'No registrado' ?></dd></div>
                            <div><dt class="text-[10px] text-slate-400">Fecha límite</dt><dd class="mt-1 font-semibold text-slate-700"><?= e(format_date($acta['fecha_limite'])) ?></dd></div>
                            <div><dt class="text-[10px] text-slate-400">Pendientes</dt><dd class="mt-1 font-semibold text-amber-600"><?= (int) $acta['pendientes'] ?></dd></div>
                            <div><dt class="text-[10px] text-slate-400">Cerrados</dt><dd class="mt-1 font-semibold text-emerald-600"><?= (int) $acta['cerrados'] ?> de <?= (int) $acta['total_requerimientos'] ?></dd></div>
                        </dl>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2 lg:w-44 lg:flex-col">
                        <a href="<?= e(url('/ivc/' . $acta['id'])) ?>" class="btn-primary">Abrir seguimiento <?= icon('arrow-right', 'h-4 w-4') ?></a>
                        <a href="<?= e(url('/ivc/' . $acta['id'] . '/editar')) ?>" class="btn-secondary"><?= icon('edit', 'h-4 w-4') ?> Editar acta</a>
                        <form method="post" action="<?= e(url('/ivc/' . $acta['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar esta acta y todos sus requerimientos? Sólo se permitirá si no tienen evidencias ni registros sanitarios vinculados."><?= csrf_field() ?><button type="submit" class="btn-danger-soft w-full">Eliminar</button></form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?></div>
    <?php endif; ?>
</section>
