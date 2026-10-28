<div class="page-heading">
    <div>
        <a href="<?= e(url('/documentos')) ?>" class="text-link mb-3"><?= icon('arrow-left', 'h-3.5 w-3.5') ?> Biblioteca documental</a>
        <div class="flex flex-wrap items-center gap-3"><h1 class="!text-2xl"><?= e($document['nombre_documento']) ?></h1><span class="badge <?= status_class($document['estado']) ?>"><?= e($document['estado']) ?></span></div>
        <p><?= e($document['codigo_estandar']) ?> · <?= e($document['fase_phva']) ?> · Versión <?= (int) $document['version'] ?></p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="<?= e(url('/evidencias?vinculo=' . rawurlencode('documento:' . $document['id']) . '#cargar')) ?>" class="btn-secondary"><?= icon('upload', 'h-4 w-4') ?> Adjuntar evidencia</a>
        <a href="<?= e(url('/documentos/' . $document['id'] . '/descargar')) ?>" class="btn-secondary"><?= icon('download', 'h-4 w-4') ?> Descargar editable</a>
        <a href="<?= e(url('/documentos/' . $document['id'] . '/imprimir')) ?>" class="btn-secondary" target="_blank" rel="noopener"><?= icon('download', 'h-4 w-4') ?> Imprimir / PDF</a>
        <?php if ($document['estado'] === 'Borrador'): ?>
            <form method="post" action="<?= e(url('/documentos/' . $document['id'] . '/aprobar')) ?>" data-confirm="¿Confirmas que el contenido fue revisado y está listo para aprobarse?"><?= csrf_field() ?><button class="btn-primary" type="submit"><?= icon('check-circle', 'h-4 w-4') ?> Aprobar documento</button></form>
        <?php endif; ?>
    </div>
