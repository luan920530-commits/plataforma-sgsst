<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Análisis de riesgo</h1>
            <p class="text-slate-500 text-sm mt-0.5">
                <?= $totalAnalisis ?> análisis en total
            </p>
        </div>
        <a href="<?= base_url('empresas') ?>"
           class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-medium shadow-sm transition">
            <i class="fa-solid fa-plus mr-2"></i> Nuevo análisis
        </a>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-500 uppercase">Total</div>
            <div class="text-2xl font-bold text-slate-800 mt-1"><?= $totalAnalisis ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-500 uppercase">Borrador</div>
            <div class="text-2xl font-bold text-yellow-600 mt-1"><?= $totalBorrador ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-500 uppercase">Completos</div>
            <div class="text-2xl font-bold text-blue-600 mt-1"><?= $totalCompletos ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-500 uppercase">Generados</div>
            <div class="text-2xl font-bold text-green-600 mt-1"><?= $totalGenerados ?></div>
        </div>
    </div>

    <!-- Tabla -->
    <?php if (empty($analisis)): ?>
        <div class="bg-white rounded-xl border border-dashed border-slate-300 p-12 text-center">
            <i class="fa-solid fa-chart-line text-4xl text-slate-300 mb-3"></i>
            <p class="text-slate-500 font-medium">No hay análisis registrados</p>
            <p class="text-slate-400 text-sm mt-1 mb-4">
                Los análisis se crean desde la ficha de cada empresa
            </p>
            <a href="<?= base_url('empresas') ?>"
               class="inline-block px-4 py-2 bg-brand-600 text-white rounded-lg font-medium">
                <i class="fa-solid fa-building mr-2"></i> Ir a empresas
            </a>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold text-slate-700">Empresa</th>
                            <th class="text-left px-4 py-3 font-semibold text-slate-700">NIT</th>
                            <th class="text-left px-4 py-3 font-semibold text-slate-700">Versión</th>
                            <th class="text-left px-4 py-3 font-semibold text-slate-700">Fecha</th>
                            <th class="text-center px-4 py-3 font-semibold text-slate-700">Amenazas</th>
                            <th class="text-center px-4 py-3 font-semibold text-slate-700">Docs</th>
                            <th class="text-left px-4 py-3 font-semibold text-slate-700">Estado</th>
                            <th class="w-40"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($analisis as $a): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-800"><?= e($a['razon_social']) ?></div>
                                    <div class="text-xs text-slate-500">
                                        <?= (int)$a['num_trabajadores'] ?> trabajadores
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600"><?= e($a['nit']) ?></td>
                                <td class="px-4 py-3">
                                    <span class="inline-block px-2 py-1 text-xs bg-slate-100 text-slate-700 rounded font-medium">
                                        v<?= e($a['version']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600 text-xs">
                                    <?= e($a['fecha_elaboracion']) ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-block min-w-[2rem] px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded font-semibold">
                                        <?= (int)$a['total_amenazas'] ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ((int)$a['total_documentos'] > 0): ?>
                                        <span class="inline-block min-w-[2rem] px-2 py-1 text-xs bg-green-100 text-green-700 rounded font-semibold">
                                            <?= (int)$a['total_documentos'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-300">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php
                                    $colorEstado = match($a['estado']) {
                                        'generado' => 'bg-green-100 text-green-700',
                                        'completo' => 'bg-blue-100 text-blue-700',
                                        default    => 'bg-yellow-100 text-yellow-700',
                                    };
                                    ?>
                                    <span class="inline-block px-2 py-1 text-xs rounded font-medium <?= $colorEstado ?>">
                                        <?= e(ucfirst($a['estado'])) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="<?= base_url("analisis/{$a['id']}/wizard/paso1") ?>"
                                       class="inline-flex items-center gap-1 text-brand-600 hover:underline text-sm">
                                        Continuar <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
