<?php
$uploadLink = old('vinculo', $selectedLink !== '' ? $selectedLink : 'general:');
$reviewed = (int) $stats['revisados'];
$pending = (int) $stats['pendientes'];
?>
<div class="page-heading">
    <div><span class="eyebrow">Expediente operativo · <?= e(App\Services\ModuleAccess::shortLabel($planModule)) ?></span><h1>Centro de evidencias</h1><p>Conserva reportes, formularios, listas de asistencia, fotografías, inspecciones y demás pruebas documentales vinculadas a su registro de origen.</p></div>
    <a href="#cargar" class="btn-primary"><?= icon('upload', 'h-4 w-4') ?> Adjuntar evidencia</a>
</div>

<div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <section class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Archivos</p><p class="mt-2 text-2xl font-semibold text-slate-900"><?= (int) $stats['total'] ?></p><p class="mt-1 text-[10px] text-slate-400">En el alcance de este plan</p></section>
    <section class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Pendientes</p><p class="mt-2 text-2xl font-semibold text-amber-600"><?= $pending ?></p><p class="mt-1 text-[10px] text-slate-400">Requieren revisión humana</p></section>
    <section class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Revisados</p><p class="mt-2 text-2xl font-semibold text-emerald-600"><?= $reviewed ?></p><p class="mt-1 text-[10px] text-slate-400">Con responsable y fecha</p></section>
    <section class="metric-card"><p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Almacenamiento</p><p class="mt-2 text-2xl font-semibold text-slate-900"><?= e(number_format((int) $stats['bytes'] / 1048576, 1, ',', '.')) ?> <span class="text-xs font-normal">MB</span></p><p class="mt-1 text-[10px] text-slate-400">Archivo privado con SHA-256</p></section>
</div>

<div class="mt-6 rounded-2xl bg-blue-50 p-4 text-[11px] leading-5 text-blue-800 ring-1 ring-blue-100">
    <strong>Cargar no equivale a verificar.</strong> Toda evidencia inicia como Pendiente. La revisión humana documenta una comprobación de la empresa; no emite certificados ni cambia automáticamente el cumplimiento de un requisito.
</div>

