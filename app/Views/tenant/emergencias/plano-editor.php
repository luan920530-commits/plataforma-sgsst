<?php
$isNew = $drawing === null;
$isHistorical = $sourceVersion !== null;
$isObsolete = $plan['estado'] === 'Obsoleto';
$encodedDesign = App\Services\FloorPlan::encode($design);
$resumedDesign = array_key_exists('datos_json', $oldInput);
$clientDesign = $resumedDesign ? (string) $oldInput['datos_json'] : $encodedDesign;
$action = $isNew ? '/emergencias/' . $plan['id'] . '/planos' : '/planos-emergencia/' . $drawing['id'];
$toolCatalog = App\Services\FloorPlan::toolCatalog();
$toolCategories = App\Services\FloorPlan::categories();
$cadObjects = App\Services\FloorPlan::cadCatalog();
$cadCategories = App\Services\FloorPlan::cadCategories();
$basicGlyphs = ['select' => '↖', 'wall' => '━', 'door' => '◜', 'route' => '⇢', 'text' => 'T'];
?>
<div class="page-heading">
    <div>
        <a href="<?= e(url('/emergencias/' . $plan['id'])) ?>" class="text-link mb-3"><?= icon('arrow-left', 'h-3.5 w-3.5') ?> Volver a <?= e($plan['codigo']) ?></a>
        <span class="eyebrow">Editor visual de planos</span>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="!text-2xl"><?= e($isNew ? 'Nuevo plano de emergencia' : $drawing['nombre']) ?></h1>
            <?php if (!$isNew): ?><span class="badge badge-blue">v<?= (int) $drawing['version'] ?></span><span class="badge badge-amber">Borrador</span><?php endif; ?>
        </div>
        <p><?= e($plan['titulo']) ?> · <?= e($plan['centro_trabajo']) ?></p>
    </div>
    <?php if (!$isNew): ?><a href="<?= e(url('/evidencias?vinculo=' . rawurlencode('plano:' . $drawing['id']) . '#cargar')) ?>" class="btn-secondary"><?= icon('upload', 'h-4 w-4') ?> Adjuntar otro soporte</a><?php endif; ?>
</div>

<div class="mt-5 rounded-2xl bg-amber-50 p-4 text-[11px] leading-5 text-amber-800 ring-1 ring-amber-100">
    <strong>Biblioteca técnica de apoyo, no validación automática.</strong> Incluye 332 pictogramas de referencia ISO 7010 y las familias de color y forma usadas por NTC 1461. No todas las señales aplican a todos los centros: selecciona únicamente las necesarias y verifica pictograma, orientación, dimensiones, iluminación, recorridos y condiciones reales con una persona competente. No reemplaza un CAD profesional ni una revisión técnica/legal.
</div>
<?php if ($isHistorical): ?><div class="mt-4 rounded-2xl bg-blue-50 p-4 text-[11px] leading-5 text-blue-800 ring-1 ring-blue-100">Estás consultando la <strong>versión <?= (int) $sourceVersion['version'] ?></strong>, creada por <?= e($sourceVersion['created_by_nombre'] ?? 'Usuario') ?> el <?= e(format_date($sourceVersion['created_at'])) ?>. Si guardas, se creará una versión nueva a partir de este diseño; la versión actual es la <?= (int) $drawing['version'] ?>. Para exportar, primero guarda esta copia o vuelve a la versión actual.</div><?php endif; ?>
<?php if ($isObsolete): ?><div class="mt-4 rounded-2xl bg-slate-100 p-4 text-[11px] leading-5 text-slate-600 ring-1 ring-slate-200">El plan de emergencia está obsoleto. Este plano se conserva para consulta y exportación, pero no admite nuevas versiones.</div><?php endif; ?>

