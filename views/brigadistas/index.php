<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Brigadistas</h1>
        <p class="text-slate-500 text-sm mt-0.5">
            Cumplimiento Resolución 256/2014 — mínimo 20% de la población laboral
        </p>
    </div>

    <!-- Resumen global -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <div class="text-xs text-slate-500 uppercase">Total brigadistas</div>
            <div class="text-3xl font-bold text-amber-600 mt-1"><?= $totalBrigadistas ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <div class="text-xs text-slate-500 uppercase">Mínimo requerido</div>
            <div class="text-3xl font-bold text-slate-800 mt-1"><?= $totalRequeridos ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <div class="text-xs text-slate-500 uppercase">Estado global</div>
            <div class="mt-1">
                <?php if ($totalBrigadistas >= $totalRequeridos): ?>
                    <span class="text-2xl font-bold text-green-600">
                        <i class="fa-solid fa-circle-check"></i> Cumple
                    </span>
                <?php else: ?>
                    <span class="text-2xl font-bold text-red-600">
                        <i class="fa-solid fa-circle-xmark"></i> Faltan <?= $totalRequeridos - $totalBrigadistas ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Empresas -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200">
            <h3 class="font-semibold text-slate-800">Por empresa</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-slate-700">Empresa</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-700">Trabajadores</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-700">Brigadistas</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-700">Mínimo</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-700">Estado</th>
                    <th class="w-32"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($empresas as $e): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-800"><?= e($e['razon_social']) ?></td>
                        <td class="px-4 py-3 text-slate-600"><?= (int)$e['num_trabajadores'] ?></td>
                        <td class="px-4 py-3 text-slate-600 font-semibold"><?= $e['total_brigadistas'] ?></td>
                        <td class="px-4 py-3 text-slate-600"><?= $e['minimo_brigadistas'] ?></td>
                        <td class="px-4 py-3">
                            <?php if ($e['cumple']): ?>
                                <span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded font-medium">
                                    <i class="fa-solid fa-check mr-1"></i> Cumple
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded font-medium">
                                    <i class="fa-solid fa-xmark mr-1"></i> Faltan <?= $e['minimo_brigadistas'] - $e['total_brigadistas'] ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="<?= base_url("brigadistas/empresa/{$e['id']}") ?>"
                               class="text-brand-600 hover:underline text-sm">
                                Gestionar <i class="fa-solid fa-arrow-right ml-1"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
