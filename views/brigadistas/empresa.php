<div class="space-y-6">
    <div class="flex items-center gap-4">
        <a href="<?= base_url('brigadistas') ?>" class="p-2 rounded-lg hover:bg-slate-100">
            <i class="fa-solid fa-arrow-left text-slate-600"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Brigada de emergencias</h1>
            <p class="text-slate-500 text-sm"><?= e($empresa['razon_social']) ?></p>
        </div>
    </div>

    <!-- Estado -->
    <?php
    $totalBrig = count($brigadistas);
    $cumple    = $totalBrig >= $minimo;
    ?>
    <div class="p-4 rounded-lg border flex items-start gap-3
        <?= $cumple ? 'bg-green-50 border-green-200 text-green-800' : 'bg-yellow-50 border-yellow-200 text-yellow-800' ?>">
        <i class="fa-solid <?= $cumple ? 'fa-circle-check' : 'fa-triangle-exclamation' ?> mt-0.5"></i>
        <div class="flex-1 text-sm">
            <strong>Resolución 256/2014:</strong>
            Brigada actual: <strong><?= $totalBrig ?></strong> de
            <strong><?= $minimo ?></strong> requeridos.
            <?php if (!$cumple): ?>
                Faltan <strong><?= $minimo - $totalBrig ?></strong> brigadistas.
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Brigadistas actuales -->
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 bg-slate-50">
                <h3 class="font-semibold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-user-shield text-amber-600"></i>
                    Brigadistas (<?= $totalBrig ?>)
                </h3>
            </div>

            <?php if (empty($brigadistas)): ?>
                <div class="p-8 text-center text-slate-500 text-sm">
                    <i class="fa-solid fa-user-slash text-3xl text-slate-300 mb-2"></i>
                    <p>Aún no hay brigadistas asignados</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-slate-100">
                    <?php foreach ($brigadistas as $p): ?>
                        <div class="p-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center font-bold">
                                    <?= strtoupper(substr($p['nombre_completo'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="font-medium text-slate-800"><?= e($p['nombre_completo']) ?></div>
                                    <div class="text-xs text-slate-500">
                                        <?= e($p['entrenado_brigada']) ?>
                                    </div>
                                </div>
                            </div>
                            <form method="POST" action="<?= base_url("brigadistas/{$p['id']}/quitar") ?>"
                                  onsubmit="return confirm('¿Quitar de la brigada?')">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="empresa_id" value="<?= $empresa['id'] ?>">
                                <button type="submit" class="text-red-500 hover:bg-red-50 p-2 rounded">
                                    <i class="fa-solid fa-user-minus"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Candidatos -->
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 bg-slate-50">
                <h3 class="font-semibold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-users text-slate-600"></i>
                    Candidatos (<?= count($candidatos) ?>)
                </h3>
            </div>

            <?php if (empty($candidatos)): ?>
                <div class="p-8 text-center text-slate-500 text-sm">
                    <p>No hay personal sin asignar</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-slate-100">
                    <?php foreach ($candidatos as $p): ?>
                        <div class="p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="font-medium text-slate-800"><?= e($p['nombre_completo']) ?></div>
                                <div class="text-xs text-slate-500"><?= e($p['cargo']) ?></div>
                            </div>
                            <form method="POST" action="<?= base_url('brigadistas/asignar') ?>"
                                  class="flex gap-2 items-center">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="personal_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="empresa_id" value="<?= $empresa['id'] ?>">

                                <select name="tipo"
                                        class="flex-1 px-2 py-1.5 text-sm border border-slate-200 rounded">
                                    <option>Primeros Auxilios</option>
                                    <option>Evacuación</option>
                                    <option>Incendios</option>
                                    <option>Primeros Auxilios, Evacuación</option>
                                    <option>Primeros Auxilios, Evacuación, Incendios</option>
                                </select>
                                <button type="submit"
                                        class="px-3 py-1.5 text-sm bg-brand-600 hover:bg-brand-700 text-white rounded font-medium whitespace-nowrap">
                                    <i class="fa-solid fa-user-plus mr-1"></i> Asignar
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
