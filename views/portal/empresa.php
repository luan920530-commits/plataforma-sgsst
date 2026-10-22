<?php $eid = (int)$empresa['id']; $editable = ($permiso === 'representante'); $p = $puntaje['total']; ?>
<div class="space-y-5">
  <div class="mac-card p-5 flex flex-wrap justify-between gap-3 items-center">
    <div>
      <p class="text-xs font-semibold" style="color:var(--mac-blue)">🏢 MI EMPRESA · NIVEL DE RIESGO <?= e((string)$empresa['nivel_riesgo']) ?></p>
      <h1 class="text-2xl font-bold"><?= e($empresa['razon_social']) ?></h1>
      <p class="text-sm mac-muted">NIT <?= e($empresa['nit']) ?> · <?= (int)$empresa['num_trabajadores'] ?> trabajadores · Grupo de requisitos: <b><?= e($requisitos['grupo']) ?></b></p>
    </div>
    <span class="mac-badge" style="<?= $editable ? 'background:#dcfae5;color:#067647' : 'background:#e8f1fe;color:#0060c9' ?>"><?= $editable ? '✏️ Representante: puedes corregir y generar' : '👁 Solo lectura' ?></span>
  </div>


  <!-- TABLERO DEL CLIENTE -->
  <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    <div class="mac-card p-4"><div class="text-[11px] font-bold uppercase mac-muted">✅ Cumplimiento SG-SST</div><div class="text-3xl font-bold mt-1"><?= $p ?>%</div><div class="h-2 rounded mt-2" style="background:rgba(0,0,0,.06)"><div class="h-2 rounded" style="width:<?= $p ?>%;background:<?= $p<=60?'#ff3b30':($p<=85?'#ff9500':'#34c759') ?>"></div></div><span class="mac-badge mt-2" style="<?= $p<=60?'background:#ffe5e5;color:#b42318':($p<=85?'background:#fef0c7;color:#93370d':'background:#dcfae5;color:#067647') ?>"><?= e($semaforo) ?></span></div>
    <div class="mac-card p-4"><div class="text-[11px] font-bold uppercase mac-muted">📄 Mis documentos</div><div class="text-3xl font-bold mt-1"><?= (int)($dash['documentos'] ?? 0) ?></div><p class="text-xs mac-muted mt-1">✅ <?= (int)($dash['docAceptados'] ?? 0) ?> aceptados · 🕘 <?= (int)($dash['docPendientes'] ?? 0) ?> por revisar</p><a href="#documentos" class="text-xs font-semibold" style="color:var(--mac-blue)">Revisarlos ahora →</a></div>
    <div class="mac-card p-4"><div class="text-[11px] font-bold uppercase" style="color:#b42318">🔔 Alertas y vencimientos</div><div class="text-3xl font-bold mt-1"><?= (int)($dash['criticas'] ?? 0) ?></div><p class="text-xs mac-muted mt-1">críticas hoy · ⚠️ <?= (int)($dash['peligrosAltos'] ?? 0) ?> peligros NR I/II abiertos</p><a href="#cumplimiento" class="text-xs font-semibold" style="color:var(--mac-blue)">Ver pendientes →</a></div>
    <div class="mac-card p-4"><div class="text-[11px] font-bold uppercase mac-muted">👥 Mi operación</div><div class="text-3xl font-bold mt-1"><?= (int)($dash['trabajadores'] ?? 0) ?></div><p class="text-xs mac-muted mt-1">trabajadores · 📋 <?= (int)($dash['registrosMes'] ?? 0) ?> registros este mes · 📝 <?= (int)($dash['formulariosAbiertos'] ?? 0) ?> formularios abiertos</p><a href="#registros" class="text-xs font-semibold" style="color:var(--mac-blue)">Seguimiento diario →</a></div>
  </section>

  <nav class="flex flex-wrap gap-1.5 sticky top-2 z-20">
    <?php foreach (['datos'=>'1. 🏢 Datos de mi empresa','cumplimiento'=>'2. ✅ Mi cumplimiento','documentos'=>'3. 📄 Mis documentos','registros'=>'4. 📋 Registros y seguimiento diario'] as $a=>$t): ?>
    <a href="#<?= $a ?>" class="mac-btn-secondary !rounded-full shadow"><?= $t ?></a><?php endforeach; ?>
    <button type="button" onclick="abrirModalFormularios()" class="mac-btn !rounded-full shadow">📝 Formularios (modal)</button>
  </nav>

  <div class="mac-alert mac-alert-info">
    📋 <b>Documentación que requiere tu empresa:</b> <?= e($requisitos['descripcion']) ?> Abajo ves solo los estándares que te aplican, con su estado. <?php if ($editable): ?>Puedes corregirlos aquí mismo: todo se guarda en la base de datos de tu empresa y los documentos nuevos salen actualizados.<?php endif; ?>
  </div>

  <?php if (!empty($alertas)): ?>
  <section class="mac-card p-5"><h2 class="font-bold">🔔 Pendientes y vencimientos</h2>
    <ul class="mt-2 space-y-1.5 text-sm"><?php foreach (array_slice($alertas, 0, 8) as $al): ?>
      <li><span class="mac-badge" style="<?= $al['nivel']==='critica' ? 'background:#ffe5e5;color:#b42318' : 'background:#fef0c7;color:#93370d' ?>"><?= $al['vencida'] ? '⛔ Vencida' : '🟡 Atención' ?></span> <?= e($al['mensaje']) ?> <?php if ($al['fecha']): ?><span class="text-xs mac-muted">📅 <?= e($al['fecha']) ?></span><?php endif; ?></li>
    <?php endforeach; ?></ul></section>
  <?php endif; ?>

  <!-- DATOS DE LA EMPRESA -->
  <section id="datos" class="mac-card p-5">
    <h2 class="font-bold">1. Datos de mi empresa <?= $editable ? '' : '(solo lectura)' ?></h2>
    <p class="text-xs mac-muted mb-3">Estos datos alimentan automáticamente todos los documentos. Si algo cambió (representante, dirección, trabajadores, riesgo…), corrígelo aquí.</p>
    <form method="POST" enctype="multipart/form-data" action="<?= base_url("mi-empresa/{$eid}/datos") ?>">
      <fieldset <?= $editable ? '' : 'disabled' ?> class="contents">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <div class="grid md:grid-cols-3 gap-2 text-sm">
        <?php foreach (['razon_social'=>'Razón social','nit'=>'NIT','actividad_economica'=>'Actividad económica','ciiu'=>'CIIU','direccion'=>'Dirección','ciudad'=>'Ciudad','departamento'=>'Departamento','telefono'=>'Teléfono','email'=>'Correo','arl'=>'ARL','representante_legal'=>'Representante legal','horarios'=>'Horarios'] as $c=>$t): ?>
        <label class="text-xs"><?= $t ?><input name="<?= $c ?>" value="<?= e((string)($empresa[$c] ?? '')) ?>" class="mac-input w-full mt-1" style="padding:.55rem"></label>
        <?php endforeach; ?>
        <label class="text-xs">Nivel de riesgo
          <select name="nivel_riesgo" class="mac-input w-full mt-1" style="padding:.55rem"><?php foreach (['I','II','III','IV','V'] as $r): ?><option <?= $empresa['nivel_riesgo']===$r?'selected':'' ?>><?= $r ?></option><?php endforeach; ?></select></label>
        <label class="text-xs">N.° trabajadores<input type="number" name="num_trabajadores" value="<?= (int)$empresa['num_trabajadores'] ?>" class="mac-input w-full mt-1" style="padding:.55rem"></label>
        <label class="text-xs">Aforo máximo<input type="number" name="aforo_max" value="<?= (int)$empresa['aforo_max'] ?>" class="mac-input w-full mt-1" style="padding:.55rem"></label>
        <label class="text-xs">Área (m²)<input type="number" step="0.01" name="area_m2" value="<?= e((string)$empresa['area_m2']) ?>" class="mac-input w-full mt-1" style="padding:.55rem"></label>
        <label class="text-xs">N.° de pisos<input type="number" name="num_pisos" value="<?= (int)$empresa['num_pisos'] ?>" class="mac-input w-full mt-1" style="padding:.55rem"></label>
        <label class="text-xs md:col-span-2">Áreas de alto riesgo<input name="areas_alto_riesgo" value="<?= e((string)($empresa['areas_alto_riesgo'] ?? '')) ?>" class="mac-input w-full mt-1" style="padding:.55rem"></label>
        <label class="text-xs">C.C. Representante legal<input name="representante_cc" value="<?= e((string)($empresa['representante_cc'] ?? '')) ?>" class="mac-input w-full mt-1" style="padding:.55rem" placeholder="Cédula"></label>
        <label class="text-xs">👷 Responsable del SG-SST (diseño/implementación)<input name="resp_sgsst_nombre" value="<?= e((string)($empresa['resp_sgsst_nombre'] ?? '')) ?>" class="mac-input w-full mt-1" style="padding:.55rem" placeholder="Nombre completo (firma los documentos)"></label>
        <label class="text-xs">C.C. Responsable SG-SST<input name="resp_sgsst_cc" value="<?= e((string)($empresa['resp_sgsst_cc'] ?? '')) ?>" class="mac-input w-full mt-1" style="padding:.55rem" placeholder="Cédula"></label>
        <label class="text-xs">🖼 Logo de la empresa (encabezado de documentos)<input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" class="mac-input w-full mt-1" style="padding:.45rem"></label>
      </div>
      <p class="text-[11px] mac-muted mt-2">El Responsable del SG-SST y el Representante Legal aparecen automáticamente en el pie de firma de los documentos que lo requieren (políticas, asignaciones, actas…). Regístralos también en <b>Personal</b> con su cargo para elegirlos al personalizar cada documento.</p>
      <?php if ($editable): ?><button class="mac-btn mt-3">💾 Guardar correcciones</button><?php endif; ?>
      </fieldset>
    </form>
  </section>

  <!-- CUMPLIMIENTO SEGÚN SU NIVEL -->
  <section id="cumplimiento" class="mac-card p-5">
    <div class="flex flex-wrap justify-between gap-2 items-center">
      <div><h2 class="font-bold">2. Mi cumplimiento (estándares que me aplican)</h2>
      <p class="text-xs mac-muted">Grupo <?= e($requisitos['grupo']) ?> · <?= count($evaluaciones) ?> estándares · Actualiza el estado y la evidencia de cada uno.</p></div>
      <div class="text-right"><div class="text-3xl font-bold"><?= $p ?>%</div><span class="mac-badge" style="<?= $p<=60?'background:#ffe5e5;color:#b42318':($p<=85?'background:#fef0c7;color:#93370d':'background:#dcfae5;color:#067647') ?>"><?= e($semaforo) ?></span></div>
    </div>
    <div class="h-2 rounded mt-3" style="background:rgba(0,0,0,.06)"><div class="h-2 rounded" style="width:<?= $p ?>%;background:linear-gradient(90deg,#0a84ff,#34c759)"></div></div>
    <form method="POST" action="<?= base_url("mi-empresa/{$eid}/evaluacion") ?>" class="mt-4">
      <fieldset <?= $editable ? '' : 'disabled' ?> class="contents">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <div class="space-y-2">
      <?php foreach ($evaluaciones as $ev): ?>
        <div class="flex flex-wrap gap-2 items-center border rounded-xl px-3 py-2" style="border-color:var(--mac-stroke)">
          <span class="text-sm flex-1 min-w-[260px]"><b><?= e($ev['codigo']) ?></b> · <?= e($ev['nombre']) ?><br><span class="text-xs mac-muted">Evidencia esperada: <?= e($ev['evidencia_esperada']) ?></span></span>
          <select name="estado[<?= e($ev['codigo']) ?>]" class="mac-input" style="padding:.45rem"><?php foreach (['no_cumple'=>'No cumple','parcial'=>'Parcial','cumple'=>'Cumple','no_aplica'=>'No aplica'] as $k=>$v): ?><option value="<?= $k ?>" <?= $ev['estado']===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select>
          <input name="evidencia[<?= e($ev['codigo']) ?>]" value="<?= e((string)($ev['evidencia'] ?? '')) ?>" placeholder="Mi evidencia / dónde está" class="mac-input flex-1 min-w-[180px]" style="padding:.45rem">
          <input name="observacion[<?= e($ev['codigo']) ?>]" value="<?= e((string)($ev['observacion'] ?? '')) ?>" placeholder="Observación" class="mac-input flex-1 min-w-[140px]" style="padding:.45rem">
        </div>
      <?php endforeach; ?>
      </div>
      <?php if ($editable): ?><button class="mac-btn mt-3">💾 Guardar mi cumplimiento</button><?php endif; ?>
      </fieldset>
    </form>
    <a class="mac-btn-secondary mt-3" target="_blank" href="<?= base_url("sgsst/empresa/{$eid}/informe") ?>">🖨 Imprimir informe de cumplimiento actualizado</a>
    <a class="mac-btn-secondary mt-3" href="<?= base_url("sgsst/empresa/{$eid}/matriz") ?>">⚠️ Ver matriz de peligros (GTC 45)</a>
    <a class="mac-btn-secondary mt-3" href="<?= base_url("sgsst/empresa/{$eid}/formularios") ?>">📝 Diligenciar formularios (inspecciones, EPP, actas…)</a>
    <?php $resumenM=[]; foreach($resumenMatriz as $r) $resumenM[$r['nr_nivel']]=$r['c']; ?>
    <span class="text-xs mac-muted ml-2">Peligros: I: <?= (int)($resumenM['I']??0) ?> · II: <?= (int)($resumenM['II']??0) ?> · III: <?= (int)($resumenM['III']??0) ?> · IV: <?= (int)($resumenM['IV']??0) ?></span>
  </section>

  <!-- DOCUMENTOS -->
  <section id="documentos" class="mac-card p-5">
    <h2 class="font-bold">3. Mis documentos: ver, generar actualizados e imprimir</h2>
    <p class="text-xs mac-muted mb-3">Al registrar tu empresa se generó tu paquete documental con tus datos: revísalo abajo en «Mis documentos generados», <b>déjalo así</b> si está bien ✅, <b>regenéralo</b> si corregiste datos, o descárgalo, corrígelo en Word/Excel y súbelo con «⬆ Subir mi corrección»: tu versión queda guardada como la oficial. «Generar actualizado» crea documentos nuevos desde las plantillas maestras.</p>
    <?php foreach ($grupos as $g): ?>
    <details class="border rounded-xl mb-2" style="border-color:var(--mac-stroke)">
      <summary class="px-3 py-2 cursor-pointer text-sm font-semibold">📁 Estándar <?= e($g['numero'] ?: '—') ?> — <?= e(mb_convert_case(mb_strtolower($g['estandar']), MB_CASE_TITLE, 'UTF-8')) ?> <span class="mac-muted font-normal">(<?= count($g['archivos']) ?>)</span></summary>
      <ul class="px-3 pb-3 space-y-1.5 text-sm">
        <?php foreach ($g['archivos'] as $a): ?>
        <li class="flex flex-wrap gap-2 items-center justify-between">
          <span>📄 <?= e($a['nombre']) ?> <span class="text-xs mac-muted"><?= e($a['tipo']) ?> · <?= strtoupper($a['ext']) ?></span></span>
          <span class="flex gap-1.5">
            <a class="mac-btn-secondary !py-1" href="<?= base_url("sgsst/empresa/{$eid}/documentos/descargar?f=" . urlencode($a['relativo'])) ?>">👁 Ver original</a>
            <?php if ($a['generable'] && $editable): ?>
            <form method="POST" action="<?= base_url("mi-empresa/{$eid}/documentos/generar") ?>" class="inline">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="f" value="<?= e($a['relativo']) ?>">
              <button class="mac-btn !py-1">✨ Generar actualizado</button>
            </form><?php endif; ?>
          </span>
        </li><?php endforeach; ?>
      </ul>
    </details>
    <?php endforeach; ?>

    <?php if ($generados): ?>
    <h3 class="font-semibold mt-4 mb-2 text-sm">🗂 Mis documentos generados (listos para imprimir)</h3>
    <ul class="text-sm space-y-1.5"><?php foreach ($generados as $g): ?>
      <li class="flex flex-wrap gap-2 justify-between border-b pb-1.5" style="border-color:var(--mac-stroke)">
        <span>📄 <?= e($g['nombre_archivo']) ?> <span class="text-xs mac-muted"><?= e($g['created_at']) ?></span></span>
        <span class="flex flex-wrap gap-1.5 items-center">
          <?php if (!empty($g['carpeta'])): ?><span class="mac-badge" style="<?= ($g['estado'] ?? 'pendiente_revision') === 'aceptado' ? 'background:#dcfae5;color:#067647' : (($g['estado'] ?? '') === 'corregido' ? 'background:#e0e7ff;color:#3730a3' : 'background:#fef0c7;color:#93370d') ?>"><?= match($g['estado'] ?? 'pendiente_revision') {'aceptado' => '✅ Aceptado', 'corregido' => '✏️ Corregido', default => '🕘 Por revisar'} ?></span><?php endif; ?>
          <a class="mac-btn !py-0.5 text-xs" href="<?= base_url("sgsst/documentos/{$g['id']}/ver") ?>">👁 Ver documento</a>
          <a class="underline" style="color:var(--mac-blue)" href="<?= base_url("sgsst/documentos-generados/{$g['id']}/descargar") ?>">⬇ Descargar</a>
          <?php if ($editable && !empty($g['carpeta'])): ?>
          <form method="POST" action="<?= base_url("sgsst/documentos/{$g['id']}/aceptar") ?>" class="inline"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="mac-btn-secondary !py-0.5 text-xs">✅ Dejar así</button></form>
          <form method="POST" action="<?= base_url("sgsst/documentos/{$g['id']}/regenerar") ?>" class="inline" onsubmit="return confirm('Se creará una nueva versión con los datos actuales de tu empresa. ¿Continuar?')"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="mac-btn-secondary !py-0.5 text-xs">↻ Regenerar</button></form>
          <form method="POST" action="<?= base_url("sgsst/documentos/{$g['id']}/subir") ?>" enctype="multipart/form-data" class="inline-flex gap-1 items-center"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="file" name="archivo" required accept=".doc,.docx,.xls,.xlsx,.pdf" class="text-xs max-w-[150px]"><button class="mac-btn !py-0.5 text-xs">⬆ Subir mi corrección</button></form>
          <?php endif; ?>
        </span>
      </li><?php endforeach; ?></ul>
    <?php endif; ?>
  </section>

  <!-- REGISTROS -->
  <section id="registros" class="mac-card p-5">
    <h2 class="font-bold">4. Registros, gestión y seguimiento diario</h2>
    <p class="text-xs mac-muted mb-3">Aquí llegan también los reportes que hacen los operarios desde el formulario público. <?php if ($editable): ?>Puedes crear registros (capacitaciones, inspecciones, entregas de EPP…).<?php endif; ?></p>
    <div class="mac-alert mac-alert-info mb-3">
      🦺 <b>Enlace para operarios (sin usuario ni contraseña):</b><br>
      <code class="text-xs break-all"><?= e(base_url("reporte/{$reporteToken}")) ?></code>
      <p class="text-xs mt-1">Compártelo por WhatsApp, cartelera o código QR: cualquier trabajador puede reportar condiciones inseguras, incidentes, accidentes o sugerencias, incluso de forma anónima, y el reporte aparece aquí al instante.</p>
    </div>
    <?php if ($editable): ?>
    <form method="POST" action="<?= base_url("mi-empresa/{$eid}/registros") ?>" class="grid md:grid-cols-4 gap-2 mb-3">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <select name="tipo" class="mac-input" style="padding:.55rem"><?php foreach ($tiposRegistro as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select>
      <input name="titulo" required placeholder="Título del registro" class="mac-input" style="padding:.55rem">
      <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" class="mac-input" style="padding:.55rem">
      <input name="responsable" placeholder="Responsable" class="mac-input" style="padding:.55rem">
      <input name="descripcion" placeholder="Descripción / hallazgo" class="mac-input md:col-span-3" style="padding:.55rem">
      <button class="mac-btn">➕ Crear registro</button>
    </form><?php endif; ?>
    <ul class="text-sm space-y-1.5"><?php if (empty($registros)): ?><li class="mac-muted">Sin registros todavía.</li><?php endif; ?>
    <?php foreach ($registros as $r): ?>
      <li class="flex flex-wrap gap-2 items-center border-b pb-1.5" style="border-color:var(--mac-stroke)">
        <span class="mac-badge" style="background:#f2f4f7;color:#344054"><?= e($tiposRegistro[$r['tipo']] ?? $r['tipo']) ?></span>
        <span class="flex-1"><b><?= e($r['titulo']) ?></b> <span class="text-xs mac-muted"><?= e((string)$r['descripcion']) ?></span></span>
        <span class="text-xs mac-muted">📅 <?= e($r['fecha']) ?> · <?= e($r['estado']) ?></span>
      </li><?php endforeach; ?></ul>
  </section>

  <?php if ($periodos): ?>
  <section class="mac-card p-5">
    <h2 class="font-bold">5. Mis indicadores</h2>
    <table class="w-full text-sm mt-2"><thead><tr><th class="text-left py-1">Periodo</th><th>Accidentes</th><th>Frecuencia</th><th>Severidad</th><th>Ausentismo %</th></tr></thead>
    <tbody><?php foreach ($periodos as $per): $i=$per['indicadores']; ?>
      <tr class="border-t" style="border-color:var(--mac-stroke)"><td class="py-1.5"><?= e($per['periodo']) ?></td><td class="text-center"><?= $i['accidentes'] ?></td><td class="text-center"><?= $i['frecuencia'] ?></td><td class="text-center"><?= $i['severidad'] ?></td><td class="text-center"><?= $i['ausentismo_pct'] ?>%</td></tr>
    <?php endforeach; ?></tbody></table>
  </section>
  <?php endif; ?>
</div>

<!-- MODAL FORMULARIOS -->
<div id="modalFormularios" class="fixed inset-0 z-50 hidden items-center justify-center p-3" style="background:rgba(20,22,28,.55);backdrop-filter:blur(3px)">
  <div class="mac-card w-full max-w-4xl overflow-hidden" style="border-radius:18px">
    <div class="flex items-center justify-between px-4 py-3 border-b" style="border-color:var(--mac-stroke)">
      <b class="text-sm">📝 Formularios de mi empresa — diligencia sin salir de esta pantalla</b>
      <button type="button" onclick="cerrarModalFormularios()" class="mac-btn-secondary !py-1">✕ Cerrar</button>
    </div>
    <iframe id="iframeFormularios" title="Formularios" style="width:100%;height:72vh;border:0" loading="lazy"></iframe>
  </div>
</div>
<script>
function abrirModalFormularios(){ var m=document.getElementById('modalFormularios'); document.getElementById('iframeFormularios').src='<?= base_url("sgsst/empresa/{$eid}/formularios") ?>?embed=1'; m.classList.remove('hidden'); m.classList.add('flex'); }
function cerrarModalFormularios(){ var m=document.getElementById('modalFormularios'); m.classList.add('hidden'); m.classList.remove('flex'); location.reload(); }
document.getElementById('modalFormularios').addEventListener('click', function(e){ if(e.target===this) cerrarModalFormularios(); });
</script>