<form method="post" action="<?= e(url($action)) ?>" id="floor-plan-form" class="mt-6">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($drawing['id'] ?? 0) ?>"><input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
    <input type="hidden" name="datos_json" id="floor-plan-data">
    <?php if (!$isNew): ?><input type="hidden" name="version_actual" value="<?= (int) $drawing['version'] ?>"><?php endif; ?>

    <section class="ios-card p-5 sm:p-6">
        <div class="grid gap-4 lg:grid-cols-[1fr_240px_1fr]">
            <div><label class="label" for="plan-name">Nombre del plano *</label><input id="plan-name" name="nombre" class="input" required maxlength="180" value="<?= e(old('nombre', $drawing['nombre'] ?? 'Plano de evacuación · ' . $plan['centro_trabajo'])) ?>"><?= field_error('nombre') ?></div>
            <div><label class="label" for="plan-scale">Escala / referencia dimensional *</label><input id="plan-scale" name="escala" class="input" required maxlength="120" value="<?= e(old('escala', $drawing['escala'] ?? 'Referencial · verificar en sitio')) ?>" placeholder="Ej. Referencial; 1:50 por verificar"><?= field_error('escala') ?></div>
            <div><label class="label" for="plan-notes">Datos y observaciones</label><input id="plan-notes" name="descripcion" class="input" maxlength="2000" value="<?= e(old('descripcion', $drawing['descripcion'] ?? '')) ?>" placeholder="Piso, sede, orientación, fecha de levantamiento y responsable"><?= field_error('descripcion') ?></div>
        </div>
        <?= field_error('datos_json') ?>
    </section>

    <div id="floor-plan-editor" class="floor-plan-workspace mt-5" data-design="<?= e($clientDesign) ?>" data-fallback-design="<?= e($encodedDesign) ?>" data-resumed="<?= $resumedDesign ? '1' : '0' ?>" data-export-url="<?= e($isNew ? '' : url('/planos-emergencia/' . $drawing['id'] . '/exportar-png')) ?>" data-can-export="<?= !$isNew && !$isHistorical ? '1' : '0' ?>" data-version="<?= (int) ($drawing['version'] ?? 0) ?>" data-plan="<?= e($plan['codigo'] . ' · ' . $plan['centro_trabajo']) ?>">
        <aside class="floor-palette" aria-label="Herramientas del plano" data-floor-palette>
            <div class="floor-tool-rail">
                <div class="floor-rail-tools" aria-label="Dibujo y edición">
                    <?php foreach ($toolCatalog as $tool => $info): ?>
                        <?php if ($info['category'] !== 'draw') continue; ?>
                        <button type="button" class="floor-tool<?= $tool === 'select' ? ' is-active' : '' ?>" data-tool="<?= e($tool) ?>" data-name="<?= e($info['name']) ?>" data-description="<?= e($info['description']) ?>" aria-label="<?= e($info['name']) ?>" aria-pressed="<?= $tool === 'select' ? 'true' : 'false' ?>">
                            <span class="floor-tool-icon floor-tool-icon--basic" data-basic-icon="<?= e($tool) ?>" aria-hidden="true"><?= e($basicGlyphs[$tool]) ?></span>
                            <span class="floor-tool-copy"><strong><?= e($info['name']) ?></strong><small><?= e($info['reference']) ?></small></span>
                        </button>
                    <?php endforeach; ?>
                    <div class="floor-rail-divider"></div>
                    <button type="button" class="floor-tool floor-sign-tool" data-sign-tool data-name="Señal de seguridad" data-description="Configura el tipo de señal en el panel derecho y haz clic sobre el lienzo para ubicarla." data-reference="ISO 7010 E002" aria-label="Configurar y ubicar señal de seguridad" aria-pressed="false">
                        <span class="floor-tool-icon floor-sign-tool-icon" data-sign-tool-icon data-category-icon="E002" aria-hidden="true">E002</span>
                        <span class="floor-tool-copy"><strong>Señal de seguridad</strong><small>ISO 7010</small></span>
                    </button>
                    <button type="button" class="floor-tool floor-sign-tool floor-cad-tool" data-cad-tool data-name="Equipamiento CAD" data-description="Elige mobiliario, equipos, vehículos o conexiones entre pisos y haz clic sobre el lienzo para ubicarlos." data-reference="Mobiliario" aria-label="Configurar y ubicar equipamiento CAD" aria-pressed="false">
                        <span class="floor-tool-icon floor-sign-tool-icon floor-cad-tool-icon" data-cad-tool-icon aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 9h18v4H3zM5 13v7m14-7v7M8 9V5h8v4"/></svg></span>
                        <span class="floor-tool-copy"><strong>Equipamiento CAD</strong><small>32 objetos</small></span>
                    </button>
                </div>
            </div>
        </aside>

        <section class="ios-card min-w-0 overflow-hidden">
            <div class="floor-canvas-heading">
                <div><span class="eyebrow">Área de trabajo</span><h2>Lienzo del plano</h2></div>
                <span class="floor-canvas-tool" data-canvas-tool>Seleccionar y mover</span>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/70 px-3 py-2">
                <div class="flex items-center gap-1">
                    <button type="button" class="btn-icon h-8 w-8" data-undo title="Deshacer (Ctrl+Z)" aria-label="Deshacer"><?= icon('arrow-left', 'h-3.5 w-3.5') ?></button>
                    <button type="button" class="btn-icon h-8 w-8" data-redo title="Rehacer (Ctrl+Y)" aria-label="Rehacer"><?= icon('arrow-right', 'h-3.5 w-3.5') ?></button>
                    <button type="button" class="btn-icon h-8 w-8" data-delete title="Eliminar selección (Supr)" aria-label="Eliminar selección"><?= icon('x', 'h-3.5 w-3.5') ?></button>
                    <span class="mx-1 h-5 w-px bg-slate-200"></span>
                    <button type="button" class="btn-icon h-8 w-8" data-zoom-out aria-label="Reducir">−</button><span data-zoom-label class="w-10 text-center text-[10px] font-semibold text-slate-500">100%</span><button type="button" class="btn-icon h-8 w-8" data-zoom-in aria-label="Ampliar">+</button>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-[10px] text-slate-500"><label class="flex items-center gap-1.5"><input type="checkbox" class="checkbox" data-grid-visible checked> Cuadrícula</label><label class="flex items-center gap-1.5"><input type="checkbox" class="checkbox" data-snap checked> Ajustar</label><span data-cursor-position class="font-mono text-slate-400">X 0 · Y 0</span></div>
            </div>
            <div class="floor-plan-scroll" id="floor-plan-scroll">
                <svg id="floor-plan-canvas" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Lienzo visual del plano de emergencia" tabindex="0"></svg>
            </div>
            <div class="flex flex-col gap-2 border-t border-slate-100 px-4 py-3 text-[10px] leading-4 text-slate-400 sm:flex-row sm:items-center sm:justify-between"><p data-editor-status role="status">Selecciona una herramienta. Para una señal, elige su tipo en el panel derecho y haz clic sobre el lienzo.</p><p class="shrink-0">Mayús: sin ajuste · Supr: eliminar · Ctrl+S: guardar</p></div>
        </section>

        <aside class="floor-plan-properties space-y-5">
            <section class="ios-card floor-sign-configurator p-4" data-sign-configurator>
                <div class="floor-sign-preview">
                    <span class="floor-sign-preview-icon" data-sign-preview-icon data-category-icon="E002" aria-hidden="true">E002</span>
                    <div class="min-w-0"><span>Objeto de señalización</span><h2 data-sign-preview-name><?= e($toolCatalog['iso_e002']['name']) ?></h2><small data-sign-preview-code>ISO 7010 E002</small></div>
                </div>
                <p class="floor-sign-description" data-sign-preview-description><?= e($toolCatalog['iso_e002']['description']) ?></p>
                <div class="floor-sign-filters">
                    <div class="floor-sign-search"><?= icon('search', 'h-3.5 w-3.5') ?><label class="sr-only" for="floor-sign-search">Buscar entre las 332 señales</label><input id="floor-sign-search" type="search" placeholder="Buscar código o nombre…" autocomplete="off" data-sign-search></div>
                    <label class="sr-only" for="floor-sign-category">Filtrar por familia</label>
                    <select id="floor-sign-category" data-sign-category aria-label="Filtrar por familia"><option value="">Todas</option><?php foreach ($toolCategories as $categoryKey => $category): ?><option value="<?= e($categoryKey) ?>"><?= e($category['short']) ?></option><?php endforeach; ?></select>
                </div>
                <div class="floor-sign-select-heading"><label for="floor-sign-type">Tipo de señal</label><span data-sign-count aria-live="polite">332</span></div>
                <select id="floor-sign-type" class="floor-sign-options" size="5" data-sign-type aria-label="Tipo de señal ISO 7010">
                    <?php foreach ($toolCategories as $categoryKey => $category): ?>
                    <optgroup label="<?= e($category['label']) ?>" data-category="<?= e($categoryKey) ?>">
                        <?php foreach ($toolCatalog as $tool => $info): ?>
                            <?php if ($info['category'] !== $categoryKey) continue; ?>
                            <option value="<?= e($tool) ?>" data-category="<?= e($categoryKey) ?>" data-search="<?= e($info['name'] . ' ' . $info['reference'] . ' ' . $info['description']) ?>"><?= e($info['reference'] . ' · ' . $info['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
                <p class="floor-sign-hint">Sin una señal seleccionada, define la próxima que ubicarás. Con una señal seleccionada, cambia su tipo conservando posición, tamaño y rotación.</p>
            </section>
            <section class="ios-card floor-sign-configurator floor-cad-configurator p-4" data-cad-configurator hidden>
                <div class="floor-sign-preview">
                    <span class="floor-sign-preview-icon floor-cad-preview-icon" data-cad-preview-icon style="color: <?= e($cadObjects['cad_desk']['color']) ?>" aria-hidden="true"><?= $cadObjects['cad_desk']['svg'] ?></span>
                    <div class="min-w-0"><span>Equipamiento CAD</span><h2 data-cad-preview-name><?= e($cadObjects['cad_desk']['name']) ?></h2><small data-cad-preview-category><?= e($cadCategories[$cadObjects['cad_desk']['category']]['label']) ?></small></div>
                </div>
                <p class="floor-sign-description" data-cad-preview-description><?= e($cadObjects['cad_desk']['description']) ?></p>
                <div class="floor-sign-filters">
                    <div class="floor-sign-search"><?= icon('search', 'h-3.5 w-3.5') ?><label class="sr-only" for="floor-cad-search">Buscar entre los 32 objetos CAD</label><input id="floor-cad-search" type="search" placeholder="Buscar equipo o mueble…" autocomplete="off" data-cad-search></div>
                    <label class="sr-only" for="floor-cad-category">Filtrar objetos CAD por familia</label>
                    <select id="floor-cad-category" data-cad-category aria-label="Filtrar objetos CAD por familia"><option value="">Seis familias</option><?php foreach ($cadCategories as $categoryKey => $category): ?><option value="<?= e($categoryKey) ?>"><?= e($category['label']) ?></option><?php endforeach; ?></select>
                </div>
                <div class="floor-sign-select-heading"><span>Tipo de objeto</span><span data-cad-count aria-live="polite">32</span></div>
                <div class="floor-cad-grid" data-cad-grid aria-label="Catálogo visual de equipamiento CAD">
                    <?php foreach ($cadObjects as $tool => $object): ?>
                    <button type="button" class="floor-cad-option<?= $tool === 'cad_desk' ? ' is-active' : '' ?>" data-cad-type="<?= e($tool) ?>" data-category="<?= e($object['category']) ?>" data-search="<?= e($object['name'] . ' ' . $object['description'] . ' ' . $cadCategories[$object['category']]['label']) ?>" aria-label="<?= e($object['name']) ?>" aria-pressed="<?= $tool === 'cad_desk' ? 'true' : 'false' ?>">
                        <span class="floor-cad-option-icon" style="color: <?= e($object['color']) ?>" aria-hidden="true"><?= $object['svg'] ?></span><span><?= e($object['name']) ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <p class="mt-2 rounded-lg bg-slate-50 p-2 text-center text-[10px] text-slate-500" data-cad-empty hidden>No hay objetos que coincidan con la búsqueda.</p>
                <p class="floor-sign-hint">Haz clic en el lienzo para ubicarlo. Ajusta Grosor / tamaño para escalarlo y usa Rotación para orientarlo. Sin selección defines el próximo objeto; con uno seleccionado cambias su tipo conservando su geometría.</p>
            </section>
            <section class="ios-card p-4">
                <h2 class="text-xs font-semibold text-slate-800">Propiedades del elemento</h2>
                <div data-no-selection class="mt-3 rounded-xl bg-slate-50 p-3 text-[11px] leading-5 text-slate-400">Selecciona un elemento para cambiar su texto, tamaño u orientación.</div>
                <div data-selection-properties class="mt-4 hidden space-y-3">
                    <p class="text-[11px] font-semibold text-blue-600" data-selected-type></p>
                    <div><label class="label" for="element-label">Texto / identificación</label><input id="element-label" class="input h-9 text-xs" data-element-label maxlength="160"></div>
                    <div data-color-field><label class="label" for="element-color">Color</label><select id="element-color" class="input h-9 text-xs" data-element-color><option value="#334155">Gris oscuro</option><option value="#0f172a">Negro</option><option value="#475569">Gris pizarra</option><option value="#64748b">Gris</option><option value="#2563eb">Azul</option><option value="#005387">Azul ISO</option><option value="#15803d">Verde oscuro</option><option value="#16a34a">Verde</option><option value="#237f52">Verde ISO</option><option value="#dc2626">Rojo</option><option value="#b71f2e">Rojo ISO</option><option value="#f97316">Naranja</option><option value="#f9a800">Amarillo ISO</option><option value="#ca8a04">Amarillo maquinaria</option><option value="#b45309">Ocre industrial</option></select></div>
                    <p class="hidden rounded-lg bg-slate-50 p-2 text-[10px] leading-4 text-slate-500" data-standard-color-note>El pictograma conserva los colores y la forma de su familia de seguridad.</p>
                    <div class="grid grid-cols-2 gap-3"><div><label class="label" for="element-stroke">Grosor / tamaño</label><input id="element-stroke" type="number" min="2" max="30" step="1" class="input h-9 text-xs" data-element-stroke></div><div><label class="label" for="element-rotation">Rotación °</label><input id="element-rotation" type="number" min="-360" max="360" step="15" class="input h-9 text-xs" data-element-rotation></div></div>
                </div>
            </section>
            <section class="ios-card p-4"><h2 class="text-xs font-semibold text-slate-800">Lienzo y cuadrícula</h2><div class="mt-4 grid grid-cols-3 gap-2"><div><label class="label" for="canvas-width">Ancho</label><input id="canvas-width" type="number" min="400" max="2400" step="20" class="input h-9 px-2 text-xs" data-canvas-width></div><div><label class="label" for="canvas-height">Alto</label><input id="canvas-height" type="number" min="300" max="1600" step="20" class="input h-9 px-2 text-xs" data-canvas-height></div><div><label class="label" for="canvas-grid">Malla</label><input id="canvas-grid" type="number" min="5" max="100" step="5" class="input h-9 px-2 text-xs" data-canvas-grid></div></div><p class="mt-3 text-[10px] leading-4 text-slate-400">Unidades del dibujo, no metros reales. Documenta por separado la escala verificada.</p></section>
            <section class="ios-card p-4"><h2 class="text-xs font-semibold text-slate-800">Familias de señalización</h2><ul class="mt-3 space-y-2 text-[10px] leading-4 text-slate-500"><li><strong class="text-green-700">Verde:</strong> emergencia y condición segura.</li><li><strong class="text-red-700">Rojo:</strong> equipos contra incendio y prohibiciones.</li><li><strong class="text-blue-700">Azul:</strong> acciones obligatorias.</li><li><strong class="text-amber-600">Amarillo:</strong> advertencias de peligro.</li></ul><p class="mt-3 border-t border-slate-100 pt-3 text-[10px] leading-4 text-slate-400">El código ISO ayuda a identificar el pictograma; no indica por sí solo que su uso sea exigible en ese punto.</p></section>
            <?php if (!$isNew): ?><section class="ios-card p-4"><div class="flex items-center justify-between"><h2 class="text-xs font-semibold text-slate-800">Historial de versiones</h2><span class="badge badge-gray"><?= count($versions) ?></span></div><div class="mt-3 max-h-64 space-y-2 overflow-y-auto"><?php foreach ($versions as $version): ?><a href="<?= e(url('/planos-emergencia/' . $drawing['id'] . '/versiones/' . $version['id'])) ?>" class="block rounded-xl p-3 text-[10px] leading-4 ring-1 <?= !$isHistorical && (int) $version['version'] === (int) $drawing['version'] ? 'bg-blue-50 text-blue-700 ring-blue-100' : ($isHistorical && (int) $version['id'] === (int) $sourceVersion['id'] ? 'bg-blue-50 text-blue-700 ring-blue-100' : 'bg-slate-50 text-slate-500 ring-slate-100') ?>"><strong>Versión <?= (int) $version['version'] ?></strong><?php if (!$isHistorical && (int) $version['version'] === (int) $drawing['version']): ?> · actual<?php endif; ?><br><?= e($version['created_by_nombre'] ?? 'Usuario') ?> · <?= e(format_date($version['created_at'])) ?></a><?php endforeach; ?></div><?php if ($isHistorical): ?><a href="<?= e(url('/planos-emergencia/' . $drawing['id'] . '/editar')) ?>" class="text-link mt-3">Volver a la versión actual</a><?php endif; ?></section><?php endif; ?>
        </aside>
    </div>

    <div class="ios-card mt-5 flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div><p class="text-xs font-semibold text-slate-700" data-save-summary><?= $isNew ? 'El primer guardado creará la versión 1.' : 'Cada guardado crea una versión inmutable.' ?></p><p class="mt-1 text-[10px] leading-4 text-slate-400">Guarda antes de exportar. El PNG quedará como evidencia Pendiente de revisión humana.</p><p data-export-result class="mt-2 text-[11px] leading-5" role="status"></p></div>
        <div class="flex flex-wrap gap-2"><?php if (!$isNew): ?><button type="button" class="btn-secondary" data-export <?= $isHistorical ? 'disabled' : '' ?>><?= icon('download', 'h-4 w-4') ?> Exportar y archivar PNG</button><?php endif; ?><?php if (!$isObsolete): ?><button type="submit" class="btn-primary" data-save><?= icon('check', 'h-4 w-4') ?> <?= $isNew ? 'Guardar versión 1' : ($isHistorical ? 'Guardar copia como nueva versión' : 'Guardar nueva versión') ?></button><?php endif; ?></div>
    </div>
    <noscript><div class="mt-4 rounded-xl bg-rose-50 p-4 text-xs text-rose-700">El editor visual necesita JavaScript. Actívalo para dibujar; los datos almacenados no se modifican hasta que guardes.</div></noscript>
</form>
<?php if (!$isNew && !$isHistorical && !$isObsolete): ?><section class="mt-5 flex flex-col gap-3 rounded-2xl border border-rose-100 bg-rose-50/60 p-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-xs font-semibold text-rose-800">Eliminar plano y su historial</h2><p class="mt-1 text-[10px] leading-4 text-rose-700">Sólo disponible sin evidencias vinculadas. Se eliminarán el diseño y todas sus versiones; la acción queda auditada.</p></div><form method="post" action="<?= e(url('/planos-emergencia/' . $drawing['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar este plano y TODAS sus versiones? Esta acción no se puede deshacer."><?= csrf_field() ?><button type="submit" class="btn-secondary !text-rose-600"><?= icon('trash', 'h-4 w-4') ?> Eliminar plano</button></form></section><?php endif; ?>
