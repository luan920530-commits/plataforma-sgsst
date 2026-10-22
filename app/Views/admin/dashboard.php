<?php $activeRate = ((int) ($stats['total'] ?? 0)) > 0 ? round(((int) $stats['activas'] / (int) $stats['total']) * 100) : 0; ?>
<div class="page-heading">
    <div>
        <span class="eyebrow">Centro de control</span>
        <h1>Todo tu ecosistema, en perspectiva.</h1>
        <p>Supervisa empresas, capacidad y actividad de la plataforma desde un único lugar.</p>
    </div>
    <a href="<?= e(url('/admin/empresas/nueva')) ?>" class="btn-primary"><?= icon('plus', 'h-4 w-4') ?> Nueva empresa</a>
</div>

<section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ([
        ['Empresas activas', (int) $stats['activas'], 'de ' . (int) $stats['total'] . ' registradas', 'building', 'bg-blue-50 text-blue-600'],
        ['Usuarios activos', $users, 'accesos habilitados', 'users', 'bg-violet-50 text-violet-600'],
        ['Personas cubiertas', number_format((int) $stats['trabajadores']), 'según dotación declarada', 'heart', 'bg-rose-50 text-rose-600'],
        ['Documentos generados', number_format($documents), 'en todas las empresas', 'documents', 'bg-emerald-50 text-emerald-600'],
    ] as $metric): ?>
        <article class="metric-card">
            <div class="flex items-start justify-between"><span class="metric-icon <?= $metric[4] ?>"><?= icon($metric[3], 'h-5 w-5') ?></span><span class="text-[10px] font-semibold uppercase tracking-wider text-slate-300">SaaS</span></div>
            <p class="mt-5 text-[30px] font-semibold tracking-[-0.04em] text-slate-950"><?= e($metric[1]) ?></p>
            <p class="mt-1 text-sm font-medium text-slate-600"><?= e($metric[0]) ?></p>
            <p class="mt-1 text-xs text-slate-400"><?= e($metric[2]) ?></p>
        </article>
    <?php endforeach; ?>
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.65fr)_minmax(300px,0.75fr)]">
    <section class="ios-card overflow-hidden">
        <div class="card-heading">
            <div><h2>Empresas recientes</h2><p>Clasificación y estado actual de cada tenant.</p></div>
            <a href="<?= e(url('/admin/empresas')) ?>" class="text-link">Ver todas <?= icon('arrow-right', 'h-3.5 w-3.5') ?></a>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Empresa</th><th>Actividad</th><th>Estándares</th><th>Estado</th><th><span class="sr-only">Ver</span></th></tr></thead>
                <tbody>
                <?php foreach ($tenants as $tenant): ?>
                    <tr>
                        <td><div class="flex items-center gap-3"><span class="company-avatar"><?= e(initials($tenant['razonsocial'])) ?></span><div><a href="<?= e(url('/admin/empresas/' . $tenant['id'])) ?>" class="font-semibold text-slate-800 hover:text-blue-600"><?= e($tenant['razonsocial']) ?></a><p class="mt-0.5 text-[11px] text-slate-400">NIT <?= e($tenant['nit']) ?></p></div></div></td>
                        <td><p class="max-w-[210px] truncate text-xs text-slate-600"><?= e($tenant['actividad'] ?? $tenant['sector_economico']) ?></p><p class="mt-1 text-[10px] text-slate-400">Riesgo <?= e($tenant['nivel_riesgo']) ?> · <?= (int) $tenant['numero_trabajadores'] ?> trabajadores</p></td>
                        <td><span class="badge badge-blue"><?= (int) $tenant['grupo_estandares'] ?> estándares</span></td>
                        <td><span class="badge <?= status_class($tenant['estado']) ?>"><span class="badge-dot"></span><?= e($tenant['estado']) ?></span></td>
                        <td><a href="<?= e(url('/admin/empresas/' . $tenant['id'])) ?>" class="btn-icon h-8 w-8" aria-label="Ver empresa"><?= icon('chevron-right', 'h-4 w-4') ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="space-y-6">
        <section class="ios-card p-5">
            <div class="flex items-start justify-between"><div><h2 class="text-base font-semibold">Salud de la plataforma</h2><p class="mt-1 text-xs text-slate-400">Empresas habilitadas para operar</p></div><span class="metric-icon h-9 w-9 bg-emerald-50 text-emerald-600"><?= icon('activity', 'h-4 w-4') ?></span></div>
            <div class="mt-5 flex items-center gap-5">
                <div class="progress-ring" style="--progress: <?= $activeRate * 3.6 ?>deg"><span><?= $activeRate ?>%</span></div>
                <div class="flex-1 space-y-3 text-xs"><div class="flex justify-between"><span class="text-slate-500">Activas</span><strong><?= (int) $stats['activas'] ?></strong></div><div class="flex justify-between"><span class="text-slate-500">Suspendidas</span><strong><?= (int) $stats['total'] - (int) $stats['activas'] ?></strong></div><div class="flex justify-between"><span class="text-slate-500">Esquema completo</span><strong><?= (int) $stats['grupo_60'] ?></strong></div></div>
            </div>
        </section>
        <section class="ios-card p-5">
            <div class="flex items-center justify-between"><div><h2 class="text-base font-semibold">Catálogo normativo</h2><p class="mt-1 text-xs text-slate-400">Tres niveles preconfigurados</p></div><a href="<?= e(url('/admin/estandares')) ?>" class="text-link">Explorar</a></div>
            <div class="mt-5 grid grid-cols-3 gap-2">
                <?php foreach ([7 => ['Micro', 'bg-emerald-50 text-emerald-700'], 21 => ['PYME', 'bg-blue-50 text-blue-700'], 60 => ['Completo', 'bg-violet-50 text-violet-700']] as $group => $label): ?>
                    <a href="<?= e(url('/admin/estandares?grupo=' . $group)) ?>" class="rounded-2xl p-3 text-center ring-1 ring-black/[0.03] <?= $label[1] ?>"><p class="text-2xl font-semibold"><?= (int) $standardCounts[$group] ?></p><p class="mt-1 text-[10px] font-medium">estándares · <?= e($label[0]) ?></p></a>
                <?php endforeach; ?>
            </div>
            <p class="mt-4 flex items-start gap-2 text-[11px] leading-5 text-slate-400"><?= icon('briefcase', 'mt-0.5 h-3.5 w-3.5 shrink-0') ?><span><strong class="text-slate-600"><?= $activitiesCount ?> actividades económicas</strong> con matrices IPEVR y perfiles iniciales.</span></p>
        </section>
    </div>
