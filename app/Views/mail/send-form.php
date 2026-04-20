<?php
/** @var array<string,mixed> $quote */
/** @var list<array<string,mixed>> $invoiceAttachments */
/** @var list<array<string,mixed>> $mailHistory */
/** @var string $defaultSubject */
/** @var string $mailPreviewHtml */
$qid = (int) ($quote['id'] ?? 0);
$xData = json_encode(
    ['showPreview' => false, 'previewHtml' => $mailPreviewHtml],
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
) ?: '{}';
$xDataAttr = htmlspecialchars($xData, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="max-w-3xl space-y-6" x-data="<?= $xDataAttr ?>">
    <nav class="text-sm text-gray-500">
        <a href="<?= e(url('/presupuestos')) ?>" class="text-[#1565C0] hover:underline">Presupuestos</a>
        <span class="mx-1">›</span>
        <a href="<?= e(url('/presupuestos/' . $qid)) ?>" class="text-[#1565C0] hover:underline">#<?= e((string) ($quote['quote_number'] ?? '')) ?></a>
        <span class="mx-1">›</span>
        <span class="text-gray-700">Enviar mail</span>
    </nav>

    <div>
        <h2 class="text-xl font-semibold text-gray-900">Enviar factura por mail</h2>
        <p class="text-sm text-gray-600 mt-1">Cliente: <strong><?= e((string) ($quote['client_name'] ?? '—')) ?></strong>
            <?php if (!empty($quote['client_email'])): ?>
                · <?= e((string) $quote['client_email']) ?>
            <?php else: ?>
                <span class="text-amber-700">· Sin email cargado</span>
            <?php endif; ?>
        </p>
    </div>

    <?php if ($invoiceAttachments === []): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-900 px-4 py-3 text-sm">
            No hay facturas cargadas para este presupuesto. Subí una factura primero desde el detalle del presupuesto.
        </div>
        <a href="<?= e(url('/presupuestos/' . $qid)) ?>" class="inline-flex px-4 py-2 rounded-lg border border-gray-300 text-sm">Volver al presupuesto</a>
    <?php else: ?>
        <form method="post" action="<?= e(url('/presupuestos/' . $qid . '/enviar-mail')) ?>" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
            <?= csrfField() ?>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Factura a adjuntar</label>
                <select name="attachment_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Seleccioná…</option>
                    <?php foreach ($invoiceAttachments as $a): ?>
                        <option value="<?= (int) $a['id'] ?>"><?= e((string) ($a['original_filename'] ?? '')) ?> — <?= e((string) ($a['created_at'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Asunto</label>
                <input type="text" name="subject" value="<?= e($defaultSubject) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje personalizado <span class="text-gray-400 font-normal">(opcional)</span></label>
                <textarea name="custom_message" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                          placeholder="Si lo dejás vacío, se usa el texto predeterminado del mail."></textarea>
            </div>

            <div class="flex flex-wrap gap-2 items-center">
                <button type="button" @click="showPreview = !showPreview"
                        class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                    <span x-text="showPreview ? 'Ocultar vista previa' : 'Ver vista previa del mail'"></span>
                </button>
            </div>
            <div x-show="showPreview" x-cloak class="border border-gray-200 rounded-lg overflow-hidden bg-gray-50">
                <iframe class="w-full min-h-[420px] bg-white" title="Vista previa" :srcdoc="previewHtml"></iframe>
            </div>

            <div class="flex flex-wrap gap-3 pt-2 border-t border-gray-200">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">Enviar mail</button>
                <a href="<?= e(url('/presupuestos/' . $qid)) ?>" class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm">Cancelar</a>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($mailHistory !== []): ?>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Historial de envíos (este presupuesto)</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                        <tr>
                            <th class="text-left px-3 py-2">Fecha</th>
                            <th class="text-left px-3 py-2">Destino</th>
                            <th class="text-left px-3 py-2">Asunto</th>
                            <th class="text-center px-3 py-2">Estado</th>
                            <th class="text-left px-3 py-2">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($mailHistory as $log): ?>
                            <tr>
                                <td class="px-3 py-2 whitespace-nowrap"><?= e((string) ($log['sent_at'] ?? '')) ?></td>
                                <td class="px-3 py-2"><?= e((string) ($log['to_email'] ?? '')) ?></td>
                                <td class="px-3 py-2"><?= e((string) ($log['subject'] ?? '')) ?></td>
                                <td class="px-3 py-2 text-center">
                                    <?php if (($log['status'] ?? '') === 'sent'): ?>
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">enviado</span>
                                    <?php else: ?>
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-800">falló</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-600 max-w-xs break-words">
                                    <?php if (($log['status'] ?? '') !== 'sent' && !empty($log['error_message'])): ?>
                                        <?= e((string) $log['error_message']) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
