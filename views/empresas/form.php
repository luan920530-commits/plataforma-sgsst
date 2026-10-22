<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">
        <?= $empresa ? 'Editar empresa' : 'Nueva empresa' ?>
    </h1>

    <form method="POST"
          action="<?= base_url($empresa ? "empresas/{$empresa['id']}" : 'empresas') ?>"
          class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Razón social *</label>
                <input type="text" name="razon_social" required
                       value="<?= e($empresa['razon_social'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">NIT *</label>
                <input type="text" name="nit" required
                       value="<?= e($empresa['nit'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">CIIU</label>
                <input type="text" name="ciiu"
                       value="<?= e($empresa['ciiu'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Actividad económica</label>
                <input type="text" name="actividad_economica"
                       value="<?= e($empresa['actividad_economica'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Dirección</label>
                <input type="text" name="direccion"
                       value="<?= e($empresa['direccion'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Ciudad</label>
                <input type="text" name="ciudad"
                       value="<?= e($empresa['ciudad'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Departamento</label>
                <input type="text" name="departamento"
                       value="<?= e($empresa['departamento'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Teléfono</label>
                <input type="text" name="telefono"
                       value="<?= e($empresa['telefono'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" name="email"
                       value="<?= e($empresa['email'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">ARL</label>
                <input type="text" name="arl"
                       value="<?= e($empresa['arl'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nivel de riesgo</label>
                <select name="nivel_riesgo"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
                    <?php foreach (['I','II','III','IV','V'] as $n): ?>
                        <option value="<?= $n ?>" <?= ($empresa['nivel_riesgo'] ?? 'I') === $n ? 'selected' : '' ?>>
                            <?= $n ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Representante legal</label>
                <input type="text" name="resp_sgsst_nombre" value="<?= e($empresa['resp_sgsst_nombre'] ?? '') ?>" placeholder="Responsable del SG-SST (firma documentos)" class="w-full border rounded-lg px-3 py-2 mb-3">
                <input type="text" name="resp_sgsst_cc" value="<?= e($empresa['resp_sgsst_cc'] ?? '') ?>" placeholder="C.C. Responsable SG-SST" class="w-full border rounded-lg px-3 py-2 mb-3">
                <input type="text" name="representante_cc" value="<?= e($empresa['representante_cc'] ?? '') ?>" placeholder="C.C. Representante legal" class="w-full border rounded-lg px-3 py-2 mb-3">
                <input type="text" name="representante_legal"
                       value="<?= e($empresa['representante_legal'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">N° trabajadores</label>
                <input type="number" name="num_trabajadores" min="0"
                       value="<?= e($empresa['num_trabajadores'] ?? 0) ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Aforo máximo</label>
                <input type="number" name="aforo_max" min="0"
                       value="<?= e($empresa['aforo_max'] ?? 0) ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Área (m²)</label>
                <input type="number" step="0.01" name="area_m2" min="0"
                       value="<?= e($empresa['area_m2'] ?? 0) ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">N° pisos</label>
                <input type="number" name="num_pisos" min="1"
                       value="<?= e($empresa['num_pisos'] ?? 1) ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Horarios</label>
                <input type="text" name="horarios"
                       placeholder="Lunes a sábado de 13:00 a 00:00"
                       value="<?= e($empresa['horarios'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Áreas de alto riesgo</label>
                <textarea name="areas_alto_riesgo" rows="2"
                          class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none"><?= e($empresa['areas_alto_riesgo'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
            <a href="<?= base_url('empresas') ?>"
               class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50">
                Cancelar
            </a>
            <button type="submit"
                    class="px-6 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-medium">
                Guardar
            </button>
        </div>
    </form>
</div>