</div>

<section class="ios-card mt-6 p-5 sm:p-6">
    <div class="card-heading !border-0 !p-0"><div><h2>Actividad reciente</h2><p>Trazabilidad de las acciones más recientes en el SaaS.</p></div><a href="<?= e(url('/admin/auditoria')) ?>" class="text-link">Auditoría completa <?= icon('arrow-right', 'h-3.5 w-3.5') ?></a></div>
    <div class="mt-5 grid gap-3 lg:grid-cols-2">
        <?php foreach ($audit as $entry): ?>
            <div class="flex items-start gap-3 rounded-2xl bg-slate-50/80 p-3.5">
                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white text-slate-500 shadow-sm"><?= icon('shield-plain', 'h-4 w-4') ?></span>
                <div class="min-w-0 flex-1"><p class="text-xs font-semibold text-slate-700"><?= e($entry['accion']) ?> <span class="font-normal text-slate-400">· <?= e($entry['entidad']) ?></span></p><p class="mt-1 truncate text-[11px] text-slate-500"><?= e($entry['usuario'] ?? 'Sistema') ?> · <?= e($entry['razonsocial'] ?? 'Plataforma') ?></p></div>
                <time class="shrink-0 text-[10px] text-slate-400"><?= format_date($entry['created_at']) ?></time>
            </div>
        <?php endforeach; ?>
    </div>
</section>
