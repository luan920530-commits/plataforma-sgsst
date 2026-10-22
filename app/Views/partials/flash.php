<?php if (!empty($flashes)): ?>
    <div class="mb-6 space-y-3" aria-live="polite">
        <?php foreach ($flashes as $flash): ?>
            <?php
            $type = $flash['type'] ?? 'info';
            $style = match ($type) {
                'success' => ['bg-emerald-50/95 text-emerald-800 ring-emerald-200', 'check-circle'],
                'error' => ['bg-rose-50/95 text-rose-800 ring-rose-200', 'alert'],
                'warning' => ['bg-amber-50/95 text-amber-800 ring-amber-200', 'alert'],
                default => ['bg-blue-50/95 text-blue-800 ring-blue-200', 'info'],
            };
            ?>
            <div class="flash-message flex items-start gap-3 rounded-2xl px-4 py-3.5 text-sm shadow-sm ring-1 backdrop-blur-xl <?= $style[0] ?>" role="alert">
                <span class="mt-0.5 shrink-0"><?= icon($style[1], 'h-5 w-5') ?></span>
                <p class="flex-1 leading-6"><?= e($flash['message']) ?></p>
                <button type="button" class="rounded-lg p-0.5 opacity-60 transition hover:opacity-100" data-dismiss-flash aria-label="Cerrar mensaje"><?= icon('x', 'h-4 w-4') ?></button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
