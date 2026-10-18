<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Documentos</h1>
            <p class="text-slate-500 text-sm mt-0.5">
                <?= count($documentos) ?> documentos generados ·
                <?= number_format($totalSize / 1024 / 1024, 2) ?> MB en total
            </p>
        </div>
    </div>

    <?php if (empty($documentos)): ?>
        <div class="bg-white rounded-xl border border-dashed border-slate-300 p-12 text-center">
            <i class="fa-solid fa-file-word text-4xl text-slate-300 mb-3"></i>
            <p class="text-slate-500 font-medium">Aún no has generado documentos</p>
            <p class="text-slate-400 text-sm mt-1">
                Los documentos se generan al completar el paso 7 del wizard.
            </p>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Empresa</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">NIT</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Versión</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Archivo</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Fecha</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Tamaño</th>
                        <th class="w-32"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($documentos as $d):
                        $tamano = file_exists($d['ruta']) ? filesize($d['ruta']) : 0;
                    ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-800"><?= e($d['razon_social']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= e($d['nit']) ?></td>
                            <td class="px-4 py-3 text-slate-600">v<?= e($d['version']) ?></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-2">
                                    <i class="fa-solid fa-file-zipper text-brand-600"></i>
                                    <span class="text-slate-700 text-xs font-mono"><?= e($d['nombre_archivo']) ?></span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 text-xs"><?= e($d['created_at']) ?></td>
                            <td class="px-4 py-3 text-slate-600 text-xs">
                                <?= number_format($tamano / 1024, 1) ?> KB
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="<?= base_url("analisis/{$d['analisis_id']}/descargar/" . urlencode($d['nombre_archivo'])) ?>"
                                   class="text-brand-600 hover:bg-brand-50 p-1.5 rounded"
                                   title="Descargar">
                                    <i class="fa-solid fa-download"></i>
                                </a>
                                <form method="POST" action="<?= base_url("documentos/{$d['id']}/eliminar") ?>"
                                      class="inline" onsubmit="return confirm('¿Eliminar este documento?')">
                                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                    <button type="submit" class="text-red-500 hover:bg-red-50 p-1.5 rounded" title="Eliminar">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
