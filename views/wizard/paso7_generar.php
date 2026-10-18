<?php require BASE_PATH . '/views/partials/wizard_steps.php'; ?>

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <div class="bg-brand-600 text-white px-6 py-4">
        <h2 class="text-xl font-semibold flex items-center gap-3">
            <i class="fa-solid fa-file-export"></i>
            Paso 7 — Generar documentos
        </h2>
        <p class="text-brand-100 text-sm mt-1">
            Se generarán los 5 documentos del plan en formato ZIP
        </p>
    </div>

    <div class="p-6 space-y-6">

        <!-- Listado de documentos a generar -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php
            $docs = [
                ['01_Lista_Chequeo.docx',       'Lista de chequeo',                   'fa-clipboard-list',    'blue'],
                ['02_Analisis_Vulnerabilidad.xlsx', 'Análisis de vulnerabilidad',     'fa-chart-simple',      'green'],
                ['03_Matriz_Consolidada.xlsx', 'Matriz consolidada',                 'fa-table-cells',       'purple'],
                ['04_MEDEVAC.xlsx',            'MEDEVAC (personal)',                 'fa-users',             'orange'],
                ['05_PPPRAE.docx',             'Plan de emergencias completo',       'fa-book',              'red'],
            ];
            ?>
            <?php foreach ($docs as [$nombre, $desc, $icono, $color]): ?>
                <div class="p-4 border border-slate-200 rounded-lg flex items-start gap-3">
                    <div class="w-10 h-10 rounded-lg bg-<?= $color ?>-100 text-<?= $color ?>-600 flex items-center justify-center">
                        <i class="fa-solid <?= $icono ?>"></i>
                    </div>
                    <div class="flex-1">
                        <div class="font-medium text-slate-800 text-sm"><?= $nombre ?></div>
                        <div class="text-xs text-slate-500 mt-0.5"><?= $desc ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Botón de generar -->
        <form method="POST" action="<?= base_url("analisis/{$analisisId}/generar") ?>"
              class="pt-6 border-t border-slate-200">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="flex justify-between items-center">
                <a href="<?= base_url("analisis/{$analisisId}/wizard/paso6") ?>"
                   class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50">
                    <i class="fa-solid fa-arrow-left mr-2"></i> Anterior
                </a>
                <button type="submit"
                        class="px-8 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold shadow-lg shadow-green-500/30 transition">
                    <i class="fa-solid fa-download mr-2"></i>
                    Generar y descargar ZIP
                </button>
            </div>
        </form>

        <!-- Historial -->
        <?php if (!empty($documentos)): ?>
            <div class="pt-6 border-t border-slate-200">
                <h3 class="font-semibold text-slate-800 mb-3">
                    <i class="fa-solid fa-clock-rotate-left mr-2"></i>
                    Generaciones anteriores
                </h3>
                <div class="space-y-2">
                    <?php foreach ($documentos as $d): ?>
                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                            <div class="flex items-center gap-3">
                                <i class="fa-solid fa-file-zipper text-slate-400"></i>
                                <div>
                                    <div class="text-sm font-medium text-slate-800"><?= e($d['nombre_archivo']) ?></div>
                                    <div class="text-xs text-slate-500"><?= e($d['created_at']) ?></div>
                                </div>
                            </div>
                            <a href="<?= base_url("analisis/{$analisisId}/descargar/{$d['nombre_archivo']}") ?>"
                               class="text-brand-600 hover:underline text-sm">
                                Descargar <i class="fa-solid fa-download ml-1"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