<details id="cargar" class="ios-card mt-6 overflow-hidden"<?= $errors !== [] || $selectedLink !== '' ? ' open' : '' ?>>
    <summary class="cursor-pointer p-5 text-sm font-semibold text-slate-800">Nueva evidencia documental <span class="ml-2 text-[10px] font-normal text-slate-400">PDF, JPG o PNG · máximo 10 MB</span></summary>
    <form method="post" action="<?= e(url('/evidencias')) ?>" enctype="multipart/form-data" class="grid gap-5 border-t border-slate-100 p-5 sm:p-6 lg:grid-cols-2">
        <?= csrf_field() ?><input type="hidden" name="MAX_FILE_SIZE" value="10485760">
        <div class="space-y-4">
            <div><label class="label" for="evidence-title">Título de la evidencia *</label><input id="evidence-title" name="titulo" class="input" required maxlength="180" value="<?= e(old('titulo')) ?>" placeholder="Ej. Lista de asistencia · inducción sede norte · octubre 2026"><?= field_error('titulo') ?></div>
            <div class="grid gap-4 sm:grid-cols-2"><div><label class="label" for="evidence-category">Categoría *</label><select id="evidence-category" name="categoria" class="input" required><option value="">Selecciona…</option><?php foreach ($categories as $category): ?><option value="<?= e($category) ?>"<?= selected(old('categoria'), $category) ?>><?= e($category) ?></option><?php endforeach; ?></select><?= field_error('categoria') ?></div><div><label class="label" for="evidence-date">Fecha del hecho o documento *</label><input id="evidence-date" name="fecha_evidencia" type="date" class="input" required value="<?= e(old('fecha_evidencia', date('Y-m-d'))) ?>"><?= field_error('fecha_evidencia') ?></div></div>
            <div><label class="label" for="evidence-file">Archivo físico *</label><input id="evidence-file" name="soporte" type="file" class="input h-auto py-2 text-xs" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required><p class="mt-2 text-[10px] leading-4 text-slate-400">Se comprueban extensión, firma del archivo, tamaño y hash. No se ejecutan macros ni contenido activo.</p></div>
        </div>
        <div class="space-y-4">
            <div><label class="label" for="evidence-link">Vincular con *</label><select id="evidence-link" name="vinculo" class="input" required><option value="general:"<?= selected($uploadLink, 'general:') ?>>Archivo general, sin registro específico</option><?php foreach ($linkGroups as $group): ?><optgroup label="<?= e($group['label']) ?>"><?php foreach ($group['options'] as $option): ?><option value="<?= e($option['value']) ?>"<?= selected($uploadLink, $option['value']) ?>><?= e($option['label']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select><?= field_error('vinculo') ?><p class="mt-2 text-[10px] leading-4 text-slate-400">Solo aparecen registros reales de tu empresa y del módulo habilitado.</p></div>
            <div><label class="label" for="evidence-module">Módulo para archivo general</label><select id="evidence-module" name="modulo_general" class="input"><?php foreach ($moduleOptions as $value => $label): ?><option value="<?= e($value) ?>"<?= selected(old('modulo_general', $planModule), $value) ?>><?= e($label) ?></option><?php endforeach; ?></select><?= field_error('modulo_general') ?></div>
            <div><label class="label" for="evidence-description">Descripción y contexto</label><textarea id="evidence-description" name="descripcion" class="input min-h-24" maxlength="2000" placeholder="Centro de trabajo, actividad, participantes, alcance, soporte físico original u observaciones pertinentes"><?= e(old('descripcion')) ?></textarea><?= field_error('descripcion') ?></div>
            <button type="submit" class="btn-primary w-full"><?= icon('upload', 'h-4 w-4') ?> Guardar en el archivo privado</button>
        </div>
    </form>
</details>

<section class="ios-card mt-6 p-4 sm:p-5">
    <form method="get" action="<?= e(url('/evidencias')) ?>" class="grid gap-3 md:grid-cols-[1fr_190px_160px_auto] md:items-end">
        <div><label class="label" for="evidence-search">Buscar</label><input id="evidence-search" name="q" class="input" value="<?= e($filters['q']) ?>" placeholder="Título, archivo, descripción o registro"></div>
        <div><label class="label" for="filter-category">Categoría</label><select id="filter-category" name="categoria" class="input"><option value="">Todas</option><?php foreach ($categories as $category): ?><option value="<?= e($category) ?>"<?= selected($filters['categoria'], $category) ?>><?= e($category) ?></option><?php endforeach; ?></select></div>
        <div><label class="label" for="filter-review">Revisión</label><select id="filter-review" name="revision" class="input"><option value="">Todos</option><option value="Pendiente"<?= selected($filters['revision'], 'Pendiente') ?>>Pendiente</option><option value="Revisado"<?= selected($filters['revision'], 'Revisado') ?>>Revisado</option></select></div>
        <button type="submit" class="btn-secondary"><?= icon('filter', 'h-4 w-4') ?> Filtrar</button>
        <div><label class="label" for="filter-from">Desde</label><input id="filter-from" name="desde" type="date" class="input" value="<?= e($filters['desde']) ?>"></div>
        <div><label class="label" for="filter-to">Hasta</label><input id="filter-to" name="hasta" type="date" class="input" value="<?= e($filters['hasta']) ?>"></div>
        <a href="<?= e(url('/evidencias')) ?>" class="btn-ghost md:col-span-2 md:justify-self-end">Limpiar filtros</a>
    </form>
</section>

<div class="mt-6 space-y-4">
<?php if ($evidences === []): ?>
    <section class="ios-card empty-state"><span class="empty-icon"><?= icon('upload', 'h-7 w-7') ?></span><h3>No hay evidencias en esta vista</h3><p>Adjunta la primera prueba documental o cambia los filtros para consultar otros archivos de tu empresa.</p><a href="#cargar" class="btn-secondary mt-5">Adjuntar evidencia</a></section>
<?php endif; ?>
<?php foreach ($evidences as $evidence): ?>
    <?php $relatedUrl = App\Services\EvidenceLinker::url($evidence); $isImage = in_array($evidence['extension'], ['jpg', 'png'], true); ?>
    <article id="evidencia-<?= (int) $evidence['id'] ?>" class="ios-card scroll-mt-24 p-5 sm:p-6">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start">
            <span class="metric-icon shrink-0 <?= $evidence['estado_revision'] === 'Revisado' ? 'bg-emerald-50 text-emerald-600' : 'bg-blue-50 text-blue-600' ?>"><?= icon($isImage ? 'eye' : 'document', 'h-5 w-5') ?></span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2"><span class="badge badge-blue"><?= e($evidence['categoria']) ?></span><span class="badge badge-gray"><?= e(App\Services\ModuleAccess::shortLabel($evidence['modulo'])) ?></span><span class="badge <?= $evidence['estado_revision'] === 'Revisado' ? 'badge-green' : 'badge-amber' ?>"><span class="badge-dot"></span><?= e($evidence['estado_revision']) ?></span></div>
                <h2 class="mt-3 break-words text-base font-semibold leading-6 text-slate-800"><?= e($evidence['titulo']) ?></h2>
                <p class="mt-1 break-words text-[11px] text-slate-400"><?= e($evidence['nombre_original']) ?> · <?= e(strtoupper($evidence['extension'])) ?> · <?= e(number_format((int) $evidence['tamano'] / 1048576, 2, ',', '.')) ?> MB</p>
                <?php if ($evidence['descripcion'] !== ''): ?><p class="mt-3 whitespace-pre-line break-words text-xs leading-6 text-slate-500"><?= e($evidence['descripcion']) ?></p><?php endif; ?>
                <dl class="mt-4 grid gap-3 rounded-2xl bg-slate-50 p-4 text-[11px] leading-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div><dt class="font-semibold text-slate-600">Fecha de evidencia</dt><dd class="mt-1 text-slate-500"><?= e(format_date($evidence['fecha_evidencia'] ?: $evidence['created_at'])) ?></dd></div>
                    <div><dt class="font-semibold text-slate-600">Registro vinculado</dt><dd class="mt-1 break-words text-slate-500"><?= e($evidence['entidad_referencia']) ?></dd></div>
                    <div><dt class="font-semibold text-slate-600">Cargado por</dt><dd class="mt-1 text-slate-500"><?= e($evidence['subido_por_nombre'] ?? 'Usuario') ?><br><?= e(format_date($evidence['created_at'])) ?></dd></div>
                    <div><dt class="font-semibold text-slate-600">Revisión humana</dt><dd class="mt-1 text-slate-500"><?php if ($evidence['estado_revision'] === 'Revisado'): ?><?= e($evidence['revisado_por_nombre'] ?? 'Responsable') ?><br><?= e(format_date($evidence['revisado_at'])) ?><?php else: ?>Aún no registrada<?php endif; ?></dd></div>
                </dl>
                <?php if ($evidence['observaciones_revision'] !== ''): ?><div class="mt-3 rounded-xl bg-emerald-50 p-3 text-[11px] leading-5 text-emerald-800 ring-1 ring-emerald-100"><strong>Observaciones de revisión:</strong> <?= e($evidence['observaciones_revision']) ?></div><?php endif; ?>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <a href="<?= e(url('/evidencias/' . $evidence['id'] . '/descargar' . ($isImage ? '?inline=1' : ''))) ?>" class="btn-secondary"<?= $isImage ? ' target="_blank" rel="noopener"' : '' ?>><?= icon($isImage ? 'eye' : 'download', 'h-4 w-4') ?> <?= $isImage ? 'Ver imagen' : 'Descargar archivo' ?></a>
                    <?php if ($isImage): ?><a href="<?= e(url('/evidencias/' . $evidence['id'] . '/descargar')) ?>" class="text-link"><?= icon('download', 'h-3.5 w-3.5') ?> Descargar</a><?php endif; ?>
                    <?php if ($relatedUrl !== null): ?><a href="<?= e(url($relatedUrl)) ?>" class="text-link">Abrir registro relacionado <?= icon('arrow-right', 'h-3.5 w-3.5') ?></a><?php endif; ?>
                </div>
                <div class="mt-4 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-start">
                    <details class="flex-1"><summary class="cursor-pointer text-[11px] font-semibold text-blue-600">Registrar o actualizar revisión humana</summary><form method="post" action="<?= e(url('/evidencias/' . $evidence['id'] . '/revision')) ?>" class="mt-3 grid gap-3 rounded-xl bg-slate-50 p-3 sm:grid-cols-[160px_1fr_auto] sm:items-end"><?= csrf_field() ?><div><label class="label" for="review-<?= (int) $evidence['id'] ?>">Estado</label><select id="review-<?= (int) $evidence['id'] ?>" name="estado_revision" class="input bg-white"><option value="Pendiente"<?= selected($evidence['estado_revision'], 'Pendiente') ?>>Pendiente</option><option value="Revisado"<?= selected($evidence['estado_revision'], 'Revisado') ?>>Revisado</option></select></div><div><label class="label" for="notes-<?= (int) $evidence['id'] ?>">Alcance y observaciones *</label><input id="notes-<?= (int) $evidence['id'] ?>" name="observaciones_revision" class="input bg-white" maxlength="2000" value="<?= e($evidence['observaciones_revision']) ?>" placeholder="Qué se contrastó, por quién y con qué alcance"></div><button type="submit" class="btn-primary">Guardar revisión</button><p class="text-[10px] leading-4 text-slate-400 sm:col-span-3">Las observaciones son obligatorias para marcar Revisado. No constituyen certificación automática.</p></form></details>
                    <?php if ($evidence['estado_revision'] === 'Pendiente'): ?><form method="post" action="<?= e(url('/evidencias/' . $evidence['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar esta evidencia pendiente y su archivo físico? La acción quedará auditada."><?= csrf_field() ?><button type="submit" class="btn-danger-soft">Eliminar pendiente</button></form><?php endif; ?>
                </div>
                <details class="mt-3"><summary class="cursor-pointer text-[9px] text-slate-400">Huella de integridad</summary><p class="mt-2 break-all font-mono text-[9px] leading-4 text-slate-400">SHA-256: <?= e($evidence['hash_sha256']) ?></p></details>
            </div>
        </div>
    </article>
<?php endforeach; ?>
</div>
