<div class="page-heading">
    <div><span class="eyebrow">SG-SST por estándares</span><h1>Biblioteca documental y formularios</h1><p>Contenido recuperado del ZIP suministrado, comparado, deduplicado y conectado con los módulos de tu empresa.</p></div>
    <span class="badge badge-blue"><?= $stats['total'] ?> plantillas únicas</span>
</div>
<div class="mt-5 rounded-2xl bg-amber-50 p-4 text-[11px] leading-5 text-amber-800 ring-1 ring-amber-100"><div class="flex gap-2"><?= icon('info', 'mt-0.5 h-4 w-4 shrink-0') ?><p><strong>Material fuente para revisión técnica.</strong> La extracción no acredita cumplimiento. Adapta los textos y formularios a tus procesos, verifica la normatividad vigente y conserva la aprobación humana.</p></div></div>
<section class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
    <?php foreach ([['Plantillas', $stats['total']], ['Documentos', $stats['documents']], ['Formularios', $stats['forms']], ['Estándares', $stats['standards'] . '/60'], ['Referencias ZIP', $stats['entries']], ['Duplicados unificados', $stats['duplicates']]] as $metric): ?><div class="ios-card p-4"><p class="text-[11px] text-slate-400"><?= e($metric[0]) ?></p><p class="mt-2 text-2xl font-semibold"><?= e($metric[1]) ?></p></div><?php endforeach; ?>
</section>
<form method="get" action="<?= e(url('/biblioteca-sgsst')) ?>" class="ios-card mt-6 flex flex-wrap items-end gap-3 p-4">
    <div class="min-w-[220px] flex-1"><label class="label" for="q">Buscar</label><input id="q" name="q" class="input" maxlength="160" value="<?= e($filters['q']) ?>" placeholder="Título, módulo, código o carpeta"></div>
    <div><label class="label" for="estandar">Estándar</label><select id="estandar" name="estandar" class="input !w-auto"><option value="0">Todos</option><?php for ($number = 1; $number <= 60; $number++): ?><option value="<?= $number ?>"<?= selected((string) $filters['estandar'], (string) $number) ?>><?= $number ?></option><?php endfor; ?></select></div>
    <div><label class="label" for="tipo">Tipo</label><select id="tipo" name="tipo" class="input !w-auto"><option value="">Todos</option><option value="document"<?= selected($filters['tipo'], 'document') ?>>Documentos</option><option value="form"<?= selected($filters['tipo'], 'form') ?>>Formularios</option></select></div>
    <button class="btn-primary h-10" type="submit">Filtrar</button><?php if (array_filter($filters)): ?><a href="<?= e(url('/biblioteca-sgsst')) ?>" class="btn-ghost h-10">Limpiar</a><?php endif; ?>
</form>
<div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-400"><p><?= count($items) ?> resultado(s) · <?= $stats['formulas'] ?> fórmulas preservadas</p><p><?= $stats['ignored'] ?> metadatos/temporales excluidos · <?= $stats['warnings'] ?> plantillas con notas de conversión</p></div>
<section class="ios-card mt-3 overflow-hidden">
    <?php if ($items === []): ?><div class="empty-state"><span class="empty-icon"><?= icon('search', 'h-7 w-7') ?></span><h2>Sin resultados</h2><p>Cambia los filtros para consultar otra parte de la biblioteca.</p></div><?php else: ?>
    <div class="divide-y divide-slate-100">
        <?php foreach ($items as $item): ?><article class="flex flex-col gap-3 p-4 transition hover:bg-slate-50/70 sm:p-5">
            <div class="flex items-start gap-3"><span class="metric-icon h-10 w-10 shrink-0 <?= $item['kind'] === 'form' ? 'bg-emerald-50 text-emerald-600' : 'bg-blue-50 text-blue-600' ?>"><?= icon($item['kind'] === 'form' ? 'grid' : 'document', 'h-4 w-4') ?></span>
                <div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><a href="<?= e(url('/biblioteca-sgsst/' . $item['number'])) ?>" class="text-sm font-semibold leading-5 text-slate-800 hover:text-blue-700"><?= e($item['title']) ?></a><span class="badge badge-gray"><?= e(strtoupper($item['extension'])) ?></span><?php if ($item['warnings']): ?><span class="badge badge-amber" title="<?= e(implode(' ', $item['warnings'])) ?>">Nota de conversión</span><?php endif; ?></div>
                    <p class="mt-1 text-[11px] leading-5 text-slate-400"><?= e($item['code']) ?> · <?= e($item['module']) ?> · Estándar(es) <?= e(implode(', ', $item['standards'])) ?></p>
                    <div class="mt-2 flex flex-wrap gap-3 text-[10px] text-slate-400"><a href="<?= e(url($item['route'])) ?>" class="text-link">Abrir módulo relacionado</a><?php if ($item['duplicate_entries']): ?><span><?= $item['duplicate_entries'] + 1 ?> ubicaciones idénticas consolidadas</span><?php endif; ?><?php if (!empty($generated[$item['id']])): ?><span class="text-blue-600"><?= $generated[$item['id']] ?> borrador generado</span><?php endif; ?><?php if (!empty($responses[$item['id']])): ?><span class="text-emerald-600"><?= $responses[$item['id']] ?> registro(s)</span><?php endif; ?></div>
                </div><a href="<?= e(url('/biblioteca-sgsst/' . $item['number'])) ?>" class="btn-secondary h-9 shrink-0 text-xs"><?= $item['kind'] === 'form' ? 'Diligenciar' : 'Revisar' ?></a>
            </div>
        </article><?php endforeach; ?>
    </div><?php endif; ?>
</section>
