<?php
$pasoActual = $paso ?? 1;
$pasos = [
    1 => ['Empresa',       'fa-building'],
    2 => ['Inventario',    'fa-boxes-stacked'],
    3 => ['Amenazas',      'fa-triangle-exclamation'],
    4 => ['Vulnerabilidad','fa-chart-simple'],
    5 => ['Personal',      'fa-users'],
    6 => ['Revisión',      'fa-clipboard-check'],
    7 => ['Generar',       'fa-file-export'],
];
$analisisId = $analisisId ?? 0;
?>

<div class="bg-white rounded-xl border border-slate-200 p-4 mb-6 overflow-x-auto">
    <div class="flex items-center min-w-max">
        <?php foreach ($pasos as $n => [$nombre, $icono]):
            $completado = $n < $pasoActual;
            $activo     = $n === $pasoActual;
        ?>
            <div class="flex items-center">
                <a href="<?= base_url("analisis/{$analisisId}/wizard/paso{$n}") ?>"
                   class="flex items-center gap-2 group">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold transition
                        <?= $completado ? 'bg-green-500 text-white'
                            : ($activo ? 'bg-brand-600 text-white ring-4 ring-brand-100'
                            : 'bg-slate-100 text-slate-400 group-hover:bg-slate-200') ?>">
                        <?php if ($completado): ?>
                            <i class="fa-solid fa-check"></i>
                        <?php else: ?>
                            <i class="fa-solid <?= $icono ?>"></i>
                        <?php endif; ?>
                    </div>
                    <div class="hidden lg:block">
                        <div class="text-xs uppercase tracking-wider <?= $activo ? 'text-brand-600' : 'text-slate-400' ?>">
                            Paso <?= $n ?>
                        </div>
                        <div class="text-sm font-medium <?= $activo ? 'text-slate-800' : 'text-slate-500' ?>">
                            <?= $nombre ?>
                        </div>
                    </div>
                </a>
                <?php if ($n < count($pasos)): ?>
                    <div class="w-8 lg:w-16 h-0.5 mx-1 lg:mx-2 rounded
                        <?= $completado ? 'bg-green-500' : 'bg-slate-200' ?>"></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
