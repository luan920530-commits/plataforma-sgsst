<?php $eid = (int)$empresa['id']; ?>
<div class="space-y-5">
  <div class="mac-card p-5 flex flex-wrap justify-between gap-3 items-center">
    <div>
      <p class="text-xs font-semibold" style="color:var(--mac-blue)">SG-SST · FORMULARIOS WEB</p>
      <h1 class="text-2xl font-bold">Formatos diligenciables</h1>
      <p class="text-sm mac-muted"><?= e($empresa['razon_social']) ?> · Los formatos del paquete, ahora como formularios con historial, vencimientos y auditoría.</p>
    </div>
    <a class="mac-btn-secondary" href="<?= base_url("sgsst/empresa/{$eid}") ?>">← Panel SG-SST</a>
  </div>

  <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-3">
    <?php foreach ($formatos as $codigo => $f): $c = $conteos[$codigo] ?? null; ?>
    <a href="<?= base_url("sgsst/empresa/{$eid}/formularios/{$codigo}" . (!empty($embed) ? "?embed=1" : "")) ?>" class="mac-card p-4 block">
      <div class="flex justify-between gap-2">
        <h2 class="font-bold text-[15px]">📝 <?= e($f['titulo']) ?></h2>
        <?php if (!empty($f['confidencial'])): ?><span class="mac-badge" style="background:#ffe5e5;color:#b42318">🔒 Confidencial</span><?php endif; ?>
      </div>
      <p class="text-xs mac-muted mt-1"><?= e($f['descripcion']) ?></p>
      <p class="text-xs mt-2">
        <span class="mac-badge" style="background:#e8f1fe;color:#0060c9">Estándar <?= e($f['estandar']) ?></span>
        <span class="mac-muted"><?= (int)($c['c'] ?? 0) ?> diligenciado(s)<?php if ((int)($c['abiertos'] ?? 0)): ?> · <b><?= (int)$c['abiertos'] ?> abierto(s)</b><?php endif; ?></span>
      </p>
    </a>
    <?php endforeach; ?>
  </div>

  <section class="mac-card p-5">
    <h2 class="font-bold mb-2">🕘 Últimos formularios diligenciados</h2>
    <?php if (empty($recientes)): ?><p class="text-sm mac-muted">Todavía no se diligencia ningún formulario. Elige uno arriba para empezar.</p>
    <?php else: ?><ul class="text-sm space-y-1.5">
      <?php foreach ($recientes as $r): $f = $formatos[$r['formato']] ?? null; if (!$f) continue; ?>
      <li class="flex flex-wrap gap-2 items-center justify-between border-b pb-1.5" style="border-color:var(--mac-stroke)">
        <span>📝 <b><?= e($f['titulo']) ?></b> <span class="text-xs mac-muted">#<?= (int)$r['id'] ?> · 📅 <?= e($r['fecha']) ?><?= $r['fecha_vencimiento'] ? ' · vence ' . e($r['fecha_vencimiento']) : '' ?></span></span>
        <span class="flex gap-2 items-center">
          <span class="mac-badge" style="<?= $r['estado']==='cerrado' ? 'background:#dcfae5;color:#067647' : 'background:#fef0c7;color:#93370d' ?>"><?= e($r['estado']) ?></span>
          <a class="underline" style="color:var(--mac-blue)" href="<?= base_url("sgsst/formularios/{$r['id']}") ?>">Ver / imprimir</a>
        </span>
      </li><?php endforeach; ?>
    </ul><?php endif; ?>
  </section>
</div>
