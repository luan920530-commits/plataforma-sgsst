<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="<?= base_url('medevac') ?>" class="p-2 rounded-lg hover:bg-slate-100">
                <i class="fa-solid fa-arrow-left text-slate-600"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">MEDEVAC</h1>
                <p class="text-slate-500 text-sm"><?= e($empresa['razon_social']) ?></p>
            </div>
        </div>
        <a href="<?= base_url("medevac/empresa/{$empresa['id']}/exportar") ?>"
           class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium">
            <i class="fa-solid fa-download mr-2"></i> Exportar XLSX
        </a>
    </div>

    <?php if (empty($personal)): ?>
        <div class="bg-white rounded-xl border border-dashed border-slate-300 p-12 text-center">
            <i class="fa-solid fa-user-plus text-4xl text-slate-300 mb-3"></i>
            <p class="text-slate-500">No hay personal registrado</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($personal as $p): ?>
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold">
                                <?= strtoupper(substr($p['nombre_completo'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="font-semibold text-slate-800"><?= e($p['nombre_completo']) ?></div>
                                <div class="text-xs text-slate-500"><?= e($p['cargo'] ?: 'Sin cargo') ?></div>
                            </div>
                        </div>
                        <?php if ($p['entrenado_brigada']): ?>
                            <span class="px-2 py-1 text-xs bg-amber-100 text-amber-700 rounded">Brigadista</span>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <div class="text-xs text-slate-500">Cédula</div>
                            <div class="text-slate-800"><?= e($p['cedula'] ?: '—') ?></div>
                        </div>
                        <div>
                            <div class="text-xs text-slate-500">RH</div>
                            <div class="text-slate-800"><?= e($p['rh'] ?: '—') ?></div>
                        </div>
                        <div>
                            <div class="text-xs text-slate-500">EPS</div>
                            <div class="text-slate-800"><?= e($p['eps'] ?: '—') ?></div>
                        </div>
                        <div>
                            <div class="text-xs text-slate-500">AFP</div>
                            <div class="text-slate-800"><?= e($p['afp'] ?: '—') ?></div>
                        </div>
                        <div class="col-span-2">
                            <div class="text-xs text-slate-500">Contacto de emergencia</div>
                            <div class="text-slate-800">
                                <?= e($p['contacto_emergencia_nombre'] ?: '—') ?>
                                <?php if ($p['contacto_emergencia_tel']): ?>
                                    · <span class="text-slate-600"><?= e($p['contacto_emergencia_tel']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($p['alergias']): ?>
                            <div class="col-span-2 p-2 bg-red-50 border border-red-100 rounded-lg">
                                <div class="text-xs text-red-700 font-medium">
                                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                                    Alergias / medicamentos
                                </div>
                                <div class="text-sm text-red-800 mt-0.5"><?= e($p['alergias']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($p['firma_url'])):
                        $rutaFirma = BASE_PATH . '/public/' . $p['firma_url'];
                        if (file_exists($rutaFirma)): ?>
                            <div class="mt-4 pt-4 border-t border-slate-100">
                                <div class="text-xs text-slate-500 mb-1">Firma</div>
                                <img src="<?= base_url($p['firma_url']) ?>" alt="Firma"
                                     class="h-12 bg-white border border-slate-200 rounded">
                            </div>
                        <?php endif;
                    endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
