<?php $eid = (int)$empresa['id']; $p = $puntaje['total']; ?>
<div class="space-y-6">
  <div class="flex flex-wrap justify-between gap-3">
    <div><h1 class="text-2xl font-bold text-slate-800">SG-SST · <?= e($empresa['razon_social']) ?></h1>
    <p class="text-sm text-slate-500">NIT <?= e($empresa['nit']) ?> · Riesgo <?= e((string)$empresa['nivel_riesgo']) ?> · ARL <?= e((string)$empresa['arl']) ?> · <?= (int)$empresa['num_trabajadores'] ?> trabajadores</p></div>
    <div class="flex gap-2 h-fit">
      <a target="_blank" href="<?= base_url("sgsst/empresa/{$eid}/informe") ?>" class="px-3 py-2 border rounded-lg bg-white">Informe imprimible</a>
      <a href="<?= base_url("empresas/{$eid}") ?>" class="px-3 py-2 bg-brand-600 text-white rounded-lg">Módulo PPPRAE / análisis</a>
    </div>
  </div>

  <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-4 text-sm">
    <b>Uso correcto:</b> esta evaluación es la base de seguimiento del SG-SST. La aplicabilidad de cada estándar depende del N.° de trabajadores y la clase de riesgo (Res. 0312/2019); marca <b>No aplica</b> solo con justificación. Las plantillas deben adaptarse, ser validadas por el responsable del SG-SST y firmarse. Si el puntaje es ≤60% la norma exige reportar a la ARL y elaborar plan de mejoramiento; si es ≤85%, el plan debe estar disponible para el Ministerio de Trabajo.
  </div>

  <div class="grid md:grid-cols-5 gap-3">
    <div class="bg-white border rounded-xl p-4 md:col-span-2"><div class="text-xs text-slate-500">CUMPLIMIENTO ESTÁNDARES MÍNIMOS</div>
      <div class="text-3xl font-bold mt-1"><?= $p ?>% <span class="text-sm <?= $p<=60?'text-red-600':($p<=85?'text-yellow-600':'text-green-600') ?>"><?= e($semaforo) ?></span></div>
      <div class="h-2 bg-slate-100 rounded mt-2"><div class="h-2 rounded bg-brand-600" style="width:<?= $p ?>%"></div></div></div>
    <?php foreach (['PLANEAR','HACER','VERIFICAR','ACTUAR'] as $c): ?>
    <div class="bg-white border rounded-xl p-4"><div class="text-xs text-slate-500"><?= $c ?></div><div class="text-2xl font-bold"><?= $puntaje['por_ciclo'][$c] ?? 0 ?>%</div></div>
    <?php endforeach; ?>
  </div>

  <?php if (!empty($soloLectura)): ?>
  <div class="mac-alert mac-alert-info">👁 <b>Portal de solo lectura:</b> estás consultando el SG-SST de esta empresa. Puedes ver avances, matriz, indicadores e informes, pero no modificar información.</div>
  <?php endif; ?>

  <?php if (!empty($alertas)): ?>
  <section class="mac-card p-5">
    <h2 class="font-bold">🔔 Alertas y vencimientos <span class="text-sm font-normal mac-muted">(<?= count($alertas) ?> activas · vencidas y próximos 30 días)</span></h2>
    <ul class="mt-3 space-y-2 text-sm">
      <?php foreach (array_slice($alertas, 0, 12) as $al): ?>
      <li class="flex flex-wrap gap-2 items-center">
        <span class="mac-badge" style="<?= $al['nivel']==='critica' ? 'background:#ffe5e5;color:#b42318' : 'background:#fef0c7;color:#93370d' ?>"><?= $al['vencida'] ? '⛔ Vencida' : ($al['nivel']==='critica' ? '🔴 Atención' : '🟡 Próxima') ?></span>
        <a href="#<?= e($al['ancla']) ?>" class="hover:underline"><?= e($al['mensaje']) ?></a>
        <?php if ($al['fecha']): ?><span class="text-xs mac-muted">📅 <?= e($al['fecha']) ?></span><?php endif; ?>
      </li><?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <nav class="flex flex-wrap gap-2 text-sm">
    <?php foreach (['evaluacion'=>'Evaluación inicial','plan'=>'Plan anual','registros'=>'Registros y seguimiento','matriz'=>'Matriz IPVR','indicadores'=>'Indicadores','plantillas'=>'Plantillas y documentos','accesos'=>'Acceso cliente','auditoria'=>'Auditoría'] as $a=>$t): ?>
    <a href="#<?= $a ?>" class="px-3 py-1.5 bg-white border rounded-full"><?= $t ?></a><?php endforeach; ?>
  </nav>

  <!-- EVALUACIÓN -->
  <section id="evaluacion" class="bg-white border rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b"><h2 class="font-bold">1. Evaluación inicial — Estándares mínimos (Res. 0312/2019)</h2>
    <p class="text-xs text-slate-500">Califica cada ítem, registra la evidencia que lo soporta y, si no cumple, el responsable y la fecha compromiso. Esto alimenta el puntaje y el informe de auditoría.</p></div>
    <form method="POST" action="<?= base_url("sgsst/empresa/{$eid}/evaluacion") ?>">
      <fieldset <?= !empty($soloLectura) ? 'disabled' : '' ?> class="contents">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <div class="overflow-x-auto"><table class="w-full text-sm min-w-[900px]">
        <thead class="bg-slate-50"><tr><th class="text-left px-3 py-2">Estándar</th><th class="px-3 py-2">Estado</th><th class="text-left px-3 py-2">Evidencia / soporte</th><th class="text-left px-3 py-2">Observación</th><th class="text-left px-3 py-2">Responsable</th><th class="px-3 py-2">Fecha compromiso</th></tr></thead>
        <tbody class="divide-y"><?php foreach ($evaluaciones as $ev): $c=$ev['codigo']; ?>
          <tr><td class="px-3 py-2"><b><?= e($c) ?></b> · <?= e($ev['ciclo']) ?><br><?= e($ev['nombre']) ?><br><span class="text-xs text-slate-400"><?= e($ev['norma']) ?> — Evidencia esperada: <?= e($ev['evidencia_esperada']) ?></span></td>
          <td class="px-3 py-2"><select name="estado[<?= e($c) ?>]" class="border rounded px-2 py-1"><?php foreach (['no_cumple'=>'No cumple','parcial'=>'Parcial','cumple'=>'Cumple','no_aplica'=>'No aplica'] as $k=>$v): ?><option value="<?= $k ?>" <?= $ev['estado']===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></td>
          <td class="px-3 py-2"><input name="evidencia[<?= e($c) ?>]" value="<?= e((string)($ev['evidencia'] ?? '')) ?>" class="border rounded px-2 py-1 w-full" placeholder="Documento, acta, registro..."></td>
          <td class="px-3 py-2"><input name="observacion[<?= e($c) ?>]" value="<?= e((string)($ev['observacion'] ?? '')) ?>" class="border rounded px-2 py-1 w-full" placeholder="Justificación / brecha"></td>
          <td class="px-3 py-2"><input name="responsable[<?= e($c) ?>]" value="<?= e((string)($ev['responsable'] ?? '')) ?>" class="border rounded px-2 py-1 w-full"></td>
          <td class="px-3 py-2"><input type="date" name="fecha_compromiso[<?= e($c) ?>]" value="<?= e((string)($ev['fecha_compromiso'] ?? '')) ?>" class="border rounded px-2 py-1"></td></tr>
        <?php endforeach; ?></tbody></table></div>
      <?php if (empty($soloLectura)): ?><div class="p-4 border-t text-right"><button class="bg-brand-600 text-white px-5 py-2 rounded-lg">Guardar evaluación</button></div><?php endif; ?>
      </fieldset>
    </form>
  </section>

  <!-- PLAN ANUAL -->
  <section id="plan" class="bg-white border rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b"><h2 class="font-bold">2. Plan de trabajo anual</h2><p class="text-xs text-slate-500">Se cargó un plan base al crear la empresa. Agrega actividades, actualiza estados y conserva la evidencia de ejecución.</p></div>
    <?php if (empty($soloLectura)): ?><form method="POST" action="<?= base_url("sgsst/empresa/{$eid}/actividades") ?>" class="p-4 grid md:grid-cols-6 gap-2 border-b bg-slate-50">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <input name="titulo" required placeholder="Nueva actividad" class="border rounded px-2 py-2 md:col-span-2">
      <select name="ciclo" class="border rounded px-2 py-2"><?php foreach (['PLANEAR','HACER','VERIFICAR','ACTUAR'] as $c): ?><option><?= $c ?></option><?php endforeach; ?></select>
      <input name="responsable" placeholder="Responsable" class="border rounded px-2 py-2">
      <input type="date" name="fecha_vencimiento" class="border rounded px-2 py-2">
      <button class="bg-brand-600 text-white rounded px-3 py-2">Agregar</button>
    </form><?php endif; ?>
    <table class="w-full text-sm"><thead class="bg-slate-50"><tr><th class="text-left px-4 py-2">Actividad</th><th class="text-left px-4 py-2">Ciclo</th><th class="text-left px-4 py-2">Responsable</th><th class="text-left px-4 py-2">Vence</th><th class="text-left px-4 py-2">Estado</th></tr></thead>
    <tbody class="divide-y"><?php foreach ($actividades as $a): $vencida = $a['fecha_vencimiento'] && $a['fecha_vencimiento'] < date('Y-m-d') && !in_array($a['estado'],['completada','cancelada'],true); ?>
      <tr class="<?= $vencida?'bg-red-50':'' ?>"><td class="px-4 py-2"><?= e($a['titulo']) ?><?= $vencida?' <b class="text-red-600 text-xs">VENCIDA</b>':'' ?></td><td class="px-4 py-2"><?= e($a['ciclo']) ?></td><td class="px-4 py-2"><?= e((string)$a['responsable']) ?></td><td class="px-4 py-2"><?= e((string)$a['fecha_vencimiento']) ?></td>
      <td class="px-4 py-2"><form method="POST" action="<?= base_url("sgsst/actividades/{$a['id']}/estado") ?>" class="flex gap-1"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <select name="estado" <?= !empty($soloLectura)?'disabled':'' ?> class="border rounded px-1 py-1"><?php foreach (['pendiente','en_proceso','completada','vencida','cancelada'] as $s): ?><option <?= $a['estado']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select><button class="bg-slate-700 text-white rounded px-2">OK</button></form></td></tr>
    <?php endforeach; ?></tbody></table>
  </section>

  <!-- REGISTROS -->
  <section id="registros" class="bg-white border rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b"><h2 class="font-bold">3. Registros, gestión y seguimiento diario</h2><p class="text-xs text-slate-500">Capacitaciones, inspecciones, incidentes/accidentes, EPP, exámenes, mantenimientos, simulacros, COPASST, auditorías y acciones de mejora. Todo queda con responsable, vencimiento y trazabilidad.</p></div>
    <?php if (empty($soloLectura)): ?><form method="POST" action="<?= base_url("sgsst/empresa/{$eid}/registros") ?>" class="p-4 grid md:grid-cols-4 gap-2 border-b bg-slate-50">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <select name="tipo" class="border rounded px-2 py-2"><?php foreach ($tiposRegistro as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select>
      <input name="titulo" required placeholder="Título del registro" class="border rounded px-2 py-2">
      <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" class="border rounded px-2 py-2">
      <input type="date" name="fecha_vencimiento" title="Fecha de seguimiento/vencimiento" class="border rounded px-2 py-2">
      <input name="responsable" placeholder="Responsable" class="border rounded px-2 py-2">
      <select name="severidad" class="border rounded px-2 py-2"><?php foreach (['baja','media','alta','critica'] as $s): ?><option><?= $s ?></option><?php endforeach; ?></select>
      <input name="evidencia" placeholder="Evidencia (documento, acta, enlace o ubicación)" class="border rounded px-2 py-2">
      <input name="descripcion" placeholder="Descripción / hallazgo / acción" class="border rounded px-2 py-2">
      <button class="bg-brand-600 text-white rounded px-3 py-2 md:col-span-4">Crear registro</button>
    </form><?php endif; ?>
    <table class="w-full text-sm"><thead class="bg-slate-50"><tr><th class="text-left px-4 py-2">Fecha</th><th class="text-left px-4 py-2">Tipo</th><th class="text-left px-4 py-2">Registro</th><th class="text-left px-4 py-2">Responsable</th><th class="text-left px-4 py-2">Estado</th></tr></thead>
    <tbody class="divide-y"><?php if(empty($registros)): ?><tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Sin registros todavía.</td></tr><?php endif; ?>
    <?php foreach ($registros as $r): ?><tr><td class="px-4 py-2"><?= e($r['fecha']) ?></td><td class="px-4 py-2"><?= e($tiposRegistro[$r['tipo']] ?? $r['tipo']) ?></td>
      <td class="px-4 py-2"><b><?= e($r['titulo']) ?></b><br><span class="text-xs text-slate-500"><?= e((string)$r['descripcion']) ?> <?= $r['evidencia']?'· Evidencia: '.e((string)$r['evidencia']):'' ?></span></td>
      <td class="px-4 py-2"><?= e((string)$r['responsable']) ?></td>
      <td class="px-4 py-2"><form method="POST" action="<?= base_url("sgsst/registros/{$r['id']}/estado") ?>" class="flex gap-1"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <select name="estado" <?= !empty($soloLectura)?'disabled':'' ?> class="border rounded px-1 py-1"><?php foreach (['abierto','en_gestion','cerrado','vencido'] as $s): ?><option <?= $r['estado']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select><button class="bg-slate-700 text-white rounded px-2">OK</button></form></td></tr><?php endforeach; ?>
    </tbody></table>
  </section>


  <!-- MATRIZ IPVR -->
  <section id="matriz" class="mac-card p-5">
    <div class="flex flex-wrap justify-between gap-2 items-center">
      <div><h2 class="font-bold">4. Matriz de peligros IPVR (GTC 45)</h2>
      <p class="text-xs mac-muted">Valoración automática NP = ND × NE, NR = NP × NC. Peligros nivel I/II abiertos: <b><?= (int)($peligrosCriticos ?? 0) ?></b></p></div>
      <a class="mac-btn" href="<?= base_url("sgsst/empresa/{$eid}/matriz") ?>">Abrir matriz completa</a>
    </div>
    <div class="flex flex-wrap gap-2 mt-3">
      <?php $resumenM = []; foreach (($resumenMatriz ?? []) as $r) $resumenM[$r['nr_nivel']] = $r['c']; ?>
      <?php foreach (['I','II','III','IV'] as $n): ?><span class="mac-badge" style="background:#f2f4f7;color:#344054">Nivel <?= $n ?>: <?= (int)($resumenM[$n] ?? 0) ?></span><?php endforeach; ?>
    </div>
  </section>

  <!-- INDICADORES -->
  <section id="indicadores" class="bg-white border rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b"><h2 class="font-bold">5. Indicadores automáticos (Res. 0312/2019)</h2>
    <p class="text-xs text-slate-500">Registra por mes las horas-hombre trabajadas y los días (nómina). Los accidentes y enfermedades se cuentan automáticamente desde los Registros. Frecuencia y severidad = eventos/HHT × 240.000.</p></div>
    <?php if (empty($soloLectura)): ?>
    <form method="POST" action="<?= base_url("sgsst/empresa/{$eid}/periodos") ?>" class="p-4 grid md:grid-cols-5 gap-2 border-b bg-slate-50 text-sm">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <label class="text-xs">Periodo (AAAA-MM)<input type="month" name="periodo" required value="<?= date('Y-m') ?>" class="border rounded px-2 py-2 w-full mt-1"></label>
      <label class="text-xs">Horas-hombre trabajadas<input type="number" step="0.01" min="0" name="horas_trabajadas" required class="border rounded px-2 py-2 w-full mt-1"></label>
      <label class="text-xs">Trabajadores promedio<input type="number" min="0" name="trabajadores_promedio" class="border rounded px-2 py-2 w-full mt-1"></label>
      <label class="text-xs">Días perdidos (incapacidad)<input type="number" min="0" name="dias_perdidos" class="border rounded px-2 py-2 w-full mt-1"></label>
      <label class="text-xs">Días cargados<input type="number" min="0" name="dias_cargados" class="border rounded px-2 py-2 w-full mt-1"></label>
      <label class="text-xs">Accidentes mortales<input type="number" min="0" name="accidentes_mortales" class="border rounded px-2 py-2 w-full mt-1"></label>
      <label class="text-xs">Días ausencia médica<input type="number" min="0" name="dias_ausencia_medica" class="border rounded px-2 py-2 w-full mt-1"></label>
      <label class="text-xs">Días programados<input type="number" min="0" name="dias_programados" class="border rounded px-2 py-2 w-full mt-1"></label>
      <button class="bg-brand-600 text-white rounded px-3 py-2 self-end">Guardar periodo</button>
    </form><?php endif; ?>
    <table class="w-full text-sm"><thead class="bg-slate-50"><tr><th class="text-left px-4 py-2">Periodo</th><th class="px-4 py-2">Accidentes</th><th class="px-4 py-2">Frecuencia</th><th class="px-4 py-2">Severidad</th><th class="px-4 py-2">ILI</th><th class="px-4 py-2">Mortales %</th><th class="px-4 py-2">Ausentismo %</th></tr></thead>
    <tbody class="divide-y"><?php if (empty($periodos)): ?><tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">Sin periodos. Registra el primer mes para ver los indicadores.</td></tr><?php endif; ?>
    <?php foreach ($periodos as $per): $i = $per['indicadores']; ?><tr><td class="px-4 py-2"><b><?= e($per['periodo']) ?></b></td>
      <td class="px-4 py-2 text-center"><?= $i['accidentes'] ?> <span class="text-xs text-slate-400">(enf: <?= $i['enfermedades'] ?>)</span></td>
      <td class="px-4 py-2 text-center"><?= $i['frecuencia'] ?></td><td class="px-4 py-2 text-center"><?= $i['severidad'] ?></td>
      <td class="px-4 py-2 text-center"><?= $i['ili'] ?></td><td class="px-4 py-2 text-center"><?= $i['mortalidad_pct'] ?>%</td>
      <td class="px-4 py-2 text-center"><?= $i['ausentismo_pct'] ?>%</td></tr><?php endforeach; ?>
    </tbody></table>
  </section>

  <!-- PLANTILLAS -->
  <section id="plantillas" class="bg-white border rounded-xl p-5">
    <h2 class="font-bold">6. Plantillas base y documentos para presentar</h2>
    <p class="text-xs text-slate-500 mb-4">Se generan con los datos de la empresa en formato editable (.doc), quedan registrados con hash SHA-256 y en la auditoría. Deben adaptarse, validarse y firmarse antes de presentarse como documentos oficiales.</p>
    <a class="mac-btn mb-4" href="<?= base_url("sgsst/empresa/{$eid}/documentos") ?>">📚 Abrir catálogo documental maestro (plantillas reales, 60 estándares)</a>
    <div class="grid md:grid-cols-3 gap-3"><?php foreach ($plantillas as $cod=>$pl): ?>
      <div class="border rounded-xl p-4"><h3 class="font-semibold text-sm"><?= e($pl['titulo']) ?></h3><p class="text-xs text-slate-500 mt-1"><?= e($pl['norma']) ?><br>Firma: <?= e($pl['firma']) ?></p>
      <a href="<?= base_url("sgsst/empresa/{$eid}/plantilla/{$cod}") ?>" class="inline-block mt-3 bg-brand-600 text-white text-sm rounded-lg px-3 py-1.5">Generar documento</a></div>
    <?php endforeach; ?></div>
    <?php if ($documentosSgsst): ?><h3 class="font-semibold mt-5 mb-2 text-sm">Documentos SG-SST generados</h3><ul class="text-sm space-y-1"><?php foreach ($documentosSgsst as $d): ?><li>📄 <?= e($d['nombre_archivo']) ?> <span class="text-xs text-slate-400"><?= e($d['created_at']) ?> · SHA-256 <?= e(substr((string)$d['hash_sha256'],0,12)) ?>…</span></li><?php endforeach; ?></ul><?php endif; ?>
  </section>


  <?php if (empty($soloLectura)): ?>
  <section id="accesos" class="bg-white border rounded-xl p-5">
    <h2 class="font-bold">Portal del cliente (solo lectura)</h2>
    <p class="text-xs text-slate-500 mb-3">Comparte este SG-SST con un usuario cliente (debe existirlo creado un superadmin en «Usuarios y roles»). Podrá consultar cumplimiento, matriz, indicadores e informes; no podrá modificar, descargar documentos generados ni ver otras empresas.</p>
    <form method="POST" action="<?= base_url("sgsst/empresa/{$eid}/accesos") ?>" class="flex flex-wrap gap-2">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <input type="email" name="email" required placeholder="correo@empresa-cliente.com" class="border rounded-lg px-3 py-2 flex-1 min-w-[240px]">
      <button class="bg-brand-600 text-white rounded-lg px-4 py-2">Otorgar acceso de lectura</button>
    </form>
    <?php if (!empty($accesos)): ?><ul class="mt-3 space-y-2 text-sm"><?php foreach ($accesos as $ac): ?>
      <li class="flex justify-between items-center border rounded-lg px-3 py-2"><span>👤 <b><?= e($ac['nombre']) ?></b> · <?= e($ac['email']) ?> <span class="text-xs text-slate-400">(<?= e($ac['rol']) ?> · solo lectura)</span></span>
      <form method="POST" action="<?= base_url("sgsst/accesos/{$ac['id']}/revocar") ?>" onsubmit="return confirm('¿Revocar el acceso de este usuario?')"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="text-red-600 text-sm">Revocar</button></form></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </section>
  <?php endif; ?>

  <!-- AUDITORÍA -->
  <section id="auditoria" class="bg-white border rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b"><h2 class="font-bold">7. Auditoría y trazabilidad</h2><p class="text-xs text-slate-500">Registro automático de acciones relevantes sobre esta empresa (quién, qué y cuándo), útil para auditorías internas, ARL y Ministerio de Trabajo.</p></div>
    <table class="w-full text-sm"><thead class="bg-slate-50"><tr><th class="text-left px-4 py-2">Fecha</th><th class="text-left px-4 py-2">Usuario</th><th class="text-left px-4 py-2">Acción</th><th class="text-left px-4 py-2">Detalle</th></tr></thead>
    <tbody class="divide-y"><?php if(empty($auditoria)): ?><tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Sin movimientos registrados.</td></tr><?php endif; ?>
    <?php foreach ($auditoria as $a): ?><tr><td class="px-4 py-2"><?= e($a['created_at']) ?></td><td class="px-4 py-2"><?= e((string)($a['usuario'] ?? '—')) ?></td><td class="px-4 py-2"><?= e($a['accion']) ?></td><td class="px-4 py-2"><?= e((string)$a['detalle']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
  </section>
</div>
