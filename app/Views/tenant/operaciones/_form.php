<?php
$record = $record ?? [];
$value = static function (string $key) use ($record) {
    $fallback = $record[$key] ?? $record['datos'][$key] ?? '';
    return old($key, $fallback);
};
?>
<div class="grid gap-4 sm:grid-cols-2">
    <div><label class="label">Tipo de registro *</label><select name="tipo" class="input" required><?php foreach ($definition['types'] as $type): ?><option<?= selected($value('tipo') ?: $definition['types'][0], $type) ?>><?= e($type) ?></option><?php endforeach; ?></select></div>
    <div><label class="label">Título / referencia *</label><input name="titulo" class="input" required maxlength="240" value="<?= e($value('titulo')) ?>" placeholder="Describe el objeto sin afirmar que ya se ejecutó"></div>
    <div class="sm:col-span-2"><label class="label">Descripción y alcance *</label><textarea name="descripcion" class="input min-h-24" required maxlength="12000"><?= e($value('descripcion')) ?></textarea></div>
    <div><label class="label">Responsable *</label><input name="responsable" class="input" required maxlength="240" value="<?= e($value('responsable')) ?>"></div>
    <div><label class="label">Recursos asignados / requeridos</label><input name="recursos" class="input" maxlength="12000" value="<?= e($value('recursos')) ?>" placeholder="Humanos, técnicos y financieros"></div>
    <div><label class="label">Fecha programada *</label><input type="date" name="fecha_programada" class="input" required value="<?= e($value('fecha_programada')) ?>"></div>
    <div><label class="label">Fecha real de ejecución</label><input type="date" name="fecha_ejecucion" class="input" value="<?= e($value('fecha_ejecucion')) ?>"><p class="mt-1 text-[10px] text-slate-400">Déjala vacía si todavía no se ha ejecutado.</p></div>
    <div><label class="label">Vencimiento / próxima revisión</label><input type="date" name="fecha_vencimiento" class="input" value="<?= e($value('fecha_vencimiento')) ?>"></div>
    <div><label class="label">Prioridad</label><select name="prioridad" class="input"><?php foreach (['Baja','Media','Alta','Critica'] as $priority): ?><option<?= selected($value('prioridad') ?: 'Media', $priority) ?>><?= $priority ?></option><?php endforeach; ?></select></div>
    <div class="sm:col-span-2"><label class="label">Criterio de matriz / ISO relacionado</label><input name="requisito_clave" class="input" maxlength="40" value="<?= e($value('requisito_clave') ?: ($requisitoClave ?? '')) ?>" placeholder="Ej.: 2.4.1 o ISO 45001 8.1.4"></div>
</div>
<div class="mt-6 rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-100"><h3 class="text-sm font-semibold text-slate-800">Datos específicos de <?= e($definition['title']) ?></h3><div class="mt-4 grid gap-4 sm:grid-cols-2">
    <?php foreach ($definition['fields'] as $field): $name=$field['name']; $classes=in_array($field['type'],['textarea'],true)?'sm:col-span-2':''; ?>
        <div class="<?= $classes ?>"><label class="label"><?= e($field['label']) ?><?= $field['required']?' *':'' ?></label>
        <?php if ($field['type']==='textarea'): ?><textarea name="<?= e($name) ?>" class="input min-h-24" <?= $field['required']?'required':'' ?>><?= e($value($name)) ?></textarea>
        <?php elseif ($field['type']==='select'): ?><select name="<?= e($name) ?>" class="input" <?= $field['required']?'required':'' ?>><option value="">Seleccionar…</option><?php foreach ($field['options'] as $option): ?><option<?= selected($value($name),$option) ?>><?= e($option) ?></option><?php endforeach; ?></select>
        <?php elseif ($field['type']==='employee'): ?><select name="<?= e($name) ?>" class="input" <?= $field['required']?'required':'' ?>><option value=""><?= $field['required']?'Seleccionar persona…':'No vinculada / contratista externo' ?></option><?php foreach ($employees as $employee): ?><option value="<?= (int)$employee['id'] ?>"<?= selected((string)$value($name),(string)$employee['id']) ?>><?= e($employee['nombre']) ?> · <?= e($employee['documento']) ?> · <?= e($employee['cargo']) ?></option><?php endforeach; ?></select>
        <?php else: ?><input type="<?= $field['type']==='number'?'number':($field['type']==='date'?'date':'text') ?>" <?= $field['type']==='number'?'step="any"':'' ?> name="<?= e($name) ?>" class="input" <?= $field['required']?'required':'' ?> value="<?= e($value($name)) ?>"><?php endif; ?></div>
    <?php endforeach; ?>
</div></div>
<div class="mt-5"><label class="label">Resultado real / conclusión</label><textarea name="resultado" class="input min-h-24" maxlength="12000" placeholder="Requerido para completar. No lo confundas con la actividad planeada."><?= e($value('resultado')) ?></textarea></div>
<div class="mt-5 flex flex-wrap justify-end gap-2"><button type="submit" class="btn-primary"><?= icon('check','h-4 w-4') ?> <?= e($submitLabel ?? 'Guardar registro') ?></button></div>
