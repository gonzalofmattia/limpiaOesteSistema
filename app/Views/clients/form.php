<?php
$isEdit = $client !== null;
$action = url($isEdit ? '/clientes/' . (int) $client['id'] : '/clientes');
$c = $client ?? [];
$segments = is_array($segments ?? null) ? $segments : [];
$segmentsJson = json_encode($segments, JSON_UNESCAPED_UNICODE);
$initialType = (string) ($c['client_type'] ?? 'mayorista');
$initialMarkup = isset($c['default_markup']) && $c['default_markup'] !== null && $c['default_markup'] !== ''
    ? (string) $c['default_markup']
    : '';
$clientFormCfg = json_encode([
    'clientType' => $initialType,
    'customMarkup' => $initialMarkup,
    'segments' => $segments,
], JSON_UNESCAPED_UNICODE);
?>
<div class="max-w-2xl bg-white rounded-xl border border-gray-200 shadow-sm p-6">
    <script>
    window.__clientFormCfg = <?= $clientFormCfg ?: '{"clientType":"mayorista","customMarkup":"","segments":[]}' ?>;
    function clientFormState(cfg) {
        return {
            clientType: cfg && cfg.clientType ? cfg.clientType : 'mayorista',
            customMarkup: cfg && cfg.customMarkup ? cfg.customMarkup : '',
            segments: cfg && Array.isArray(cfg.segments) ? cfg.segments : [],
            segmentMarkup() {
                const seg = this.segments.find((s) => s.segment_key === this.clientType);
                return seg ? Number(seg.default_markup || 0) : 60;
            },
            effectiveMarkup() {
                const cm = String(this.customMarkup || '').trim();
                return cm !== '' ? Number(cm) : this.segmentMarkup();
            }
        };
    }
    </script>
    <?php if ($isEdit): ?>
        <?php $balance = isset($c['effective_balance']) ? (float) $c['effective_balance'] : (float) ($c['balance'] ?? 0); ?>
        <div class="mb-4">
            <?php if ($balance > 0): ?>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-800">Saldo: <?= formatPrice($balance) ?> [debe]</span>
            <?php else: ?>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">Al día</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <form method="post" action="<?= e($action) ?>" class="space-y-4" x-data="clientFormState(window.__clientFormCfg)">
        <?= csrfField() ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
            <input type="text" name="name" required value="<?= e($c['name'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Razón social</label>
            <input type="text" name="business_name" value="<?= e($c['business_name'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contacto</label>
                <input type="text" name="contact_person" value="<?= e($c['contact_person'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                <input type="text" name="phone" value="<?= e($c['phone'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" value="<?= e($c['email'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
            <textarea name="address" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><?= e($c['address'] ?? '') ?></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ciudad</label>
            <input type="text" name="city" value="<?= e($c['city'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
            <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><?= e($c['notes'] ?? '') ?></textarea>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Segmento</label>
                <select name="client_type" x-model="clientType"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <?php foreach ($segments as $seg): ?>
                        <?php
                        $segKey = (string) ($seg['segment_key'] ?? '');
                        $segLabel = (string) ($seg['segment_label'] ?? $segKey);
                        $segMarkup = (float) ($seg['default_markup'] ?? 0);
                        ?>
                        <option value="<?= e($segKey) ?>" <?= $segKey === $initialType ? 'selected' : '' ?>>
                            <?= e($segLabel) ?> (<?= e(number_format($segMarkup, 0, ',', '.')) ?>%)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">
                    Markup del segmento:
                    <span class="font-semibold" x-text="segmentMarkup() + '%'"></span>
                </p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Markup individual (%)</label>
                <input type="number" name="default_markup" step="0.01" min="0" max="500"
                       x-model="customMarkup"
                       :placeholder="'Dejar vacío para usar ' + segmentMarkup() + '%'"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <p class="text-xs mt-1">
                    <span class="text-gray-500">Markup efectivo: </span>
                    <span class="font-semibold text-gray-800" x-text="effectiveMarkup() + '%'"></span>
                    <span x-show="String(customMarkup || '').trim() !== ''" class="text-yellow-700">(override individual)</span>
                    <span x-show="String(customMarkup || '').trim() === ''" class="text-green-700">(del segmento)</span>
                </p>
            </div>
        </div>
        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" <?= !isset($c['is_active']) || $c['is_active'] ? 'checked' : '' ?>> Activo
        </label>
        <div class="flex gap-3 pt-4 border-t border-gray-200">
            <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Guardar</button>
            <a href="<?= e(url('/clientes')) ?>" class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm">Cancelar</a>
        </div>
    </form>
</div>