</div>
<div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
    <div class="space-y-5">
        <div class="document-preview"><?= $document['contenido_html'] ?></div>
        <?php if ($document['estado'] !== 'Obsoleto'): ?>
            <details class="ios-card overflow-hidden" <?= $document['estado'] === 'Borrador' ? 'open' : '' ?>>
                <summary class="cursor-pointer list-none p-5"><div class="flex items-center justify-between gap-4"><div><h2 class="text-sm font-semibold">Editor visual del documento</h2><p class="mt-1 text-xs text-slate-400">Edita el contenido como se presenta; al guardar se creará una nueva versión en borrador.</p></div><?= icon('edit', 'h-4 w-4 shrink-0 text-blue-600') ?></div></summary>
                <form method="post" action="<?= e(url('/documentos/' . $document['id'])) ?>" class="border-t border-slate-100 p-4 sm:p-5" data-confirm="¿Guardar una nueva versión en borrador? La aprobación actual se retirará." data-rich-form>
                    <?= csrf_field() ?>
                    <div data-rich-editor>
                        <div class="rich-editor-toolbar" data-editor-toolbar role="toolbar" aria-label="Herramientas de formato">
                            <select class="rich-editor-select" data-editor-block aria-label="Estilo de párrafo">
                                <option value="p">Párrafo</option><option value="h1">Título principal</option><option value="h2">Título de sección</option><option value="h3">Subtítulo</option><option value="blockquote">Cita</option>
                            </select>
                            <span class="rich-editor-separator" aria-hidden="true"></span>
                            <button type="button" data-editor-command="bold" title="Negrita" aria-label="Negrita"><strong>B</strong></button>
                            <button type="button" data-editor-command="italic" title="Cursiva" aria-label="Cursiva"><em>I</em></button>
                            <button type="button" data-editor-command="insertUnorderedList" title="Lista con viñetas" aria-label="Lista con viñetas">• Lista</button>
                            <button type="button" data-editor-command="insertOrderedList" title="Lista numerada" aria-label="Lista numerada">1. Lista</button>
                            <button type="button" data-editor-action="table" title="Insertar tabla" aria-label="Insertar tabla">Tabla</button>
                            <button type="button" data-editor-command="removeFormat" title="Quitar formato" aria-label="Quitar formato">Limpiar</button>
                            <span class="rich-editor-separator" aria-hidden="true"></span>
                            <button type="button" data-editor-command="undo" title="Deshacer" aria-label="Deshacer">Deshacer</button>
                            <button type="button" data-editor-command="redo" title="Rehacer" aria-label="Rehacer">Rehacer</button>
                        </div>
                        <div class="document-preview rich-editor-surface" data-editor-surface contenteditable="true" role="textbox" aria-multiline="true" aria-label="Contenido visual del documento" spellcheck="true"><?= $document['contenido_html'] ?></div>
                        <textarea name="contenido_html" data-editor-input class="hidden" aria-label="Contenido del documento" tabindex="-1"><?= e($document['contenido_html']) ?></textarea>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-400"><p>La barra permite títulos, párrafos, listas, énfasis y tablas. El pegado se realiza como texto para evitar estilos o código externo.</p><span data-editor-status aria-live="polite">Editor listo</span></div>
                        <p class="mt-2 hidden text-xs font-medium text-rose-600" data-editor-error role="alert"></p>
                    </div>
                    <?= field_error('contenido_html') ?>
                    <div class="mt-3 flex items-start gap-2 text-[11px] leading-5 text-slate-400"><?= icon('shield-plain', 'mt-0.5 h-3.5 w-3.5 shrink-0') ?><p>El servidor vuelve a sanear el contenido y conserva únicamente la estructura documental permitida. Revisa la nueva versión antes de aprobarla.</p></div>
                    <noscript><p class="mt-3 rounded-xl bg-amber-50 p-3 text-xs text-amber-800">Activa JavaScript para usar el editor visual. El contenido HTML interno no se muestra como alternativa.</p></noscript>
                    <div class="mt-4 flex justify-end"><button type="submit" class="btn-primary" data-editor-submit disabled>Guardar como nueva versión</button></div>
                </form>
            </details>
        <?php endif; ?>
    </div>
    <aside class="space-y-5">
        <section class="ios-card p-5"><h2 class="text-sm font-semibold">Control del documento</h2><dl class="mt-5 space-y-4 text-xs">
            <div><dt class="text-slate-400">Empresa</dt><dd class="mt-1 font-medium leading-5 text-slate-700"><?= e($tenant['razonsocial']) ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-400">Código</dt><dd class="font-mono font-semibold"><?= e($document['codigo_estandar']) ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-400">Tipo</dt><dd class="font-semibold"><?= e($document['tipo']) ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-400">Versión</dt><dd class="font-semibold">v<?= (int) $document['version'] ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-400">Fase</dt><dd class="font-semibold"><?= e($document['fase_phva']) ?></dd></div>
            <div><dt class="text-slate-400">Última actualización</dt><dd class="mt-1 font-medium text-slate-700"><?= format_date($document['updated_at'], true) ?></dd></div>
            <?php if ($document['aprobado_at']): ?><div class="rounded-xl bg-emerald-50 p-3"><dt class="text-emerald-600">Aprobado por</dt><dd class="mt-1 font-semibold text-emerald-900"><?= e($document['aprobado_por_nombre'] ?? 'Usuario del sistema') ?></dd><dd class="mt-1 text-[10px] text-emerald-600"><?= format_date($document['aprobado_at'], true) ?></dd></div><?php endif; ?>
        </dl></section>
        <?php if ($versions !== []): ?><section class="ios-card p-5"><h2 class="text-sm font-semibold">Historial de versiones</h2><p class="mt-1 text-[11px] text-slate-400">Copias archivadas al crear cada nueva versión.</p><div class="mt-4 space-y-2"><?php foreach ($versions as $version): ?><a href="<?= e(url('/documentos/' . $document['id'] . '/versiones/' . $version['id'])) ?>" class="flex items-center justify-between rounded-xl bg-slate-50 p-3 text-xs transition hover:bg-blue-50"><span><strong>v<?= (int) $version['version'] ?></strong><span class="ml-2 text-[10px] text-slate-400"><?= format_date($version['created_at']) ?></span></span><span class="badge <?= status_class($version['estado']) ?>"><?= e($version['estado']) ?></span></a><?php endforeach; ?></div></section><?php endif; ?>
        <section class="rounded-2xl bg-amber-50 p-4 text-[11px] leading-5 text-amber-800 ring-1 ring-amber-100"><div class="flex items-center gap-2 font-semibold"><?= icon('alert', 'h-4 w-4') ?> Antes de aprobar</div><ul class="mt-3 list-disc space-y-1.5 pl-4 text-amber-700/80"><li>Ajusta el contenido a los procesos reales.</li><li>Verifica responsables y recursos.</li><li>Confirma los requisitos legales aplicables.</li><li>Gestiona las firmas que correspondan.</li></ul></section>
        <?php if ($document['estado'] !== 'Obsoleto'): ?><form method="post" action="<?= e(url('/documentos/' . $document['id'] . '/obsoleto')) ?>" data-confirm="¿Marcar este documento como obsoleto? Dejará publicarse como vigente."><?= csrf_field() ?><button type="submit" class="btn-danger-soft w-full">Marcar como obsoleto</button></form><?php endif; ?>
    </aside>
</div>
