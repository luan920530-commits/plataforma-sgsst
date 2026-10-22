<div class="space-y-5">
  <div class="mac-card p-6">
    <p class="text-xs font-semibold" style="color:var(--mac-blue)">🏢 PORTAL DE MI EMPRESA</p>
    <h1 class="text-2xl font-bold mt-1">Mis empresas</h1>
    <p class="text-sm mac-muted">Elige la empresa para ver su documentación SG-SST, corregir datos, generar e imprimir documentos actualizados.</p>
  </div>
  <?php if (empty($empresas)): ?>
  <div class="mac-card p-8 text-center mac-muted">Aún no tienes empresas compartidas. Pide a tu consultor SG-SST que te otorgue acceso.</div>
  <?php else: ?>
  <div class="grid md:grid-cols-2 gap-4">
    <?php foreach ($empresas as $e): ?>
    <a href="<?= base_url("mi-empresa/{$e['id']}") ?>" class="mac-card p-5 block">
      <h2 class="font-bold"><?= e($e['razon_social']) ?></h2>
      <p class="text-xs mac-muted">NIT <?= e($e['nit']) ?> · Riesgo <?= e((string)$e['nivel_riesgo']) ?> · <?= (int)$e['num_trabajadores'] ?> trabajadores</p>
      <span class="mac-badge mt-2" style="background:#e8f1fe;color:#0060c9"><?= $e['permiso'] === 'representante' ? '✏️ Representante (puede corregir)' : '👁 Solo lectura' ?></span>
    </a><?php endforeach; ?>
  </div><?php endif; ?>
</div>
