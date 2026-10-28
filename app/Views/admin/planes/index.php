<div class="page-heading">
    <div><span class="eyebrow">Configuración comercial</span><h1>Planes del SaaS</h1><p>Define precio, capacidad y el módulo que cada empresa podrá utilizar.</p></div>
    <a href="#nuevo-plan" class="btn-primary"><?= icon('plus', 'h-4 w-4') ?> Nuevo plan</a>
</div>

<div class="mt-5 rounded-2xl bg-blue-50 p-4 text-xs leading-5 text-blue-800 ring-1 ring-blue-100">
    <div class="flex gap-2"><?= icon('shield-plain', 'mt-0.5 h-4 w-4 shrink-0') ?><p><strong>Alcance aplicado en el servidor.</strong> PESVial carga únicamente PESV; Emergencias y Vigilancia carga emergencias, simulacros, programas y evidencias relacionadas. La condición Pagado o Sugerido se asigna en cada empresa y no altera este alcance.</p></div>
</div>

<details id="nuevo-plan" class="ios-card group mt-6 overflow-hidden"<?= $errors !== [] ? ' open' : '' ?>>
    <summary class="cursor-pointer list-none p-5"><div class="flex items-center justify-between"><div><h2 class="text-base font-semibold">Crear plan</h2><p class="mt-1 text-xs text-slate-400">El módulo seleccionado se validará en cada ruta del portal.</p></div><span class="btn-secondary h-9"><?= icon('plus', 'h-4 w-4') ?> Abrir formulario</span></div></summary>
    <form method="post" action="<?= e(url('/admin/planes')) ?>" class="grid gap-5 border-t border-slate-100 p-5 sm:grid-cols-2 lg:grid-cols-3">
        <?= csrf_field() ?>
        <div><label class="label" for="plan-nombre">Nombre *</label><input id="plan-nombre" name="nombre" class="input" required maxlength="80" value="<?= e(old('nombre')) ?>"><?= field_error('nombre') ?></div>
        <div><label class="label" for="plan-precio">Precio mensual COP *</label><input id="plan-precio" name="precio_mensual" type="number" min="0" step="100" class="input" required value="<?= e(old('precio_mensual')) ?>"><?= field_error('precio_mensual') ?></div>
        <div><label class="label" for="plan-modulo">Módulo habilitado *</label><select id="plan-modulo" name="modulo" class="input" required><?php foreach ($moduleOptions as $value => $label): ?><option value="<?= e($value) ?>"<?= selected(old('modulo', 'sgsst'), $value) ?>><?= e($label) ?></option><?php endforeach; ?></select><?= field_error('modulo') ?></div>
        <div><label class="label" for="plan-usuarios">Máximo de usuarios *</label><input id="plan-usuarios" name="max_usuarios" type="number" min="1" class="input" required value="<?= e(old('max_usuarios', '5')) ?>"><?= field_error('max_usuarios') ?></div>
        <div><label class="label" for="plan-empleados">Máximo de empleados *</label><input id="plan-empleados" name="max_empleados" type="number" min="1" class="input" required value="<?= e(old('max_empleados', '50')) ?>"><?= field_error('max_empleados') ?></div>
        <div class="sm:col-span-2 lg:col-span-3"><label class="label" for="plan-descripcion">Descripción *</label><textarea id="plan-descripcion" name="descripcion" class="input min-h-20" required maxlength="500"><?= e(old('descripcion')) ?></textarea><?= field_error('descripcion') ?></div>
        <div class="sm:col-span-2 lg:col-span-3 flex justify-end"><button type="submit" class="btn-primary"><?= icon('check-circle', 'h-4 w-4') ?> Guardar plan</button></div>
    </form>
</details>

<section class="mt-6 grid gap-5 xl:grid-cols-2">
    <?php foreach ($plans as $plan): ?>
        <article class="ios-card p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3"><div><div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-semibold tracking-tight"><?= e($plan['nombre']) ?></h2><span class="badge <?= $plan['activo'] ? 'badge-green' : 'badge-gray' ?>"><?= $plan['activo'] ? 'Activo' : 'Inactivo' ?></span></div><p class="mt-2 text-xs leading-5 text-slate-500"><?= e($plan['descripcion']) ?></p></div><span class="metric-icon h-10 w-10 shrink-0 bg-blue-50 text-blue-600"><?= icon($plan['modulo'] === 'pesv' ? 'shield-plain' : ($plan['modulo'] === 'emergencias' ? 'activity' : 'grid'), 'h-4 w-4') ?></span></div>
            <div class="mt-5 grid grid-cols-3 gap-3 rounded-2xl bg-slate-50 p-4 text-center"><div><strong class="block text-sm text-slate-800"><?= money($plan['precio_mensual']) ?></strong><span class="text-[10px] text-slate-400">por mes</span></div><div><strong class="block text-sm text-slate-800"><?= (int) $plan['max_usuarios'] ?></strong><span class="text-[10px] text-slate-400">usuarios</span></div><div><strong class="block text-sm text-slate-800"><?= (int) $plan['max_empleados'] ?></strong><span class="text-[10px] text-slate-400">empleados</span></div></div>
            <p class="mt-3 text-[11px] font-semibold text-blue-600"><?= e($moduleOptions[$plan['modulo']] ?? $moduleOptions['sgsst']) ?></p>
            <details class="mt-4"><summary class="cursor-pointer text-xs font-semibold text-blue-600">Editar plan</summary>
                <form method="post" action="<?= e(url('/admin/planes/' . $plan['id'])) ?>" class="mt-4 space-y-4 border-t border-slate-100 pt-4">
                    <?= csrf_field() ?>
                    <div><label class="label">Nombre *</label><input name="nombre" class="input" required maxlength="80" value="<?= e($plan['nombre']) ?>"></div>
                    <div><label class="label">Descripción *</label><textarea name="descripcion" class="input min-h-20" required maxlength="500"><?= e($plan['descripcion']) ?></textarea></div>
                    <div><label class="label">Módulo habilitado *</label><select name="modulo" class="input" required><?php foreach ($moduleOptions as $value => $label): ?><option value="<?= e($value) ?>"<?= selected($plan['modulo'], $value) ?>><?= e($label) ?></option><?php endforeach; ?></select><p class="mt-1.5 text-[10px] leading-4 text-amber-600">Cambiarlo modifica el acceso de las empresas que ya tienen este plan.</p></div>
                    <div class="grid gap-4 sm:grid-cols-3"><div><label class="label">Precio COP *</label><input name="precio_mensual" type="number" min="0" step="100" class="input" required value="<?= e($plan['precio_mensual']) ?>"></div><div><label class="label">Usuarios *</label><input name="max_usuarios" type="number" min="1" class="input" required value="<?= (int) $plan['max_usuarios'] ?>"></div><div><label class="label">Empleados *</label><input name="max_empleados" type="number" min="1" class="input" required value="<?= (int) $plan['max_empleados'] ?>"></div></div>
                    <input type="hidden" name="activo" value="0"><label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="activo" value="1" class="checkbox"<?= checked((bool) $plan['activo']) ?>> Disponible para asignar</label>
                    <button type="submit" class="btn-secondary w-full">Guardar cambios</button>
                </form>
            </details>
        </article>
    <?php endforeach; ?>
</section>
