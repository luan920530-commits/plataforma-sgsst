<div class="page-heading">
    <div>
        <span class="eyebrow">Seguridad vial · 24 pasos</span>
        <h1>Plan Estratégico de Seguridad Vial</h1>
        <p>Biblioteca PESV recuperada del paquete suministrado, deduplicada y preparada para diligenciamiento por empresa.</p>
    </div>
    <a href="<?= e(url('/procedimientos')) ?>" class="btn-secondary"><?= icon('clipboard', 'h-4 w-4') ?> Procedimientos y kits</a>
</div>

<div class="mt-5 rounded-2xl <?= $recommended ? 'bg-blue-50 text-blue-800 ring-blue-100' : 'bg-slate-50 text-slate-700 ring-slate-200' ?> p-4 text-[11px] leading-5 ring-1">
    <div class="flex gap-2"><?= icon('info', 'mt-0.5 h-4 w-4 shrink-0') ?><p><strong><?= $recommended ? 'Kit sugerido para tu actividad de transporte.' : 'Módulo PESV disponible para revisión.' ?></strong> <?= $recommended ? 'El asistente genera el índice como borrador; completa el plan con tus condiciones reales antes de aprobarlo.' : 'Confirma la aplicabilidad según desplazamientos, flota, contratistas y operación. Tener el módulo no acredita cumplimiento.' ?></p></div>
</div>

<section class="ios-card mt-5 p-5 sm:p-6">
    <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
        <div class="flex items-start gap-4">
            <span class="metric-icon h-12 w-12 shrink-0 bg-blue-50 text-blue-600"><?= icon('shield-plain', 'h-5 w-5') ?></span>
            <div><span class="eyebrow">Documento rector editable</span><h2 class="mt-1 text-lg font-semibold">Índice del PESV</h2><p class="mt-2 max-w-2xl text-xs leading-6 text-slate-500">Organiza los 24 pasos y las 97 plantillas únicas. Se guarda como documento independiente del tenant, con editor visual, versiones y aprobación humana.</p></div>
        </div>
        <div class="shrink-0">
            <?php if ($planDocument): ?>
                <a href="<?= e(url('/documentos/' . $planDocument['id'])) ?>" class="btn-primary"><?= icon('edit', 'h-4 w-4') ?> Abrir PESV · <?= e($planDocument['estado']) ?></a>
            <?php else: ?>
                <form method="post" action="<?= e(url('/procedimientos/generar')) ?>"><?= csrf_field() ?><input type="hidden" name="kit" value="pesv"><input type="hidden" name="return" value="/pesv"><button class="btn-primary" type="submit"><?= icon('document', 'h-4 w-4') ?> Generar borrador PESV</button></form>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
    <?php foreach ([['Plantillas únicas', $stats['templates']], ['Pasos', $stats['steps']], ['Formularios', $stats['forms']], ['Documentos fuente', $stats['documents']], ['Ubicaciones', $stats['source_entries']], ['Duplicados unificados', $stats['duplicate_entries']]] as $metric): ?>
        <div class="ios-card p-4"><p class="text-[11px] text-slate-400"><?= e($metric[0]) ?></p><p class="mt-2 text-2xl font-semibold"><?= e($metric[1]) ?></p></div>
    <?php endforeach; ?>
</section>

<details class="mt-5 rounded-2xl bg-amber-50 p-4 text-[11px] leading-5 text-amber-800 ring-1 ring-amber-100">
    <summary class="cursor-pointer font-semibold">Seguridad y alcance de la conversión</summary>
    <div class="mt-3 space-y-2 pl-1">
        <p>Se detectaron vínculos externos en <?= (int) $stats['security']['files_with_external_links'] ?> archivos Excel. Sus relaciones, resultados en caché y rutas externas no se copiaron al módulo. La auditoría del paquete no encontró macros, ActiveX ni objetos OLE.</p>
        <p>Se preservaron <?= number_format((int) $stats['formulas'], 0, ',', '.') ?> fórmulas locales. Las celdas de formato vacío residual fuera del área útil fueron excluidas sin recortar valores ni fórmulas; cada plantilla muestra sus advertencias.</p>
        <p>Las imágenes, gráficos y algunas características de Office pueden requerir contraste con la fuente. Ninguna conversión equivale a aprobación, verificación legal o certificación.</p>
    </div>
</details>

