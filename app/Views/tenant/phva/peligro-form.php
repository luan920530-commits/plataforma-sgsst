<?php
$classLabels = ['Fisico' => 'Físico', 'Quimico' => 'Químico', 'Biologico' => 'Biológico', 'Biomecanico' => 'Biomecánico', 'Psicosocial' => 'Psicosocial', 'Seguridad' => 'Seguridad', 'Publico' => 'Público', 'Natural' => 'Natural', 'Tecnologico' => 'Tecnológico'];
$value = static fn(string $key, $default = '') => old($key, $hazard[$key] ?? $default);
?>
<div class="page-heading">
    <div><a href="<?= e(url('/matriz-ipevr')) ?>" class="text-link mb-3"><?= icon('arrow-left', 'h-3.5 w-3.5') ?> Volver a la matriz</a><span class="eyebrow">Registro #<?= (int) $hazard['id'] ?></span><h1>Editar peligro y valoración</h1><p>Corrige la identificación o actualiza los niveles. El riesgo se recalcula al guardar.</p></div>
    <span class="badge <?= risk_class($hazard['nivel_riesgo']) ?>">Actual: <?= e($hazard['nivel_riesgo']) ?></span>
</div>
<form method="post" action="<?= e(url('/matriz-ipevr/' . $hazard['id'])) ?>" class="ios-card mt-6 p-5 sm:p-6" data-risk-form>
    <?= csrf_field() ?>
    <div class="grid gap-5 sm:grid-cols-2">
        <div><label class="label" for="proceso">Proceso *</label><input id="proceso" name="proceso" class="input" value="<?= e($value('proceso')) ?>" required><?= field_error('proceso') ?></div>
        <div><label class="label" for="cargo">Cargo o población expuesta *</label><input id="cargo" name="cargo" class="input" value="<?= e($value('cargo')) ?>" required><?= field_error('cargo') ?></div>
        <div><label class="label" for="ubicacion">Ubicación *</label><input id="ubicacion" name="ubicacion" class="input" value="<?= e($value('ubicacion')) ?>" required><?= field_error('ubicacion') ?></div>
        <div><label class="label" for="trabajadores_expuestos">Trabajadores expuestos en esta fila *</label><input id="trabajadores_expuestos" type="number" min="0" step="1" name="trabajadores_expuestos" class="input" value="<?= e($value('trabajadores_expuestos',0)) ?>" required><?= field_error('trabajadores_expuestos') ?></div>
        <div class="sm:col-span-2"><label class="label" for="peligro">Descripción del peligro *</label><input id="peligro" name="peligro" class="input" value="<?= e($value('peligro')) ?>" required><?= field_error('peligro') ?></div>
        <div><label class="label" for="clasificacion">Clasificación *</label><select id="clasificacion" name="clasificacion" class="input"><?php foreach ($classifications as $class): ?><option value="<?= e($class) ?>"<?= selected($value('clasificacion'), $class) ?>><?= e($classLabels[$class]) ?></option><?php endforeach; ?></select></div>
        <div><label class="label" for="efectos">Efectos posibles *</label><input id="efectos" name="efectos" class="input" value="<?= e($value('efectos')) ?>" required><?= field_error('efectos') ?></div>
        <div class="sm:col-span-2"><label class="label" for="controles_existentes">Controles existentes</label><textarea id="controles_existentes" name="controles_existentes" class="input min-h-24"><?= e($value('controles_existentes')) ?></textarea></div>
        <div class="sm:col-span-2"><label class="label" for="medidas_control">Medidas de prevención y control *</label><textarea id="medidas_control" name="medidas_control" class="input min-h-28" required><?= e($value('medidas_control')) ?></textarea><?= field_error('medidas_control') ?><p class="mt-2 text-[11px] text-slate-400">Prioriza eliminación, sustitución e ingeniería antes de controles administrativos y EPP.</p></div>
        <div><label class="label">Metodología *</label><input name="metodologia" class="input" required value="<?= e($value('metodologia','GTC 45')) ?>"></div>
        <div><label class="label">Fecha de evaluación *</label><input type="date" name="fecha_evaluacion" class="input" required value="<?= e($value('fecha_evaluacion')) ?>"></div>
        <div><label class="label">Próxima revisión</label><input type="date" name="proxima_revision" class="input" value="<?= e($value('proxima_revision')) ?>"></div>
        <div><label class="label">Participantes *</label><input name="participantes" class="input" required value="<?= e($value('participantes')) ?>"></div>
        <div class="sm:col-span-2"><label class="label">Jerarquía de controles y justificación *</label><textarea name="jerarquia_controles" class="input min-h-24" required><?= e($value('jerarquia_controles')) ?></textarea></div>
    </div>
    <div class="mt-6 rounded-2xl bg-slate-50 p-4">
        <h2 class="text-sm font-semibold text-slate-700">Valoración cuantitativa</h2><p class="mt-1 text-[11px] text-slate-400">Completa los tres valores para calcular NP = ND × NE y NR = NP × NC, o deja todos vacíos para conservar una valoración manual.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-4">
            <div><label class="label">Deficiencia (ND)</label><select name="nivel_deficiencia" class="input" data-risk-nd><option value="">Sin valorar</option><?php foreach ([0,2,6,10] as $option): ?><option value="<?= $option ?>"<?= selected((string) $value('nivel_deficiencia'), (string) $option) ?>><?= $option ?></option><?php endforeach; ?></select><?= field_error('nivel_deficiencia') ?></div>
            <div><label class="label">Exposición (NE)</label><select name="nivel_exposicion" class="input" data-risk-ne><option value="">Sin valorar</option><?php foreach ([1,2,3,4] as $option): ?><option value="<?= $option ?>"<?= selected((string) $value('nivel_exposicion'), (string) $option) ?>><?= $option ?></option><?php endforeach; ?></select></div>
            <div><label class="label">Consecuencia (NC)</label><select name="nivel_consecuencia" class="input" data-risk-nc><option value="">Sin valorar</option><?php foreach ([10,25,60,100] as $option): ?><option value="<?= $option ?>"<?= selected((string) $value('nivel_consecuencia'), (string) $option) ?>><?= $option ?></option><?php endforeach; ?></select></div>
            <div><label class="label">Nivel manual alternativo</label><select name="nivel_riesgo" class="input" data-risk-fallback><?php foreach (['Bajo','Medio','Alto','Critico'] as $level): ?><option<?= selected($value('nivel_riesgo', 'Medio'), $level) ?>><?= $level ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="mt-4 flex items-center gap-3 rounded-xl bg-white p-3 text-xs"><span class="text-slate-400">Resultado orientativo:</span><strong data-risk-result>Sin valores completos</strong></div>
    </div>
    <div class="mt-6 rounded-2xl bg-amber-50 p-4 text-[11px] leading-5 text-amber-800 ring-1 ring-amber-100">Al cambiar procesos, equipos, controles o la valoración, verifica si también deben actualizarse el plan anual, los procedimientos y la evaluación de riesgos.</div>
    <div class="mt-5 flex flex-wrap justify-end gap-2"><a href="<?= e(url('/matriz-ipevr')) ?>" class="btn-secondary">Cancelar</a><button type="submit" class="btn-primary"><?= icon('check', 'h-4 w-4') ?> Guardar cambios</button></div>
</form>
