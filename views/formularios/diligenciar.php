<?php $eid = (int)$empresa['id']; ?>
<div class="space-y-5">
  <div class="mac-card p-5 flex flex-wrap justify-between gap-3 items-center">
    <div>
      <p class="text-xs font-semibold" style="color:var(--mac-blue)">FORMULARIO WEB · ESTÁNDAR <?= e($formato['estandar']) ?></p>
      <h1 class="text-2xl font-bold">📝 <?= e($formato['titulo']) ?></h1>
      <p class="text-sm mac-muted"><?= e($empresa['razon_social']) ?> · <?= e($formato['descripcion']) ?></p>
      <p class="text-xs mac-muted mt-1">Basado en: <i><?= e($formato['archivo_origen']) ?></i></p>
    </div>
    <div class="flex gap-2">
      <a class="mac-btn-secondary" href="<?= base_url("sgsst/empresa/{$eid}/formularios") ?>">← Formularios</a>
      <a class="mac-btn-secondary" href="<?= base_url("sgsst/empresa/{$eid}/formularios/{$codigo}.csv") ?>">⬇ Exportar CSV</a>
    </div>
  </div>

  <?php if (!empty($formato['confidencial'])): ?>
  <div class="mac-alert" style="background:rgba(255,59,48,.08)">🔒 <b>Información confidencial:</b> este formulario contiene datos sensibles (Ley 1581/2012). Solo puede diligenciarlo y consultarlo el propietario o el representante de la empresa.</div>
  <?php endif; ?>

  <?php if ($puedeGestionar): ?>
  <form method="POST" action="<?= base_url("sgsst/empresa/{$eid}/formularios/{$codigo}") ?>" class="mac-card p-5 space-y-3">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="grid md:grid-cols-2 gap-3">
    <?php foreach ($formato['campos'] as [$clave, $etiqueta, $tipo, $opciones, $requerido]): ?>
      <label class="block text-sm font-medium <?= $tipo === 'area' ? 'md:col-span-2' : '' ?>">
        <?= e($etiqueta) ?><?= $requerido ? ' <span style="color:var(--mac-red)">*</span>' : '' ?>
        <?php if ($tipo === 'area'): ?>
          <textarea name="campo[<?= e($clave) ?>]" rows="3" <?= $requerido ? 'required' : '' ?> class="mac-input w-full mt-1" style="padding:.6rem"></textarea>
        <?php elseif ($tipo === 'fecha'): ?>
          <input type="date" name="campo[<?= e($clave) ?>]" value="<?= $clave === 'fecha' ? date('Y-m-d') : '' ?>" <?= $requerido ? 'required' : '' ?> class="mac-input w-full mt-1" style="padding:.6rem">
        <?php elseif ($tipo === 'numero'): ?>
          <input type="number" step="any" name="campo[<?= e($clave) ?>]" <?= $requerido ? 'required' : '' ?> class="mac-input w-full mt-1" style="padding:.6rem">
        <?php elseif ($tipo === 'seleccion'): ?>
          <select name="campo[<?= e($clave) ?>]" <?= $requerido ? 'required' : '' ?> class="mac-input w-full mt-1" style="padding:.6rem">
            <option value="">— Seleccionar —</option>
            <?php foreach ($opciones as $op): ?><option><?= e($op) ?></option><?php endforeach; ?>
          </select>
        <?php elseif ($tipo === 'multiple'): ?>
          <span class="block mt-1 space-y-1 font-normal">
            <?php foreach ($opciones as $op): ?>
            <label class="flex gap-2 items-center text-sm"><input type="checkbox" name="campo[<?= e($clave) ?>][]" value="<?= e($op) ?>"> <?= e($op) ?></label>
            <?php endforeach; ?>
          </span>
        <?php elseif ($tipo === 'trabajador'): ?>
          <select name="campo[<?= e($clave) ?>]" <?= $requerido ? 'required' : '' ?> class="mac-input w-full mt-1" style="padding:.6rem">
            <option value="">— Seleccionar trabajador —</option>
            <?php foreach ($personal as $per): ?><option value="<?= (int)$per['id'] ?>"><?= e($per['nombre_completo']) ?><?= $per['cargo'] ? ' · ' . e($per['cargo']) : '' ?></option><?php endforeach; ?>
          </select>
          <?php if (empty($personal)): ?><span class="text-xs mac-muted block mt-1">Sin personal registrado: cárgalo en el módulo Personal/PPPRAE.</span><?php endif; ?>
        <?php else: ?>
          <input type="text" name="campo[<?= e($clave) ?>]" <?= $requerido ? 'required' : '' ?> class="mac-input w-full mt-1" style="padding:.6rem">
        <?php endif; ?>
      </label>
    <?php endforeach; ?>
    </div>
    <button class="mac-btn">💾 Guardar formulario</button>
    <span class="text-xs mac-muted ml-2">Se guarda con fecha, responsable y trazabilidad; si tiene fecha compromiso, aparecerá en las alertas.</span>
  </form>
  <?php else: ?>
  <div class="mac-alert mac-alert-info">👁 Tu acceso es de solo lectura: puedes consultar el historial, pero no diligenciar este formulario.</div>
  <?php endif; ?>

  <section class="mac-card p-5">
    <h2 class="font-bold mb-2">🗂 Historial de este formulario</h2>
    <?php if (empty($historial)): ?><p class="text-sm mac-muted">Sin registros todavía.</p>
    <?php else: ?><ul class="text-sm space-y-1.5">
      <?php foreach ($historial as $r): ?>
      <li class="flex flex-wrap gap-2 items-center justify-between border-b pb-1.5" style="border-color:var(--mac-stroke)">
        <span>#<?= (int)$r['id'] ?> · 📅 <?= e($r['fecha']) ?><?= $r['fecha_vencimiento'] ? ' · vence ' . e($r['fecha_vencimiento']) : '' ?></span>
        <span class="flex gap-2 items-center">
          <span class="mac-badge" style="<?= $r['estado']==='cerrado' ? 'background:#dcfae5;color:#067647' : 'background:#fef0c7;color:#93370d' ?>"><?= e($r['estado']) ?></span>
          <a class="underline" style="color:var(--mac-blue)" href="<?= base_url("sgsst/formularios/{$r['id']}") ?>">Ver / imprimir</a>
        </span>
      </li><?php endforeach; ?>
    </ul><?php endif; ?>
  </section>
</div>
