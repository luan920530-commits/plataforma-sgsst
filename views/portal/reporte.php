<div class="w-full max-w-lg mac-card p-6 md:p-8" style="border-radius:22px">
  <div class="text-center mb-5">
    <div class="w-14 h-14 mx-auto rounded-2xl flex items-center justify-center text-white text-2xl shadow" style="background:linear-gradient(180deg,#ff9500,#c93400)">🦺</div>
    <h1 class="text-xl font-bold mt-3">Reporte de seguridad</h1>
    <p class="text-sm mac-muted"><?= e($empresa['razon_social']) ?><br>Tu reporte llega directo al SG-SST de la empresa. Puedes hacerlo de forma anónima.</p>
  </div>

  <?php if (!empty($enviado)): ?>
    <div class="mac-alert" style="background:rgba(52,199,89,.12)">
      ✅ <b>¡Reporte recibido!</b> Gracias por cuidar tu seguridad y la de tus compañeros. El área de SST lo revisará y tomará acciones.
    </div>
    <a class="mac-btn w-full justify-center mt-4" href="<?= base_url("reporte/{$token}") ?>">Hacer otro reporte</a>
  <?php else: ?>
  <form method="POST" action="<?= base_url("reporte/{$token}") ?>" class="space-y-3">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label class="block text-sm font-medium">¿Qué quieres reportar?
      <select name="tipo" class="mac-input w-full mt-1" style="padding:.6rem">
        <option value="condicion_insegura">⚠️ Condición insegura (un peligro en el sitio)</option>
        <option value="acto_inseguro">👀 Acto inseguro (una práctica peligrosa)</option>
        <option value="incidente">🆘 Incidente / casi-accidente</option>
        <option value="accidente">🚑 Accidente de trabajo</option>
        <option value="sugerencia">💡 Sugerencia de mejora en seguridad y salud</option>
      </select></label>
    <label class="block text-sm font-medium">¿Dónde ocurrió o está el peligro? (área/proceso)
      <input name="area" class="mac-input w-full mt-1" style="padding:.6rem" placeholder="Ej: Planta 2, junto a la sierra"></label>
    <label class="block text-sm font-medium">Cuéntanos qué pasó o qué peligro viste *
      <textarea name="descripcion" required minlength="10" rows="4" class="mac-input w-full mt-1" style="padding:.6rem" placeholder="Describe la situación con el mayor detalle posible…"></textarea></label>
    <label class="block text-sm font-medium">¿Qué tan grave es?
      <select name="severidad" class="mac-input w-full mt-1" style="padding:.6rem">
        <option value="baja">🟢 Baja</option><option value="media" selected>🟡 Media</option>
        <option value="alta">🟠 Alta</option><option value="critica">🔴 Muy grave / puede causar un accidente ya</option>
      </select></label>
    <label class="block text-sm font-medium">Tu nombre (opcional, puedes ser anónimo)
      <input name="nombre" class="mac-input w-full mt-1" style="padding:.6rem" placeholder="Anónimo"></label>
    <button class="mac-btn w-full justify-center" style="padding:.8rem">📨 Enviar reporte</button>
    <p class="text-[11px] mac-muted text-center">En una emergencia con riesgo inmediato, avisa también de inmediato a tu jefe o a la brigada. Este formulario no reemplaza la atención de emergencias (123).</p>
  </form>
  <?php endif; ?>
</div>
