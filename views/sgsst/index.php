<div class="space-y-6">
  <div class="flex flex-wrap justify-between gap-3 items-start">
    <div><h1 class="text-2xl font-bold text-slate-800">Centro de cumplimiento SG-SST</h1>
    <p class="text-slate-500 text-sm">Administra, gestiona, crea y audita el SG-SST de cada empresa: estándares mínimos (Res. 0312/2019), plan anual, registros, plantillas y trazabilidad.</p></div>
    <a href="<?= base_url('empresas/nueva') ?>" class="px-4 py-2 bg-brand-600 text-white rounded-lg">+ Registrar empresa</a>
  </div>
  <div class="bg-blue-50 border border-blue-200 text-blue-900 rounded-xl p-4 text-sm">
    Al registrar una empresa se crea automáticamente su SG-SST: <b>evaluación inicial de estándares</b>, <b>plan de trabajo anual base</b> y acceso a <b>plantillas legales editables</b>. El módulo PPPRAE (emergencias) hace parte del ciclo <b>Hacer</b>.
  </div>
  <?php if (empty($empresas)): ?>
    <div class="bg-white border rounded-xl p-10 text-center text-slate-500">Aún no hay empresas. Registra la primera para crear su SG-SST.</div>
  <?php else: ?>
  <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($empresas as $e): $p = $e['puntaje']['total']; ?>
    <div class="bg-white border rounded-xl p-5 space-y-3">
      <div class="flex justify-between gap-2"><div><h2 class="font-bold text-slate-800"><?= e($e['razon_social']) ?></h2><p class="text-xs text-slate-500">NIT <?= e($e['nit']) ?> · Riesgo <?= e((string)$e['nivel_riesgo']) ?> · <?= (int)$e['num_trabajadores'] ?> trabajadores</p></div>
      <span class="h-fit px-2 py-1 rounded text-xs font-bold <?= $p<=60?'bg-red-100 text-red-700':($p<=85?'bg-yellow-100 text-yellow-800':'bg-green-100 text-green-700') ?>"><?= e($e['semaforo']) ?></span></div>
      <div><div class="flex justify-between text-xs"><span>Estándares mínimos</span><b><?= $p ?>%</b></div>
      <div class="h-2 bg-slate-100 rounded mt-1"><div class="h-2 rounded <?= $p<=60?'bg-red-500':($p<=85?'bg-yellow-500':'bg-green-500') ?>" style="width:<?= $p ?>%"></div></div></div>
      <p class="text-xs text-slate-500">Actividades vencidas: <b><?= (int)$e['pendientes'] ?></b> · Registros abiertos: <b><?= (int)$e['abiertos'] ?></b></p>
      <?php $crit = count(array_filter($e['alertas'] ?? [], fn($a) => $a['nivel'] === 'critica')); $adv = count($e['alertas'] ?? []) - $crit; ?>
      <?php if ($crit + $adv > 0): ?><p class="flex gap-1.5 flex-wrap"><?php if ($crit): ?><span class="mac-badge" style="background:#ffe5e5;color:#b42318">🔔 <?= $crit ?> alertas críticas</span><?php endif; ?><?php if ($adv): ?><span class="mac-badge" style="background:#fef0c7;color:#93370d">🟡 <?= $adv ?> por vencer</span><?php endif; ?></p><?php endif; ?>
      <?php if (!empty($e['compartida'])): ?><p><span class="mac-badge" style="background:#e0e7ff;color:#3730a3">👁 Compartida contigo · solo lectura</span></p><?php endif; ?>
      <a class="block text-center bg-brand-600 text-white rounded-lg py-2 font-medium" href="<?= base_url("sgsst/empresa/{$e['id']}") ?>">Abrir panel SG-SST</a>
    </div><?php endforeach; ?>
  </div><?php endif; ?>
</div>
