<?php require BASE_PATH . '/views/partials/wizard_steps.php'; ?>

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <div class="bg-brand-600 text-white px-6 py-4">
        <h2 class="text-xl font-semibold flex items-center gap-3">
            <i class="fa-solid fa-building"></i>
            Paso 1 — Información de la empresa
        </h2>
        <p class="text-brand-100 text-sm mt-1">Confirma los datos generales antes de continuar</p>
    </div>

    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs uppercase text-slate-500 mb-1">Razón social</label>
                <div class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800">
                    <?= e($analisis['razon_social']) ?>
                </div>
            </div>
            <div>
                <label class="block text-xs uppercase text-slate-500 mb-1">NIT</label>
                <div class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800">
                    <?= e($analisis['nit']) ?>
                </div>
            </div>
            <div>
                <label class="block text-xs uppercase text-slate-500 mb-1">N° trabajadores</label>
                <div class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800">
                    <?= (int) $analisis['num_trabajadores'] ?>
                </div>
            </div>
            <div>
                <label class="block text-xs uppercase text-slate-500 mb-1">Versión del análisis</label>
                <div class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800">
                    v<?= e($analisis['version']) ?>
                </div>
            </div>
            <div>
                <label class="block text-xs uppercase text-slate-500 mb-1">Fecha elaboración</label>
                <div class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800">
                    <?= e($analisis['fecha_elaboracion']) ?>
                </div>
            </div>
            <div>
                <label class="block text-xs uppercase text-slate-500 mb-1">Código documento</label>
                <div class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800">
                    <?= e($analisis['codigo_documento']) ?>
                </div>
            </div>
        </div>

        <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex gap-3">
                <i class="fa-solid fa-circle-info text-blue-600 mt-0.5"></i>
                <div class="text-sm text-blue-800">
                    <strong>Nota:</strong> Si necesitas modificar algún dato, hazlo desde
                    <a href="<?= base_url("empresas/{$analisis['empresa_id']}/editar") ?>"
                       class="underline font-semibold">aquí</a>.
                    Los cambios se reflejarán automáticamente en este análisis.
                </div>
            </div>
        </div>

        <div class="flex justify-between pt-6 mt-6 border-t border-slate-200">
            <a href="<?= base_url("empresas/{$analisis['empresa_id']}") ?>"
               class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50">
                <i class="fa-solid fa-arrow-left mr-2"></i> Volver a empresa
            </a>
            <a href="<?= base_url("analisis/{$analisisId}/wizard/paso2") ?>"
               class="px-6 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-medium">
                Continuar al inventario <i class="fa-solid fa-arrow-right ml-2"></i>
            </a>
        </div>
    </div>
</div>
