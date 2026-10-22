<?php $eid = (int)$empresa['id'];
$badge = fn($n) => match($n) {'I'=>'background:#ffe5e5;color:#b42318','II'=>'background:#fef0c7;color:#93370d','III'=>'background:#fef9c3;color:#854d0e',default=>'background:#dcfae5;color:#067647'};
?>
<div class="space-y-5">
  <div class="mac-card p-5 flex flex-wrap justify-between gap-3 items-center">
    <div>
      <p class="text-xs font-semibold tracking-wide" style="color:var(--mac-blue)">SG-SST · HACER · GTC 45</p>
      <h1 class="text-2xl font-bold">Matriz de peligros IPVR</h1>
      <p class="text-sm mac-muted"><?= e($empresa['razon_social']) ?> · Identificación de peligros, evaluación y valoración de riesgos</p>
    </div>
    <div class="flex gap-2">
      <a class="mac-btn-secondary" href="<?= base_url("sgsst/empresa/{$eid}") ?>">← Panel SG-SST</a>
      <a class="mac-btn-secondary" href="<?= base_url("sgsst/empresa/{$eid}/matriz.csv") ?>">⬇ Exportar CSV</a>
    </div>
  </div>

  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <?php foreach (['I'=>'No aceptable','II'=>'Control específico','III'=>'Mejorable','IV'=>'Aceptable'] as $n=>$t): ?>
    <a href="<?= base_url("sgsst/empresa/{$eid}/matriz?nivel={$n}") ?>" class="mac-card p-4 block">
      <span class="mac-badge" style="<?= $badge($n) ?>">Nivel <?= $n ?></span>
      <div class="text-3xl font-bold mt-2"><?= (int)($resumen[$n] ?? 0) ?></div>
      <div class="text-xs mac-muted"><?= $t ?></div>
    </a><?php endforeach; ?>
  </div>
  <?php if ($filtro): ?><p class="text-sm">Filtrando nivel <?= e($filtro) ?> · <a class="underline" href="<?= base_url("sgsst/empresa/{$eid}/matriz") ?>">ver todos</a></p><?php endif; ?>

  <?php if (!$soloLectura): ?>
  <form method="POST" action="<?= base_url("sgsst/empresa/{$eid}/matriz") ?>" class="mac-card p-5 space-y-3">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <h2 class="font-bold">➕ Agregar peligro</h2>
    <div class="grid md:grid-cols-4 gap-2">
      <input name="proceso" placeholder="Proceso" class="mac-input"><input name="zona" placeholder="Zona / lugar" class="mac-input">
      <input name="actividad" placeholder="Actividad" class="mac-input"><input name="tarea" placeholder="Tarea" class="mac-input">
      <input name="descripcion" required placeholder="Peligro (descripción) *" class="mac-input md:col-span-2">
      <select name="clasificacion" class="mac-input"><?php foreach ($clasificaciones as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select>
      <label class="flex items-center gap-2 text-sm px-2"><input type="checkbox" name="rutinaria" checked> Actividad rutinaria</label>
      <input name="efectos" placeholder="Efectos posibles" class="mac-input md:col-span-2">
      <input name="responsable" placeholder="Responsable de intervención" class="mac-input"><input type="date" name="fecha_compromiso" class="mac-input">
    </div>
    <div class="grid md:grid-cols-3 gap-2">
      <input name="control_fuente" placeholder="Controles existentes — fuente" class="mac-input">
      <input name="control_medio" placeholder="Controles existentes — medio" class="mac-input">
      <input name="control_individuo" placeholder="Controles existentes — individuo / EPP" class="mac-input">
    </div>
    <div class="grid md:grid-cols-4 gap-2 items-end">
      <label class="text-xs">Nivel de deficiencia (ND)<select name="nd" class="mac-input mt-1"><?php foreach ($deficiencias as $v=>$t): ?><option value="<?= $v ?>" <?= $v===2?'selected':'' ?>><?= e($t) ?></option><?php endforeach; ?></select></label>
      <label class="text-xs">Nivel de exposición (NE)<select name="ne" class="mac-input mt-1"><?php foreach ($exposiciones as $v=>$t): ?><option value="<?= $v ?>" <?= $v===2?'selected':'' ?>><?= e($t) ?></option><?php endforeach; ?></select></label>
      <label class="text-xs">Nivel de consecuencia (NC)<select name="nc" class="mac-input mt-1"><?php foreach ($consecuencias as $v=>$t): ?><option value="<?= $v ?>" <?= $v===25?'selected':'' ?>><?= e($t) ?></option><?php endforeach; ?></select></label>
      <input name="medidas_intervencion" placeholder="Medidas de intervención propuestas" class="mac-input">
    </div>
    <p class="text-xs mac-muted">El sistema calcula automáticamente NP = ND × NE y NR = NP × NC, con su nivel y aceptabilidad según GTC 45. Jerarquía de intervención: eliminación → sustitución → controles de ingeniería → controles administrativos → EPP.</p>
    <button class="mac-btn">Valorar y agregar a la matriz</button>
  </form>
  <?php else: ?>
  <div class="mac-alert mac-alert-info">👁 Estás en el <b>portal de solo lectura</b>: puedes consultar y exportar la matriz, pero no modificarla.</div>
  <?php endif; ?>

  <div class="mac-card overflow-hidden">
    <div class="overflow-x-auto"><table class="w-full text-sm min-w-[980px]">
      <thead><tr><th class="text-left px-3 py-2">Peligro</th><th class="text-left px-3 py-2">Proceso / tarea</th><th class="px-3 py-2">ND×NE=NP</th><th class="px-3 py-2">NC</th><th class="px-3 py-2">NR</th><th class="text-left px-3 py-2">Aceptabilidad e intervención</th><th class="px-3 py-2">Estado</th><?php if(!$soloLectura): ?><th></th><?php endif; ?></tr></thead>
      <tbody class="divide-y"><?php if (empty($peligros)): ?><tr><td colspan="8" class="px-4 py-8 text-center mac-muted">Sin peligros registrados. Agrega el primero con el formulario.</td></tr><?php endif; ?>
      <?php foreach ($peligros as $p): ?>
        <tr>
          <td class="px-3 py-2"><b><?= e($p['descripcion']) ?></b><br><span class="text-xs mac-muted"><?= e((string)$p['clasificacion']) ?> · <?= e((string)$p['efectos']) ?></span></td>
          <td class="px-3 py-2 text-xs"><?= e((string)$p['proceso']) ?> / <?= e((string)$p['actividad']) ?><br><?= e((string)$p['tarea']) ?> · <?= (int)$p['rutinaria'] ? 'Rutinaria' : 'No rutinaria' ?><br><span class="mac-muted">F: <?= e((string)$p['control_fuente']) ?> · M: <?= e((string)$p['control_medio']) ?> · I: <?= e((string)$p['control_individuo']) ?></span></td>
          <td class="px-3 py-2 text-center"><?= (int)$p['nd'] ?>×<?= (int)$p['ne'] ?>=<b><?= (int)$p['np'] ?></b><br><span class="text-xs"><?= e((string)$p['np_nivel']) ?></span></td>
          <td class="px-3 py-2 text-center"><?= (int)$p['nc'] ?></td>
          <td class="px-3 py-2 text-center"><span class="mac-badge" style="<?= $badge($p['nr_nivel']) ?>"><?= (int)$p['nr'] ?> · Nivel <?= e($p['nr_nivel']) ?></span></td>
          <td class="px-3 py-2 text-xs"><b><?= e((string)$p['aceptabilidad']) ?></b><br><?= e((string)$p['medidas_intervencion']) ?><br><span class="mac-muted"><?= e((string)$p['responsable']) ?> <?= $p['fecha_compromiso'] ? '· 📅 '.e($p['fecha_compromiso']) : '' ?></span></td>
          <?php if (!$soloLectura): ?>
          <td class="px-3 py-2"><form method="POST" action="<?= base_url("sgsst/peligros/{$p['id']}/estado") ?>" class="flex gap-1"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <select name="estado" class="mac-input !w-auto !py-1 text-xs"><?php foreach (['abierto','en_control','cerrado'] as $s): ?><option <?= $p['estado']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select><button class="mac-btn-secondary !py-1">OK</button></form></td>
          <td class="px-3 py-2"><form method="POST" action="<?= base_url("sgsst/peligros/{$p['id']}/eliminar") ?>" onsubmit="return confirm('¿Eliminar este peligro de la matriz?')"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="text-red-600" title="Eliminar">🗑</button></form></td>
          <?php else: ?><td class="px-3 py-2 text-center"><?= e($p['estado']) ?></td><?php endif; ?>
        </tr><?php endforeach; ?>
      </tbody></table></div>
  </div>
</div>
