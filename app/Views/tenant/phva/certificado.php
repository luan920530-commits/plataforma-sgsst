<article class="print-sheet !p-10 sm:!p-14">
<div class="rounded-3xl border-2 border-blue-900 px-7 py-10 text-center sm:px-12">
<p class="text-[10px] font-bold uppercase tracking-[0.22em] text-blue-700"><?= e($tenant['razonsocial']) ?> · NIT <?= e($tenant['nit']) ?></p>
<div class="mx-auto mt-7 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-700"><?= icon('cap','h-7 w-7') ?></div>
<h1 class="mt-6 text-2xl font-bold tracking-tight text-slate-900">Constancia de participación y evaluación</h1>
<p class="mt-6 text-xs text-slate-500">Se hace constar que</p>
<h2 class="mt-2 text-xl font-semibold"><?= e($attendee['nombre']) ?></h2>
<p class="mt-1 text-xs text-slate-500">Documento <?= e($attendee['documento']) ?><?= $attendee['vinculacion']?' · '.e($attendee['vinculacion']):'' ?></p>
<p class="mx-auto mt-6 max-w-xl text-sm leading-7 text-slate-700">participó en la actividad <strong><?= e($training['tipo_formacion']) ?></strong> denominada <strong>“<?= e($training['tema']) ?>”</strong>, con una duración registrada de <?= e($training['duracion_horas']) ?> horas, modalidad <?= e($training['modalidad']) ?>, realizada el <?= format_date($training['fecha_realizacion']) ?>.</p>
<div class="mx-auto mt-6 grid max-w-xl grid-cols-2 gap-3 text-left"><div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] text-slate-400">Evaluación registrada</p><p class="mt-1 text-sm font-semibold"><?= e($attendee['evaluacion']) ?></p></div><div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] text-slate-400">Escala aplicada</p><p class="mt-1 text-sm font-semibold"><?= e($attendee['evaluacion_escala']) ?></p></div></div>
<p class="mt-6 text-xs text-slate-600">Responsable / instructor: <strong><?= e($training['responsable']) ?></strong></p>
<div class="mx-auto mt-10 max-w-xs border-t border-slate-400 pt-3 text-xs"><strong><?= e($training['responsable']) ?></strong><p class="mt-1 text-slate-500">Firma del responsable</p></div>
<div class="mt-10 border-t border-slate-200 pt-4 text-left text-[9px] leading-4 text-slate-500"><p>Código: <strong><?= e($attendee['certificado_codigo']) ?></strong> · Emitido: <?= e($attendee['certificado_emitido_at']) ?></p><p class="mt-1 break-all">Integridad SHA-256: <?= e($integrityHash) ?></p><p class="mt-2">Esta constancia reproduce la asistencia y evaluación registradas. No certifica por sí misma idoneidad, licencia, competencia profesional ni cumplimiento legal; su contenido debe ser validado y firmado por el responsable de la empresa.</p></div>
</div>
</article>
