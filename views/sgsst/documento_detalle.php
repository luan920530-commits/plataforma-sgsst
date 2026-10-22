<?php
$eid = (int)$empresa['id'];
$logo = !empty($empresa['logo_url']) ? base_url(ltrim((string)$empresa['logo_url'], '/')) : null;
$respN = $doc['firma_resp_nombre'] ?? ($empresa['resp_sgsst_nombre'] ?? '');
$respC = $doc['firma_resp_cc'] ?? ($empresa['resp_sgsst_cc'] ?? '');
$repN  = $doc['firma_rep_nombre'] ?? ($empresa['representante_legal'] ?? '');
$repC  = $doc['firma_rep_cc'] ?? ($empresa['representante_cc'] ?? '');
$conFirma = !empty($doc['requiere_firma']) ? true : ($doc['requiere_firma'] === null ? $sugiereFirma : false);
$conFirma = ($doc['requiere_firma'] ?? null) === null ? $sugiereFirma : !empty($doc['requiere_firma']);
?>
<style>
@media print {
  nav, aside, .no-print { display:none !important; }
  main { padding:0 !important; max-width:none !important; }
  .hoja { box-shadow:none !important; border:none !important; margin:0 !important; }
  body { background:#fff !important; }
}
.hoja { background:#fff; color:#111; max-width:820px; margin:0 auto; padding:42px 46px; border:1px solid #d8dce3; border-radius:6px; box-shadow:0 8px 30px rgba(0,0,0,.08); font-family: Georgia, 'Times New Roman', serif; }
.hoja table.enc { width:100%; border-collapse:collapse; margin-bottom:22px; }
.hoja table.enc td { border:1.4px solid #111; padding:8px 10px; vertical-align:middle; }
.hoja .logo-box { width:150px; text-align:center; font-family:Arial,sans-serif; font-size:11px; color:#555; }
.hoja .logo-box img { max-width:140px; max-height:70px; }
.hoja h1.doc { font-family:Arial,sans-serif; font-size:17px; text-align:center; margin:0; line-height:1.35; }
.hoja table.meta { width:210px; border-collapse:collapse; font-family:Arial,sans-serif; font-size:11px; }
.hoja table.meta td { border:none; padding:2px 4px; }
.hoja p.cuerpo { font-size:13.5px; line-height:1.65; text-align:justify; margin:0 0 10px; }
.firmas { display:grid; grid-template-columns:1fr 1fr; gap:48px; margin-top:56px; font-family:Arial,sans-serif; font-size:12px; }
.firmas .linea { border-top:1.4px solid #111; padding-top:6px; text-align:center; }
</style>

<div class="no-print flex flex-wrap gap-2 mb-4 max-w-[820px] mx-auto">
  <button onclick="window.print()" class="mac-btn">🖨 Imprimir / guardar PDF</button>
  <a class="mac-btn-secondary" href="<?= base_url("sgsst/documentos-generados/{$doc['id']}/descargar") ?>">⬇ Descargar archivo (Word/Excel)</a>
  <a class="mac-btn-secondary" href="<?= base_url("sgsst/empresa/{$eid}/documentos") ?>">← Volver a documentos</a>
  <span class="mac-badge self-center" style="background:#e8f1fe;color:#0060c9">v<?= (int)($doc['version'] ?? 1) ?> · <?= e(match($doc['estado'] ?? 'pendiente_revision') {'aceptado'=>'✅ Aceptado','corregido'=>'✏️ Corregido',default=>'🕘 Por revisar'}) ?></span>
</div>

<?php if ($puedeEditar): ?>
<details class="no-print mac-card p-4 mb-4 max-w-[820px] mx-auto">
  <summary class="font-bold cursor-pointer text-sm">✏️ Editar encabezado, firmas y notas (consultor HSE / representante)</summary>
  <form method="POST" action="<?= base_url("sgsst/documentos/{$doc['id']}/detalle") ?>" class="grid md:grid-cols-3 gap-2 mt-3 text-sm">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label class="text-xs">Código del documento<input name="codigo_documento" value="<?= e((string)($doc['codigo_documento'] ?? '')) ?>" placeholder="Ej: SST-DOC-01" class="mac-input w-full mt-1" style="padding:.5rem"></label>
    <label class="text-xs">Fecha de elaboración<input type="date" name="fecha_elaboracion" value="<?= e((string)($doc['fecha_elaboracion'] ?? date('Y-m-d'))) ?>" class="mac-input w-full mt-1" style="padding:.5rem"></label>
    <label class="text-xs flex items-end gap-2 pb-2"><input type="checkbox" name="requiere_firma" value="1" <?= $conFirma ? 'checked' : '' ?> class="w-4 h-4"> Requiere firmas al pie</label>
    <label class="text-xs">Responsable del diseño/implementación SG-SST (elige del personal o escribe)
      <input name="firma_resp_nombre" list="personalN" value="<?= e((string)$respN) ?>" class="mac-input w-full mt-1" style="padding:.5rem" placeholder="Nombre completo"></label>
    <label class="text-xs">C.C. Responsable<input name="firma_resp_cc" value="<?= e((string)$respC) ?>" class="mac-input w-full mt-1" style="padding:.5rem" placeholder="Cédula"></label>
    <span></span>
    <label class="text-xs">Representante Legal<input name="firma_rep_nombre" list="personalN" value="<?= e((string)$repN) ?>" class="mac-input w-full mt-1" style="padding:.5rem"></label>
    <label class="text-xs">C.C. Representante Legal<input name="firma_rep_cc" value="<?= e((string)$repC) ?>" class="mac-input w-full mt-1" style="padding:.5rem"></label>
    <label class="text-xs">Notas / contenido adicional del HSE<textarea name="notas_hse" rows="2" class="mac-input w-full mt-1" style="padding:.5rem" placeholder="Texto que se anexa al final del documento…"><?= e((string)($doc['notas_hse'] ?? '')) ?></textarea></label>
    <datalist id="personalN"><?php foreach ($personal as $per): ?><option value="<?= e($per['nombre_completo']) ?>"><?= e((string)$per['cedula']) ?></option><?php endforeach; ?></datalist>
    <button class="mac-btn md:col-span-3">💾 Guardar encabezado y firmas</button>
    <p class="text-[11px] mac-muted md:col-span-3">Consejo: registra primero a las personas en <b>Personal</b> con su cédula y cargo; aquí las eliges de la lista. La versión del encabezado sube sola cuando aceptas/regeneras o subes correcciones.</p>
  </form>
</details>
<?php endif; ?>

<article class="hoja">
  <table class="enc"><tr>
    <td class="logo-box"><?php if ($logo): ?><img src="<?= e($logo) ?>" alt="Logo empresa"><br><?php endif; ?><?= $logo ? '' : 'LOGO EMPRESA' ?><div style="margin-top:4px;font-weight:700;color:#111"><?= e($empresa['razon_social']) ?></div><div>NIT <?= e($empresa['nit']) ?></div></td>
    <td><h1 class="doc"><?= e($tituloDoc) ?></h1></td>
    <td style="width:210px"><table class="meta">
      <tr><td><b>CÓDIGO:</b></td><td><?= e((string)($doc['codigo_documento'] ?: '—')) ?></td></tr>
      <tr><td><b>VERSIÓN:</b></td><td><?= str_pad((string)(int)($doc['version'] ?? 1), 2, '0', STR_PAD_LEFT) ?></td></tr>
      <tr><td><b>FECHA ELABORACIÓN:</b></td><td><?= e(!empty($doc['fecha_elaboracion']) ? date('d/m/Y', strtotime((string)$doc['fecha_elaboracion'])) : date('d/m/Y')) ?></td></tr>
    </table></td>
  </tr></table>

  <?php if ($parrafos): foreach ($parrafos as $p): ?>
    <?php if (preg_match('/^_{5,}$/', trim($p))) continue; // las líneas sueltas las reemplaza el bloque de firmas ?>
    <p class="cuerpo"><?= e($p) ?></p>
  <?php endforeach; else: ?>
    <p class="cuerpo">Este documento es un formato de Excel o un archivo de consulta: no tiene texto extraíble para mostrar aquí. Usa los <b>Formularios web</b> de la empresa para diligenciarlo en línea (queda guardado en la base de datos con historial), o descárgalo para imprimirlo.</p>
  <?php endif; ?>

  <?php if (!empty($doc['notas_hse'])): ?>
    <p class="cuerpo"><b>Notas del responsable SG-SST:</b> <?= e((string)$doc['notas_hse']) ?></p>
  <?php endif; ?>

  <?php if ($conFirma): ?>
  <div class="firmas">
    <div class="linea">________________________________________<br><b><?= e((string)($respN ?: 'XXXXXXXXXXXXXXXXXXX')) ?></b><br>C.C. <?= e((string)($respC ?: 'XXXXXXXXXXXXXXXX')) ?><br>Responsable del diseño del SG-SST</div>
    <div class="linea">________________________________________<br><b><?= e((string)($repN ?: 'XXXXXXXXXXXXXXXXXXX')) ?></b><br>C.C. <?= e((string)($repC ?: 'XXXXXXXXXXXXXXXX')) ?><br>Representante Legal</div>
  </div>
  <?php endif; ?>
</article>
