<?php $eid = (int)$empresa['id']; ?>
<div class="space-y-5">
  <div class="mac-card p-5 flex flex-wrap justify-between gap-3 items-center">
    <div>
      <p class="text-xs font-semibold" style="color:var(--mac-blue)">FORMULARIO #<?= (int)$registro['id'] ?> · ESTÁNDAR <?= e($formato['estandar']) ?></p>
      <h1 class="text-2xl font-bold">📝 <?= e($formato['titulo']) ?></h1>
      <p class="text-sm mac-muted"><?= e($empresa['razon_social']) ?> · NIT <?= e($empresa['nit']) ?> · Fecha: <?= e($registro['fecha']) ?> · Estado: <?= e($registro['estado']) ?></p>
    </div>
    <div class="flex gap-2 no-print">
      <button class="mac-btn" onclick="window.print()">🖨 Imprimir</button>
      <a class="mac-btn-secondary" href="<?= base_url("sgsst/empresa/{$eid}/formularios/{$registro['formato']}") ?>">← Volver</a>
    </div>
  </div>

  <section class="mac-card p-5">
    <table class="w-full text-sm">
      <tbody>
      <?php foreach ($formato['campos'] as [$clave, $etiqueta, $tipo, $opciones, $requerido]): $v = $datos[$clave] ?? ''; ?>
        <tr class="border-b" style="border-color:var(--mac-stroke)">
          <td class="py-2.5 pr-4 font-semibold align-top w-64"><?= e($etiqueta) ?></td>
          <td class="py-2.5"><?= is_array($v) ? e(implode(', ', $v)) : nl2br(e((string)$v)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div class="grid md:grid-cols-3 gap-3 mt-6 text-sm">
      <div><p class="mac-muted text-xs">Diligenciado</p><p><?= e($registro['created_at']) ?></p></div>
      <div><p class="mac-muted text-xs">Fecha de vencimiento / compromiso</p><p><?= e((string)($registro['fecha_vencimiento'] ?? '—')) ?></p></div>
      <div><p class="mac-muted text-xs">Firma del responsable</p><p class="mt-6 border-t pt-1" style="border-color:var(--mac-stroke)">Nombre y firma</p></div>
    </div>
    <p class="text-[11px] mac-muted mt-4">Documento generado por la Plataforma SG-SST basado en «<?= e($formato['archivo_origen']) ?>». Su validez como evidencia depende de la firma y de los soportes asociados.</p>
  </section>

  <?php if ($puedeGestionar): ?>
  <form method="POST" action="<?= base_url("sgsst/formularios/{$registro['id']}/estado") ?>" class="mac-card p-4 flex flex-wrap gap-2 items-center no-print">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <span class="text-sm font-semibold">Cambiar estado:</span>
    <select name="estado" class="mac-input" style="padding:.5rem"><?php foreach (['abierto','en_gestion','cerrado'] as $s): ?><option <?= $registro['estado']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
    <button class="mac-btn">Guardar</button>
  </form>
  <?php endif; ?>
</div>
<style>@media print { .no-print, nav, aside, .mac-toasts { display:none !important; } main { padding:0 !important; } }</style>
