<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-slate-800">Empresas</h1>
        <a href="<?= base_url('empresas/nueva') ?>"
           class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-medium transition">
            <i class="fa-solid fa-plus mr-2"></i> Nueva
        </a>
    </div>

    <?php if (empty($empresas)): ?>
        <div class="bg-white rounded-xl border border-dashed border-slate-300 p-12 text-center">
            <p class="text-slate-500">Sin empresas registradas</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($empresas as $e): ?>
                <a href="<?= base_url("empresas/{$e['id']}") ?>"
                   class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md hover:border-brand-300 transition">
                    <div class="font-semibold text-slate-800 truncate"><?= e($e['razon_social']) ?></div>
                    <div class="text-sm text-slate-500 mt-1">NIT: <?= e($e['nit']) ?></div>
                    <div class="text-xs text-slate-400 mt-2">
                        <i class="fa-solid fa-users mr-1"></i> <?= (int)$e['num_trabajadores'] ?> trabajadores
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
