<?php $eid = (int)$empresa['id']; $total = array_sum(array_map(fn($g) => count($g['archivos']), $grupos)); ?>
<div class="space-y-5">
  <div class="mac-card p-5 flex flex-wrap justify-between gap-3 items-center">
    <div>
      <p class="text-xs font-semibold" style="color:var(--mac-blue)">SG-SST · CATÁLOGO DOCUMENTAL MAESTRO</p>
      <h1 class="text-2xl font-bold">Documentos por estándar</h1>
      <p class="text-sm mac-muted"><?= e($empresa['razon_social']) ?> · <?= count($grupos) ?> carpetas · <?= $total ?> archivos reales del paquete SG-SST</p>
    </div>
    <a class="mac-btn-secondary" href="<?= base_url("sgsst/empresa/{$eid}") ?>">← Panel SG-SST</a>
  </div>

  <div class="mac-alert mac-alert-info">
    <b>✨ Generar diligenciado</b> crea una copia con los datos de la empresa (razón social, NIT, representante legal, actividad, ciudad…) sin tocar la plantilla original; queda registrada con hash SHA-256 y en la auditoría. Los campos de firma y responsables específicos se completan al revisar el documento. Los archivos <span class="mac-badge" style="background:#fef0c7;color:#93370d">legado .doc/.xls</span> aún no se pueden diligenciar: se descargan como original mientras se convierten.
  </div>

  <input id="buscador" type="text" placeholder="🔎 Buscar documento o estándar…" class="mac-input w-full" style="padding:.7rem 1rem" oninput="filtrar(this.value)">

  <?php if ($generados): ?>
  <section class="mac-card p-5">
    <h2 class="font-bold mb-2">🗂 Documentos ya generados para esta empresa</h2>
    <ul class="text-sm space-y-1.5">
      <?php foreach ($generados as $g): ?>
      <li class="flex flex-wrap gap-2 items-center justify-between border-b pb-1.5" style="border-color:var(--mac-stroke)">
        <span>📄 <?= e($g['nombre_archivo']) ?> <span class="text-xs mac-muted"><?= e($g['created_at']) ?> · SHA-256 <?= e(substr((string)$g['hash_sha256'], 0, 10)) ?>…</span></span>
        <a class="underline" style="color:var(--mac-blue)" href="<?= base_url("sgsst/documentos-generados/{$g['id']}/descargar") ?>">Descargar</a>
      </li><?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php foreach ($grupos as $g): ?>
  <section class="mac-card overflow-hidden grupo-doc">
    <div class="px-5 py-3 border-b flex flex-wrap gap-2 items-center" style="border-color:var(--mac-stroke)">
      <span class="mac-badge" style="background:#e8f1fe;color:#0060c9">Estándar <?= e($g['numero'] ?: '—') ?></span>
      <h2 class="font-bold text-[15px]"><?= e(mb_convert_case(mb_strtolower($g['estandar']), MB_CASE_TITLE, 'UTF-8')) ?></h2>
      <span class="text-xs mac-muted"><?= count($g['archivos']) ?> archivo(s)</span>
    </div>
    <table class="w-full text-sm">
      <tbody class="divide-y">
      <?php foreach ($g['archivos'] as $a): ?>
        <tr class="fila-doc">
          <td class="px-5 py-2.5">
            <span class="font-medium"><?= e($a['nombre']) ?></span>
            <span class="text-xs mac-muted block"><?= e($a['tipo']) ?> · <?= strtoupper($a['ext']) ?> · <?= $a['kb'] ?> KB</span>
          </td>
          <td class="px-5 py-2.5 text-right whitespace-nowrap">
            <?php if ($a['legado']): ?><span class="mac-badge" style="background:#fef0c7;color:#93370d">legado</span><?php endif; ?>
            <a class="mac-btn-secondary !py-1.5" href="<?= base_url("sgsst/empresa/{$eid}/documentos/descargar?f=" . urlencode($a['relativo'])) ?>">⬇ Original</a>
            <?php if ($a['generable'] && empty($soloLectura)): ?>
            <form method="POST" action="<?= base_url("sgsst/empresa/{$eid}/documentos/generar") ?>" class="inline">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="f" value="<?= e($a['relativo']) ?>">
              <button class="mac-btn !py-1.5" title="Copia diligenciada con los datos de la empresa">✨ Generar diligenciado</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php endforeach; ?>
</div>
<script>
function filtrar(q){
  q = q.toLowerCase();
  document.querySelectorAll('.grupo-doc').forEach(g => {
    let visibles = 0;
    g.querySelectorAll('.fila-doc').forEach(f => {
      const ok = f.textContent.toLowerCase().includes(q);
      f.style.display = ok ? '' : 'none'; if (ok) visibles++;
    });
    g.style.display = (visibles || g.querySelector('h2').textContent.toLowerCase().includes(q)) ? '' : 'none';
  });
}
</script>
