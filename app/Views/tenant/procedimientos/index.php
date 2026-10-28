<div class="page-heading">
    <div><span class="eyebrow">Control operacional</span><h1>Procedimientos y formatos</h1><p>Kits para la actividad económica, programas operativos y tareas de alto riesgo, conectados con la biblioteca y los permisos de trabajo.</p></div>
    <a href="<?= e(url('/documentos')) ?>" class="btn-secondary"><?= icon('documents', 'h-4 w-4') ?> Biblioteca documental</a>
</div>
<div class="mt-5 rounded-2xl bg-blue-50 p-4 text-[11px] leading-5 text-blue-800 ring-1 ring-blue-100"><div class="flex gap-2"><?= icon('info', 'mt-0.5 h-4 w-4 shrink-0') ?><p><strong>Actividad seleccionada:</strong> <?= e($activity['descripcion']) ?> · CIIU <?= e($activity['codigo_ciuu']) ?>. Los kits sugeridos usan el perfil base registrado; confirma su aplicabilidad con la IPEVR y las tareas reales.</p></div></div>
<div class="mt-6 grid gap-5 lg:grid-cols-2">
    <?php foreach ($groups as $key => $group): ?>
        <?php $generated = count(array_filter($group['documentos'], static fn($document) => $document['registro'] !== null)); ?>
        <section id="kit-<?= e($key) ?>" class="ios-card flex scroll-mt-24 flex-col p-5 sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div><div class="flex flex-wrap items-center gap-2"><h2 class="text-base font-semibold text-slate-800"><?= e($group['nombre']) ?></h2><?php if ($group['recomendado']): ?><span class="badge badge-blue">Sugerido</span><?php endif; ?></div><p class="mt-2 text-xs leading-6 text-slate-500"><?= e($group['descripcion']) ?></p></div>
                <span class="metric-icon h-10 w-10 shrink-0 bg-violet-50 text-violet-600"><?= icon($key === 'ast' ? 'clipboard' : ($key === 'pesv' ? 'shield-plain' : 'document'), 'h-4 w-4') ?></span>
            </div>
            <p class="mt-3 rounded-xl bg-slate-50 p-3 text-[11px] leading-5 text-slate-500"><?= e($group['aplicabilidad']) ?></p>
            <div class="mt-4 flex-1 space-y-2">
                <?php foreach ($group['documentos'] as $document): ?>
                    <div class="flex items-center gap-3 rounded-xl border border-slate-100 p-3">
                        <span class="metric-icon h-8 w-8 shrink-0 bg-slate-100 text-slate-500"><?= icon(in_array($document['tipo'], ['Procedimiento', 'Programa', 'Plan'], true) ? 'book' : 'clipboard', 'h-3.5 w-3.5') ?></span>
                        <div class="min-w-0 flex-1"><p class="text-xs font-semibold leading-5 text-slate-700"><?= e($document['nombre']) ?></p><p class="mt-1 font-mono text-[10px] text-slate-400"><?= e($document['codigo']) ?></p></div>
                        <?php if ($document['registro']): ?><a href="<?= e(url('/documentos/' . $document['registro']['id'])) ?>" class="badge <?= status_class($document['registro']['estado']) ?>" title="Abrir documento"><?= e($document['registro']['estado']) ?></a><?php else: ?><span class="badge badge-gray">Sin generar</span><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-5 flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
                <div class="flex flex-wrap items-center gap-3"><span class="text-[11px] text-slate-400"><?= $generated ?> de <?= count($group['documentos']) ?> generado(s)</span><?php if (!empty($group['related_route'])): ?><a href="<?= e(url($group['related_route'])) ?>" class="text-link"><?= $key === 'limpieza' ? 'Abrir registro sanitario' : 'Abrir módulo relacionado' ?></a><?php endif; ?></div>
                <?php if ($generated < count($group['documentos'])): ?><form method="post" action="<?= e(url('/procedimientos/generar')) ?>"><?= csrf_field() ?><input type="hidden" name="kit" value="<?= e($key) ?>"><button class="btn-primary" type="submit">Generar borradores</button></form><?php else: ?><span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600"><?= icon('check-circle', 'h-4 w-4') ?> Kit generado</span><?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
<div class="mt-6 grid gap-4 md:grid-cols-2">
    <a href="<?= e(url('/permisos-trabajo')) ?>" class="ios-card flex items-center gap-4 p-5 transition hover:-translate-y-0.5 hover:shadow-ios"><span class="metric-icon bg-amber-50 text-amber-600"><?= icon('shield-plain', 'h-5 w-5') ?></span><div><h2 class="text-sm font-semibold">Permisos de trabajo</h2><p class="mt-1 text-xs leading-5 text-slate-400">Autoriza y cierra cada tarea concreta. Un procedimiento general no reemplaza el permiso.</p></div></a>
    <a href="<?= e(url('/matriz-ipevr')) ?>" class="ios-card flex items-center gap-4 p-5 transition hover:-translate-y-0.5 hover:shadow-ios"><span class="metric-icon bg-blue-50 text-blue-600"><?= icon('grid', 'h-5 w-5') ?></span><div><h2 class="text-sm font-semibold">Matriz IPEVR</h2><p class="mt-1 text-xs leading-5 text-slate-400">Verifica peligros y controles antes de aprobar los procedimientos generados.</p></div></a>
</div>
