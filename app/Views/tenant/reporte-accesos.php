<div class="page-heading">
    <div><a href="<?= e(url('/autoreportes')) ?>" class="text-link mb-3"><?= icon('arrow-left', 'h-3.5 w-3.5') ?> Autoreportes</a><span class="eyebrow">Participación sin crear cuentas</span><h1>Accesos para reportes</h1><p>Genera un enlace o QR para que trabajadores, contratistas o visitantes diligencien el formulario sin iniciar sesión.</p></div>
    <span class="badge badge-blue"><?= count($accesses) ?> acceso(s)</span>
</div>
<?php if ($created): ?>
    <section class="ios-card mt-6 border-2 border-emerald-100 p-5 sm:p-6">
        <div class="grid items-center gap-6 md:grid-cols-[1fr_240px]">
            <div><span class="badge badge-green">Acceso creado</span><h2 class="mt-3 text-lg font-semibold"><?= e($created['nombre']) ?></h2><p class="mt-2 text-xs leading-6 text-slate-500">Comparte esta URL o el QR. El token completo se muestra una sola vez; si se pierde, revoca este acceso y crea uno nuevo.</p><label class="label mt-4" for="report-access-url">URL pública</label><div class="flex flex-col gap-2 sm:flex-row"><input id="report-access-url" class="input font-mono !text-xs" readonly value="<?= e($created['url']) ?>"><button type="button" class="btn-primary shrink-0" data-copy-target="report-access-url">Copiar URL</button></div></div>
            <div><div class="qr-frame" data-qr-code="<?= e($created['url']) ?>"><p class="text-xs text-slate-400">Generando QR local…</p></div><button type="button" class="btn-secondary mt-3 w-full" data-download-qr="autoreporte-qr">Descargar QR</button><p class="mt-2 text-center text-[10px] text-slate-400">El QR contiene únicamente la URL del formulario.</p></div>
        </div>
    </section>
<?php endif; ?>
<div class="mt-6 grid items-start gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
    <form method="post" action="<?= e(url('/autoreportes/accesos')) ?>" class="ios-card p-5 sm:p-6">
        <?= csrf_field() ?><h2 class="text-sm font-semibold">Nuevo acceso compartible</h2><p class="mt-1 text-[11px] leading-5 text-slate-400">Define una vigencia y, opcionalmente, un número máximo de reportes.</p>
        <label class="label mt-5" for="nombre">Nombre o grupo *</label><input id="nombre" name="nombre" class="input" required maxlength="180" value="<?= e(old('nombre')) ?>" placeholder="Ej. Contratistas sede norte"><?= field_error('nombre') ?>
        <label class="label mt-4" for="fecha_vencimiento">Fecha de vencimiento *</label><input id="fecha_vencimiento" name="fecha_vencimiento" type="date" class="input" required min="<?= date('Y-m-d') ?>" value="<?= e(old('fecha_vencimiento', date('Y-m-d', strtotime('+30 days')))) ?>"><?= field_error('fecha_vencimiento') ?>
        <label class="label mt-4" for="max_usos">Límite de usos</label><input id="max_usos" name="max_usos" type="number" min="0" max="10000" step="1" class="input" value="<?= e(old('max_usos', '0')) ?>"><p class="mt-1 text-[10px] text-slate-400">0 = sin límite de usos hasta el vencimiento o la revocación.</p><?= field_error('max_usos') ?>
        <button type="submit" class="btn-primary mt-5 w-full"><?= icon('shield-plain', 'h-4 w-4') ?> Generar enlace y QR</button>
        <div class="mt-4 rounded-xl bg-slate-50 p-3 text-[10px] leading-5 text-slate-400">Se almacena el hash SHA-256 del token, no el enlace secreto. El acceso no permite consultar reportes, documentos ni información interna.</div>
    </form>
    <section class="ios-card overflow-hidden">
        <div class="card-heading"><div><h2>Accesos registrados</h2><p>Revoca un enlace cuando deje de ser necesario.</p></div></div>
        <?php if ($accesses === []): ?><div class="empty-state"><span class="empty-icon"><?= icon('shield-plain', 'h-7 w-7') ?></span><h3>No hay accesos generados</h3><p>Crea el primero para compartir el formulario por URL o QR.</p></div><?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($accesses as $access): ?>
                    <?php $status = !(bool) $access['activo'] ? 'Revocado' : (strtotime($access['expira_at']) < time() ? 'Vencido' : ((int) $access['max_usos'] > 0 && (int) $access['usos'] >= (int) $access['max_usos'] ? 'Límite alcanzado' : 'Activo')); ?>
                    <article class="p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3"><div><div class="flex flex-wrap items-center gap-2"><h3 class="text-sm font-semibold text-slate-700"><?= e($access['nombre']) ?></h3><span class="badge <?= $status === 'Activo' ? 'badge-green' : 'badge-gray' ?>"><?= e($status) ?></span></div><p class="mt-2 font-mono text-[10px] text-slate-400">Token <?= e($access['token_hint']) ?> · Creado por <?= e($access['creado_por_nombre'] ?? 'Usuario eliminado') ?></p></div><?php if ((bool) $access['activo']): ?><form method="post" action="<?= e(url('/autoreportes/accesos/' . $access['id'] . '/revocar')) ?>" data-confirm="¿Revocar este acceso? Sus enlaces y códigos QR dejarán de funcionar."><?= csrf_field() ?><button type="submit" class="btn-danger-soft h-8 text-[11px]">Revocar</button></form><?php endif; ?></div>
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-[11px] sm:grid-cols-4"><div><dt class="text-slate-400">Vence</dt><dd class="mt-1 font-medium text-slate-600"><?= format_date($access['expira_at']) ?></dd></div><div><dt class="text-slate-400">Usos</dt><dd class="mt-1 font-medium text-slate-600"><?= (int) $access['usos'] ?><?= (int) $access['max_usos'] > 0 ? ' / ' . (int) $access['max_usos'] : ' · sin límite' ?></dd></div><div><dt class="text-slate-400">Último uso</dt><dd class="mt-1 font-medium text-slate-600"><?= $access['last_used_at'] ? format_date($access['last_used_at']) : 'Sin uso' ?></dd></div><div><dt class="text-slate-400">Creado</dt><dd class="mt-1 font-medium text-slate-600"><?= format_date($access['created_at']) ?></dd></div></dl>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
