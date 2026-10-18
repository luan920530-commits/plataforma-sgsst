<div class="space-y-6">
    <div class="flex items-center gap-4">
        <a href="<?= base_url('personal') ?>"
           class="p-2 rounded-lg hover:bg-slate-100 transition">
            <i class="fa-solid fa-arrow-left text-slate-600"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800"><?= e($empresa['razon_social']) ?></h1>
            <p class="text-slate-500 text-sm">Personal registrado · <?= count($personal) ?> trabajadores</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <div class="text-xs text-slate-500 uppercase">Total personal</div>
            <div class="text-3xl font-bold text-slate-800 mt-1"><?= count($personal) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <div class="text-xs text-slate-500 uppercase">Brigadistas</div>
            <div class="text-3xl font-bold text-amber-600 mt-1">
                <?= count(array_filter($personal, fn($p) => !empty($p['entrenado_brigada']))) ?>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <div class="text-xs text-slate-500 uppercase">Con firma</div>
            <div class="text-3xl font-bold text-green-600 mt-1">
                <?= count(array_filter($personal, fn($p) => !empty($p['firma_url']))) ?>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <div class="text-xs text-slate-500 uppercase">Mín. requerido</div>
            <div class="text-3xl font-bold text-slate-800 mt-1">
                <?= (int) ceil(($empresa['num_trabajadores'] ?? 0) * 0.20) ?>
            </div>
        </div>
    </div>

    <?php if (empty($personal)): ?>
        <div class="bg-white rounded-xl border border-dashed border-slate-300 p-12 text-center">
            <i class="fa-solid fa-users text-4xl text-slate-300 mb-3"></i>
            <p class="text-slate-500">No hay personal registrado aún</p>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Nombre</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Cédula</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Cargo</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Celular</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">EPS</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Brigada</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Firma</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($personal as $p): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-800"><?= e($p['nombre_completo']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= e($p['cedula']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= e($p['cargo']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= e($p['celular']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= e($p['eps']) ?></td>
                            <td class="px-4 py-3">
                                <?php if ($p['entrenado_brigada']): ?>
                                    <span class="inline-block px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded">
                                        <?= e($p['entrenado_brigada']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400 text-xs">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php if (!empty($p['firma_url'])): ?>
                                    <img src="<?= base_url($p['firma_url']) ?>"
                                         alt="Firma" class="h-8 bg-white border border-slate-200 rounded">
                                <?php else: ?>
                                    <span class="text-slate-400 text-xs">Sin firma</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
