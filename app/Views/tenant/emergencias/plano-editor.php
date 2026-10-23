<?php
$isNew = $drawing === null;
$isHistorical = $sourceVersion !== null;
$isObsolete = $plan['estado'] === 'Obsoleto';
$encodedDesign = App\Services\FloorPlan::encode($design);
$resumedDesign = array_key_exists('datos_json', $oldInput);
$clientDesign = $resumedDesign ? (string) $oldInput['datos_json'] : $encodedDesign;
$action = $isNew ? '/emergencias/' . $plan['id'] . '/planos' : '/planos-emergencia/' . $drawing['id'];
$toolLabels = [
    'select' => ['Seleccionar y mover', '↖'], 'wall' => ['Muro', '━'], 'door' => ['Puerta', '◜'],
    'route' => ['Ruta de evacuación', '⇢'], 'exit' => ['Salida', 'S'], 'extinguisher' => ['Extintor', 'E'],
    'first_aid' => ['Botiquín', '+'], 'alarm' => ['Alarma', 'A'], 'assembly' => ['Punto de encuentro', 'PE'],
    'text' => ['Texto', 'T'],
];
?>
<div class="page-heading">
    <div><a href="<?= e(url('/emergencias/' . $plan['id'])) ?>" class="text-link mb-3"><?= icon('arrow-left', 'h-3.5 w-3.5') ?> Volver a <?= e($plan['codigo']) ?></a><span class="eyebrow">Editor visual de planos</span><div class="flex flex-wrap items-center gap-3"><h1 class="!text-2xl"><?= e($isNew ? 'Nuevo plano de emergencia' : $drawing['nombre']) ?></h1><?php if (!$isNew): ?><span class="badge badge-blue">v<?= (int) $drawing['version'] ?></span><span class="badge badge-amber">Borrador</span><?php endif; ?></div><p><?= e($plan['titulo']) ?> · <?= e($plan['centro_trabajo']) ?></p></div>
    <?php if (!$isNew): ?><a href="<?= e(url('/evidencias?vinculo=' . rawurlencode('plano:' . $drawing['id']) . '#cargar')) ?>" class="btn-secondary"><?= icon('upload', 'h-4 w-4') ?> Adjuntar otro soporte</a><?php endif; ?>
</div>

