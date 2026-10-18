<?php require BASE_PATH . '/views/partials/wizard_steps.php'; ?>

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <div class="bg-brand-600 text-white px-6 py-4">
        <h2 class="text-xl font-semibold flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation"></i>
            Paso 3 — Identificación de amenazas
        </h2>
        <p class="text-brand-100 text-sm mt-1">Marca las amenazas que aplican a la empresa</p>
    </div>

    <div class="p-6 space-y-4">
        <?php
        $categorias = [
            'natural'   => ['nombre' => 'Origen natural',   'icono' => 'fa-leaf',           'color' => 'green'],
            'tecnico'   => ['nombre' => 'Origen técnico',   'icono' => 'fa-gears',          'color' => 'orange'],
            'social'    => ['nombre' => 'Origen social',    'icono' => 'fa-users',          'color' => 'red'],
            'biologico' => ['nombre' => 'Origen biológico', 'icono' => 'fa-virus',          'color' => 'purple'],
        ];
        ?>

        <?php foreach ($categorias as $tipo => $cat): ?>
            <div class="border border-slate-200 rounded-xl overflow-hidden">
                <div class="bg-<?= $cat['color'] ?>-50 px-4 py-3 border-b border-<?= $cat['color'] ?>-100">
                    <h3 class="font-semibold text-<?= $cat['color'] ?>-800 flex items-center gap-2">
                        <i class="fa-solid <?= $cat['icono'] ?>"></i>
                        <?= $cat['nombre'] ?>
                    </h3>
                </div>
                <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                    <?php foreach ($amenazas as $a): if ($a['tipo'] !== $tipo) continue; ?>
                        <label class="flex items-center gap-3 p-3 border border-slate-200 rounded-lg hover:bg-slate-50 cursor-pointer transition">
                            <input type="checkbox" checked
                                   class="w-4 h-4 text-brand-600 rounded focus:ring-brand-500">
                            <span class="flex-1 text-slate-800 font-medium"><?= e($a['nombre']) ?></span>
                            <select class="text-xs px-2 py-1 border border-slate-200 rounded">
                                <option <?= $a['origen'] === 'interna' ? 'selected' : '' ?>>Interna</option>
                                <option <?= $a['origen'] === 'externa' ? 'selected' : '' ?>>Externa</option>
                            </select>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Botones -->
        <div class="flex justify-between pt-6 border-t border-slate-200">
            <a href="<?= base_url("analisis/{$analisisId}/wizard/paso2") ?>"
               class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50">
                <i class="fa-solid fa-arrow-left mr-2"></i> Anterior
            </a>
            <a href="<?= base_url("analisis/{$analisisId}/wizard/paso4") ?>"
               class="px-6 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-medium">
                Siguiente <i class="fa-solid fa-arrow-right ml-2"></i>
            </a>
        </div>
    </div>
</div>
