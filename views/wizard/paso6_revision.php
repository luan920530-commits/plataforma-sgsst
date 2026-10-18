<?php require BASE_PATH . '/views/partials/wizard_steps.php'; ?>

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <div class="bg-brand-600 text-white px-6 py-4">
        <h2 class="text-xl font-semibold flex items-center gap-3">
            <i class="fa-solid fa-clipboard-check"></i>
            Paso 6 — Revisión
        </h2>
        <p class="text-brand-100 text-sm mt-1">Verifica los resultados antes de generar los documentos</p>
    </div>

    <div class="p-6 space-y-6">

        <!-- Alertas normativas -->
        <?php if (!empty($alertas)): ?>
            <div class="space-y-2">
                <?php foreach ($alertas as $a): ?>
                    <div class="p-3 rounded-lg border flex items-start gap-3
                        <?= $a['tipo'] === 'error'   ? 'bg-red-50 border-red-200 text-red-800'
                            : ($a['tipo'] === 'warning' ? 'bg-yellow-50 border-yellow-200 text-yellow-800'
                            : 'bg-blue-50 border-blue-200 text-blue-800') ?>">
                        <i class="fa-solid
                            <?= $a['tipo'] === 'error'   ? 'fa-circle-xmark'
                                : ($a['tipo'] === 'warning' ? 'fa-triangle-exclamation'
                                : 'fa-circle-info') ?> mt-0.5"></i>
                        <div class="flex-1 text-sm">
                            <strong><?= e($a['norma']) ?>:</strong>
                            <?= e($a['mensaje']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="p-4 bg-green-50 border border-green-200 rounded-lg flex items-center gap-3 text-green-800">
                <i class="fa-solid fa-circle-check"></i>
                <span class="text-sm font-medium">Sin alertas normativas. Todo en orden.</span>
            </div>
        <?php endif; ?>

        <!-- Matriz consolidada -->
        <div class="border border-slate-200 rounded-xl overflow-hidden">
            <div class="bg-slate-50 px-4 py-3">
                <h3 class="font-semibold text-slate-800">
                    <i class="fa-solid fa-table-cells mr-2"></i>
                    Matriz consolidada de amenazas
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 border-b border-slate-200">
                        <tr>
                            <th class="text-left px-3 py-2 font-semibold text-slate-700">Amenaza</th>
                            <th class="text-center px-3 py-2 font-semibold text-slate-700">Personas</th>
                            <th class="text-center px-3 py-2 font-semibold text-slate-700">Recursos</th>
                            <th class="text-center px-3 py-2 font-semibold text-slate-700">Sistemas</th>
                            <th class="text-center px-3 py-2 font-semibold text-slate-700">Nivel</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($amenazas as $a):
                            $nivelColor = match($a['nivel_riesgo']) {
                                'ALTO'  => 'bg-red-100 text-red-700',
                                'MEDIO' => 'bg-yellow-100 text-yellow-700',
                                'BAJO'  => 'bg-green-100 text-green-700',
                                default => 'bg-slate-100 text-slate-600',
                            };
                        ?>
                            <tr>
                                <td class="px-3 py-2 font-medium text-slate-800">
                                    <?= e($a['nombre']) ?>
                                </td>
                                <td class="px-3 py-2 text-center font-mono">
                                    <?= number_format((float)$a['total_personas'], 2) ?>
                                </td>
                                <td class="px-3 py-2 text-center font-mono">
                                    <?= number_format((float)$a['total_recursos'], 2) ?>
                                </td>
                                <td class="px-3 py-2 text-center font-mono">
                                    <?= number_format((float)$a['total_sistemas'], 2) ?>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="px-2 py-1 rounded text-xs font-bold <?= $nivelColor ?>">
                                        <?= e($a['nivel_riesgo'] ?: 'Sin evaluar') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-between pt-6 border-t border-slate-200">
            <a href="<?= base_url("analisis/{$analisisId}/wizard/paso5") ?>"
               class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50">
                <i class="fa-solid fa-arrow-left mr-2"></i> Anterior
            </a>
            <a href="<?= base_url("analisis/{$analisisId}/wizard/paso7") ?>"
               class="px-6 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-medium">
                Continuar <i class="fa-solid fa-arrow-right ml-2"></i>
            </a>
        </div>
    </div>
</div>