<div class="mt-5 rounded-2xl bg-amber-50 p-4 text-[11px] leading-5 text-amber-800 ring-1 ring-amber-100"><strong>Herramienta de apoyo, no validación técnica.</strong> El editor no reemplaza un levantamiento del centro, un software CAD profesional ni la revisión de una persona competente. Verifica dimensiones, recorridos, señalización, accesibilidad y condiciones reales antes de usar el plano.</div>
<?php if ($isHistorical): ?><div class="mt-4 rounded-2xl bg-blue-50 p-4 text-[11px] leading-5 text-blue-800 ring-1 ring-blue-100">Estás consultando la <strong>versión <?= (int) $sourceVersion['version'] ?></strong>, creada por <?= e($sourceVersion['created_by_nombre'] ?? 'Usuario') ?> el <?= e(format_date($sourceVersion['created_at'])) ?>. Si guardas, se creará una versión nueva a partir de este diseño; la versión actual es la <?= (int) $drawing['version'] ?>. Para exportar, primero guarda esta copia o vuelve a la versión actual.</div><?php endif; ?>
<?php if ($isObsolete): ?><div class="mt-4 rounded-2xl bg-slate-100 p-4 text-[11px] leading-5 text-slate-600">El plan de emergencia está obsoleto. Este plano se conserva para consulta y exportación, pero no admite nuevas versiones.</div><?php endif; ?>

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

    <div id="floor-plan-editor" class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]" data-design="<?= e($clientDesign) ?>" data-fallback-design="<?= e($encodedDesign) ?>" data-resumed="<?= $resumedDesign ? '1' : '0' ?>" data-export-url="<?= e($isNew ? '' : url('/planos-emergencia/' . $drawing['id'] . '/exportar-png')) ?>" data-can-export="<?= !$isNew && !$isHistorical ? '1' : '0' ?>" data-version="<?= (int) ($drawing['version'] ?? 0) ?>" data-plan="<?= e($plan['codigo'] . ' · ' . $plan['centro_trabajo']) ?>">
        <section class="ios-card min-w-0 overflow-hidden">
            <div class="floor-plan-toolbar" role="toolbar" aria-label="Herramientas del plano">
                <?php foreach ($toolLabels as $tool => $info): ?><button type="button" class="floor-tool<?= $tool === 'select' ? ' is-active' : '' ?>" data-tool="<?= e($tool) ?>" aria-pressed="<?= $tool === 'select' ? 'true' : 'false' ?>" title="<?= e($info[0]) ?>"><span aria-hidden="true"><?= e($info[1]) ?></span><small><?= e($info[0]) ?></small></button><?php endforeach; ?>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/70 px-3 py-2">
                <div class="flex items-center gap-1"><button type="button" class="btn-icon h-8 w-8" data-undo title="Deshacer (Ctrl+Z)" aria-label="Deshacer"><?= icon('arrow-left', 'h-4 w-4') ?></button><button type="button" class="btn-icon h-8 w-8" data-redo title="Rehacer (Ctrl+Y)" aria-label="Rehacer"><?= icon('arrow-right', 'h-4 w-4') ?></button><button type="button" class="btn-icon h-8 w-8" data-delete title="Eliminar selección (Supr)" aria-label="Eliminar selección"><?= icon('x', 'h-4 w-4') ?></button><span class="mx-1 h-5 w-px bg-slate-200"></span><button type="button" class="btn-icon h-8 w-8" data-zoom-out aria-label="Reducir">−</button><span data-zoom-label class="w-10 text-center text-[10px] font-semibold text-slate-500">100%</span><button type="button" class="btn-icon h-8 w-8" data-zoom-in aria-label="Ampliar">+</button></div>
                <div class="flex flex-wrap items-center gap-3 text-[10px] text-slate-500"><label class="flex items-center gap-1.5"><input type="checkbox" class="checkbox" data-grid-visible checked> Cuadrícula</label><label class="flex items-center gap-1.5"><input type="checkbox" class="checkbox" data-snap checked> Ajustar</label><span data-cursor-position class="font-mono text-slate-400">X 0 · Y 0</span></div>
            </div>
            <div class="floor-plan-scroll" id="floor-plan-scroll">
                <svg id="floor-plan-canvas" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Lienzo visual del plano de emergencia" tabindex="0"></svg>
            </div>
            <div class="flex flex-col gap-2 border-t border-slate-100 px-4 py-3 text-[10px] leading-4 text-slate-400 sm:flex-row sm:items-center sm:justify-between"><p data-editor-status role="status">Selecciona una herramienta. Arrastra para crear muros o rutas; haz clic para ubicar señalización.</p><p class="shrink-0">Mayús: sin ajuste · Supr: eliminar · Ctrl+S: guardar</p></div>
        </section>

        <aside class="space-y-5 xl:self-start">
            <section class="ios-card p-4"><h2 class="text-xs font-semibold text-slate-800">Propiedades del elemento</h2><div data-no-selection class="mt-3 rounded-xl bg-slate-50 p-3 text-[11px] leading-5 text-slate-400">Selecciona un elemento para cambiar su texto, color, grosor o rotación.</div><div data-selection-properties class="mt-4 hidden space-y-3"><p class="text-[11px] font-semibold text-blue-600" data-selected-type></p><div><label class="label" for="element-label">Texto / identificación</label><input id="element-label" class="input h-9 text-xs" data-element-label maxlength="160"></div><div><label class="label" for="element-color">Color</label><select id="element-color" class="input h-9 text-xs" data-element-color><option value="#334155">Gris oscuro</option><option value="#0f172a">Negro</option><option value="#64748b">Gris</option><option value="#2563eb">Azul</option><option value="#15803d">Verde oscuro</option><option value="#16a34a">Verde</option><option value="#dc2626">Rojo</option><option value="#f97316">Naranja</option></select></div><div class="grid grid-cols-2 gap-3"><div><label class="label" for="element-stroke">Grosor / tamaño</label><input id="element-stroke" type="number" min="2" max="30" step="1" class="input h-9 text-xs" data-element-stroke></div><div><label class="label" for="element-rotation">Rotación °</label><input id="element-rotation" type="number" min="-360" max="360" step="15" class="input h-9 text-xs" data-element-rotation></div></div></div></section>
            <section class="ios-card p-4"><h2 class="text-xs font-semibold text-slate-800">Lienzo y cuadrícula</h2><div class="mt-4 grid grid-cols-3 gap-2"><div><label class="label" for="canvas-width">Ancho</label><input id="canvas-width" type="number" min="400" max="2400" step="20" class="input h-9 px-2 text-xs" data-canvas-width></div><div><label class="label" for="canvas-height">Alto</label><input id="canvas-height" type="number" min="300" max="1600" step="20" class="input h-9 px-2 text-xs" data-canvas-height></div><div><label class="label" for="canvas-grid">Malla</label><input id="canvas-grid" type="number" min="5" max="100" step="5" class="input h-9 px-2 text-xs" data-canvas-grid></div></div><p class="mt-3 text-[10px] leading-4 text-slate-400">Unidades del dibujo, no metros reales. Documenta por separado la escala verificada.</p></section>
            <section class="ios-card p-4"><h2 class="text-xs font-semibold text-slate-800">Convenciones</h2><ul class="mt-3 space-y-2 text-[10px] leading-4 text-slate-500"><li><strong class="text-slate-700">Muros y puertas:</strong> delimitan el recorrido.</li><li><strong class="text-green-700">Rutas y salidas:</strong> deben contrastarse en sitio.</li><li><strong class="text-red-600">Equipos y alarmas:</strong> ubica solo recursos existentes.</li><li><strong class="text-slate-700">Textos:</strong> identifica pisos, áreas, norte y datos útiles.</li></ul></section>
            <?php if (!$isNew): ?><section class="ios-card p-4"><div class="flex items-center justify-between"><h2 class="text-xs font-semibold text-slate-800">Historial de versiones</h2><span class="badge badge-gray"><?= count($versions) ?></span></div><div class="mt-3 max-h-64 space-y-2 overflow-y-auto"><?php foreach ($versions as $version): ?><a href="<?= e(url('/planos-emergencia/' . $drawing['id'] . '/versiones/' . $version['id'])) ?>" class="block rounded-xl p-3 text-[10px] leading-4 ring-1 <?= !$isHistorical && (int) $version['version'] === (int) $drawing['version'] ? 'bg-blue-50 text-blue-700 ring-blue-100' : ($isHistorical && (int) $version['id'] === (int) $sourceVersion['id'] ? 'bg-blue-50 text-blue-700 ring-blue-100' : 'bg-slate-50 text-slate-500 ring-slate-100') ?>"><strong> Versión <?= (int) $version['version'] ?></strong><?php if (!$isHistorical && (int) $version['version'] === (int) $drawing['version']): ?> · actual<?php endif; ?><br><?= e($version['created_by_nombre'] ?? 'Usuario') ?> · <?= e(format_date($version['created_at'])) ?></a><?php endforeach; ?></div><?php if ($isHistorical): ?><a href="<?= e(url('/planos-emergencia/' . $drawing['id'] . '/editar')) ?>" class="text-link mt-3">Volver a la versión actual</a><?php endif; ?></section><?php endif; ?>
        </aside>
    </div>

    <div class="ios-card mt-5 flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div><p class="text-xs font-semibold text-slate-700" data-save-summary><?= $isNew ? 'El primer guardado creará la versión 1.' : 'Cada guardado crea una versión inmutable.' ?></p><p class="mt-1 text-[10px] leading-4 text-slate-400">Guarda antes de exportar. El PNG quedará como evidencia Pendiente de revisión humana.</p><p data-export-result class="mt-2 text-[11px] leading-5" role="status"></p></div>
        <div class="flex flex-wrap gap-2"><?php if (!$isNew): ?><button type="button" class="btn-secondary" data-export <?= $isHistorical ? 'disabled' : '' ?>><?= icon('download', 'h-4 w-4') ?> Exportar y archivar PNG</button><?php endif; ?><?php if (!$isObsolete): ?><button type="submit" class="btn-primary" data-save><?= icon('check', 'h-4 w-4') ?> <?= $isNew ? 'Guardar versión 1' : ($isHistorical ? 'Guardar copia como nueva versión' : 'Guardar nueva versión') ?></button><?php endif; ?></div>
    </div>
    <noscript><div class="mt-4 rounded-xl bg-rose-50 p-4 text-xs text-rose-700">El editor visual necesita JavaScript. Actívalo para dibujar; los datos almacenados no se modifican hasta que guardes.</div></noscript>
</form>
