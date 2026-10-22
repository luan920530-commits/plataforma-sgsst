<div class="error-code"><?= e($status ?? 500) ?></div>
<h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-950"><?= e($title ?? 'Error') ?></h1>
<p class="mx-auto mt-4 max-w-lg text-sm leading-7 text-slate-500"><?= e($message ?? 'No pudimos completar la solicitud.') ?></p>
<div class="mt-8 flex flex-wrap items-center justify-center gap-3">
    <a href="<?= e(url('/dashboard')) ?>" class="btn-primary">Volver al inicio <?= icon('arrow-right', 'h-4 w-4') ?></a>
    <button type="button" onclick="history.back()" class="btn-secondary">Página anterior</button>
</div>
