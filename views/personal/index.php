<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Personal</h1>
        <p class="text-slate-500 text-sm mt-0.5">
            Vista consolidada de personal por empresa
        </p>
    </div>

    <?php if (empty($empresas)): ?>
        <div class="bg-white rounded-xl border border-dashed border-slate-300 p-12 text-center">
            <i class="fa-solid fa-users text-4xl text-slate-300 mb-3"></i>
            <p class="text-slate-500">Registra una empresa primero</p>
            <a href="<?= base_url('empresas/nueva') ?>"
               class="mt-4 inline-block px-4 py-2 bg-brand-600 text-white rounded-lg">
                Crear empresa
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($empresas as $e): ?>
                <a href="<?= base_url("personal/empresa/{$e['id']}") ?>"
                   class="block bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md hover:border-brand-300 transition">
                    <div class="flex items-start justify-between mb-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">
                            <i class="fa-solid fa-building"></i>
                        </div>
                        <i class="fa-solid fa-arrow-right text-slate-300"></i>
                    </div>
                    <div class="font-semibold text-slate-800 truncate"><?= e($e['razon_social']) ?></div>
                    <div class="text-xs text-slate-500 mt-1">NIT: <?= e($e['nit']) ?></div>

                    <div class="grid grid-cols-2 gap-3 mt-4 pt-4 border-t border-slate-100">
                        <div>
                            <div class="text-xs text-slate-500">Personal</div>
                            <div class="text-xl font-bold text-slate-800"><?= (int)$e['total_personal'] ?></div>
                        </div>
                        <div>
                            <div class="text-xs text-slate-500">Brigadistas</div>
                            <div class="text-xl font-bold text-amber-600"><?= (int)$e['total_brigadistas'] ?></div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
