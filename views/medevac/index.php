<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">MEDEVAC</h1>
            <p class="text-slate-500 text-sm mt-0.5">
                Información médica de evacuación por empresa
            </p>
        </div>
    </div>

    <?php if (empty($empresas)): ?>
        <div class="bg-white rounded-xl border border-dashed border-slate-300 p-12 text-center">
            <i class="fa-solid fa-notes-medical text-4xl text-slate-300 mb-3"></i>
            <p class="text-slate-500">No hay empresas registradas</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($empresas as $e): ?>
                <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition">
                    <div class="flex items-start justify-between mb-3">
                        <div class="w-10 h-10 rounded-lg bg-red-100 text-red-600 flex items-center justify-center">
                            <i class="fa-solid fa-notes-medical"></i>
                        </div>
                        <span class="text-xs text-slate-400">
                            <?= (int)$e['total_personal'] ?> personas
                        </span>
                    </div>
                    <div class="font-semibold text-slate-800"><?= e($e['razon_social']) ?></div>
                    <div class="text-xs text-slate-500 mt-1">NIT: <?= e($e['nit']) ?></div>

                    <div class="flex gap-2 mt-4 pt-4 border-t border-slate-100">
                        <a href="<?= base_url("medevac/empresa/{$e['id']}") ?>"
                           class="flex-1 text-center px-3 py-1.5 text-sm bg-brand-50 text-brand-700 hover:bg-brand-100 rounded-lg font-medium transition">
                            <i class="fa-solid fa-eye mr-1"></i> Ver
                        </a>
                        <a href="<?= base_url("medevac/empresa/{$e['id']}/exportar") ?>"
                           class="flex-1 text-center px-3 py-1.5 text-sm bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-lg font-medium transition">
                            <i class="fa-solid fa-download mr-1"></i> XLSX
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
