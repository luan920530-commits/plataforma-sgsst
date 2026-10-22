<?php
$oldNames = old($prefix . '_nombre', null);
if (is_array($oldNames)) {
    $repeatRows = [];
    foreach ($oldNames as $index => $name) {
        $row = [];
        foreach ($fields as $field) {
            $oldValues = old($prefix . '_' . $field[0], []);
            $row[$field[0]] = is_array($oldValues) && isset($oldValues[$index]) ? $oldValues[$index] : '';
        }
        $repeatRows[] = $row;
    }
} else {
    $repeatRows = json_decode($plan[$jsonKey] ?? '[]', true);
    if (!is_array($repeatRows)) {
        $repeatRows = [];
    }
}
if ($repeatRows === []) {
    $repeatRows = [array_fill_keys(array_map(static fn($field) => $field[0], $fields), '')];
}
$renderRow = static function (array $row) use ($prefix, $fields): void { ?>
    <div class="repeat-row rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-100" data-repeat-row>
        <div class="grid gap-3 <?= count($fields) >= 5 ? 'lg:grid-cols-5' : (count($fields) === 4 ? 'sm:grid-cols-2 lg:grid-cols-4' : 'sm:grid-cols-3') ?>">
            <?php foreach ($fields as $field): ?><label><span class="mb-1 block text-[10px] font-medium text-slate-400"><?= e($field[1]) ?></span><input name="<?= e($prefix . '_' . $field[0]) ?>[]" class="input h-10 bg-white text-xs" value="<?= e($row[$field[0]] ?? '') ?>" placeholder="<?= e($field[2] ?? '') ?>"></label><?php endforeach; ?>
        </div>
        <button type="button" class="mt-3 inline-flex items-center gap-1 text-[10px] font-medium text-rose-500 hover:text-rose-700" data-remove-row><?= icon('x', 'h-3 w-3') ?> Quitar fila</button>
    </div>
<?php }; ?>
<div class="space-y-3" data-repeat-list><?php foreach ($repeatRows as $repeatRow): $renderRow($repeatRow); endforeach; ?></div>
<button type="button" class="btn-secondary mt-3 h-9 text-xs" data-add-row="<?= e($prefix) ?>"><?= icon('plus', 'h-3.5 w-3.5') ?> Agregar fila</button>
<template id="repeat-template-<?= e($prefix) ?>"><?php $renderRow(array_fill_keys(array_map(static fn($field) => $field[0], $fields), '')); ?></template>