<section class="mt-7">
    <div class="flex flex-wrap items-end justify-between gap-3"><div><span class="eyebrow">Ruta documental</span><h2 class="mt-1 text-lg font-semibold">Los 24 pasos del paquete</h2></div><span class="text-[11px] text-slate-400">Selecciona un paso para filtrar sus recursos</span></div>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <?php foreach ($steps as $step): ?>
            <a href="<?= e(url('/pesv?paso=' . $step['step'])) ?>" class="ios-card p-4 transition hover:-translate-y-0.5 hover:shadow-ios <?= (int) $filters['paso'] === (int) $step['step'] ? 'ring-2 ring-blue-500' : '' ?>">
                <div class="flex items-center justify-between gap-3"><span class="badge badge-blue">Paso <?= (int) $step['step'] ?></span><span class="text-[10px] text-slate-400"><?= (int) $step['templates'] ?> recurso(s)</span></div>
                <h3 class="mt-3 text-xs font-semibold leading-5 text-slate-700"><?= e($step['name']) ?></h3>
                <p class="mt-2 text-[10px] text-slate-400"><?= e($step['phase']) ?></p>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<form method="get" action="<?= e(url('/pesv')) ?>" class="ios-card mt-7 flex flex-wrap items-end gap-3 p-4">
    <div class="min-w-[220px] flex-1"><label class="label" for="q">Buscar</label><input id="q" name="q" class="input" maxlength="160" value="<?= e($filters['q']) ?>" placeholder="Título, código, carpeta o paso"></div>
    <div><label class="label" for="paso">Paso</label><select id="paso" name="paso" class="input !w-auto"><option value="0">Todos</option><?php foreach ($steps as $step): ?><option value="<?= (int) $step['step'] ?>"<?= selected((string) $filters['paso'], (string) $step['step']) ?>><?= (int) $step['step'] ?> · <?= e($step['name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="label" for="tipo">Tipo</label><select id="tipo" name="tipo" class="input !w-auto"><option value="">Todos</option><option value="document"<?= selected($filters['tipo'], 'document') ?>>Documentos</option><option value="form"<?= selected($filters['tipo'], 'form') ?>>Formularios</option></select></div>
    <button class="btn-primary h-10" type="submit">Filtrar</button><?php if (array_filter($filters)): ?><a href="<?= e(url('/pesv')) ?>" class="btn-ghost h-10">Limpiar</a><?php endif; ?>
</form>

<div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-400"><p><?= count($items) ?> resultado(s) · recursos editables y aislados por empresa</p><p>Las respuestas “Completado” requieren revisión humana</p></div>
<section class="ios-card mt-3 overflow-hidden">
    <?php if ($items === []): ?>
        <div class="empty-state"><span class="empty-icon"><?= icon('search', 'h-7 w-7') ?></span><h2>Sin resultados</h2><p>Cambia los filtros para consultar otro recurso PESV.</p></div>
    <?php else: ?>
        <div class="divide-y divide-slate-100">
            <?php foreach ($items as $item): ?>
                <article class="flex flex-col gap-3 p-4 transition hover:bg-slate-50/70 sm:p-5">
                    <div class="flex items-start gap-3">
                        <span class="metric-icon h-10 w-10 shrink-0 <?= $item['kind'] === 'form' ? 'bg-emerald-50 text-emerald-600' : 'bg-blue-50 text-blue-600' ?>"><?= icon($item['kind'] === 'form' ? 'grid' : 'document', 'h-4 w-4') ?></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2"><a href="<?= e(url('/pesv/' . $item['number'])) ?>" class="text-sm font-semibold leading-5 text-slate-800 hover:text-blue-700"><?= e($item['title']) ?></a><span class="badge badge-gray"><?= e(strtoupper($item['extension'])) ?></span><?php if ($item['warnings']): ?><span class="badge badge-amber" title="<?= e(implode(' ', $item['warnings'])) ?>">Nota de conversión</span><?php endif; ?></div>
                            <p class="mt-1 text-[11px] leading-5 text-slate-400"><?= e($item['code']) ?> · Paso(s) <?= e(implode(', ', $item['steps'])) ?> · <?= e($item['module']) ?></p>
                            <div class="mt-2 flex flex-wrap gap-3 text-[10px] text-slate-400"><a href="<?= e(url($item['route'])) ?>" class="text-link">Abrir módulo relacionado</a><?php if ($item['duplicate_entries']): ?><span><?= $item['duplicate_entries'] + 1 ?> ubicaciones idénticas consolidadas</span><?php endif; ?><?php if (!empty($generated[$item['id']])): ?><span class="text-blue-600"><?= $generated[$item['id']] ?> borrador generado</span><?php endif; ?><?php if (!empty($responses[$item['id']])): ?><span class="text-emerald-600"><?= $responses[$item['id']] ?> registro(s)</span><?php endif; ?></div>
                        </div>
                        <a href="<?= e(url('/pesv/' . $item['number'])) ?>" class="btn-secondary h-9 shrink-0 text-xs"><?= $item['kind'] === 'form' ? 'Diligenciar' : 'Revisar' ?></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
