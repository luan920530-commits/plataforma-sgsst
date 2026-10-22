<div class="space-y-6">
    <div class="flex justify-between items-start">
        <div>
            <h1 class="text-2xl font-bold text-slate-800"><?= e($empresa['razon_social']) ?></h1>
            <p class="text-slate-500 text-sm mt-1">NIT: <?= e($empresa['nit']) ?></p>
        </div>
        <div class="flex gap-2">
            <a href="<?= base_url("empresas/{$empresa['id']}/editar") ?>"
               class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50">
                <i class="fa-solid fa-pen mr-2"></i> Editar
            </a>
            <a href="<?= base_url("sgsst/empresa/{$empresa['id']}") ?>"
               class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium">
                <i class="fa-solid fa-clipboard-check mr-2"></i> Gestionar SG-SST
            </a>
            <form method="POST" action="<?= base_url('analisis') ?>" class="inline">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="empresa_id" value="<?= $empresa['id'] ?>">
                <button type="submit"
                        class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-medium">
                    <i class="fa-solid fa-plus mr-2"></i> Nuevo análisis
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-500 uppercase">Nivel de riesgo</div>
            <div class="text-xl font-bold text-slate-800 mt-1"><?= e($empresa['nivel_riesgo']) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-500 uppercase">Trabajadores</div>
            <div class="text-xl font-bold text-slate-800 mt-1"><?= (int)$empresa['num_trabajadores'] ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-500 uppercase">Aforo máximo</div>
            <div class="text-xl font-bold text-slate-800 mt-1"><?= (int)$empresa['aforo_max'] ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-500 uppercase">Área</div>
            <div class="text-xl font-bold text-slate-800 mt-1"><?= (float)$empresa['area_m2'] ?> m²</div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200">
            <h2 class="font-semibold text-slate-800">Análisis de riesgo</h2>
        </div>
        <?php if (empty($analisis)): ?>
            <div class="p-8 text-center text-slate-500">
                No hay análisis registrados aún
            </div>
        <?php else: ?>
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="text-left px-4 py-2 text-sm font-semibold text-slate-700">Versión</th>
                        <th class="text-left px-4 py-2 text-sm font-semibold text-slate-700">Fecha</th>
                        <th class="text-left px-4 py-2 text-sm font-semibold text-slate-700">Estado</th>
                        <th class="text-right px-4 py-2 text-sm font-semibold text-slate-700">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($analisis as $a): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-800">v<?= e($a['version']) ?></td>
                            <td class="px-4 py-3 text-slate-600 text-sm"><?= e($a['fecha_elaboracion']) ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    <?= $a['estado'] === 'generado' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?>">
                                    <?= e($a['estado']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="<?= base_url("analisis/{$a['id']}/wizard/paso1") ?>"
                                   class="text-brand-600 hover:underline text-sm">
                                    Continuar <i class="fa-solid fa-arrow-right ml-1"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
