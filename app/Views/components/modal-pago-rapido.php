<div x-data="pagoRapidoModal()"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
     @keydown.escape.window="open = false"
     @abrir-pago.window="openForClient($event.detail.clientId, $event.detail.clientName, $event.detail.clientBalance)"
     @abrir-pago-quote.window="openForQuote($event.detail.clientId, $event.detail.clientName, $event.detail.clientBalance, $event.detail.quoteId, $event.detail.quoteTotal)">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6" @click.away="open = false">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">💰 Registrar pago</h3>

        <div class="bg-gray-50 rounded-lg p-3 mb-4">
            <p class="text-sm text-gray-500">Cliente</p>
            <p class="font-medium text-gray-800" x-text="clientName"></p>
            <p class="text-sm mt-1" x-show="clientBalance !== 0">
                <span x-show="clientBalance > 0" class="text-red-600">
                    Debe: $<span x-text="formatNumber(clientBalance)"></span>
                </span>
                <span x-show="clientBalance < 0" class="text-blue-600">
                    A favor: $<span x-text="formatNumber(Math.abs(clientBalance))"></span>
                </span>
            </p>
        </div>

        <form method="POST" action="<?= e(url('/cuenta-corriente/pago-rapido')) ?>" class="space-y-3">
            <?= csrfField() ?>
            <input type="hidden" name="client_id" x-model="clientId">
            <input type="hidden" name="quote_id" x-model="quoteId">
            <input type="hidden" name="return_to" x-model="returnTo">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Monto ($)</label>
                <input type="text" name="amount" x-model="amount" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       placeholder="0,00" inputmode="decimal">
                <button type="button" x-show="clientBalance > 0"
                        @click="amount = formatNumber(clientBalance)"
                        class="text-xs text-green-600 hover:text-green-800 mt-1 underline">
                    Pagar total: $<span x-text="formatNumber(clientBalance)"></span>
                </button>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Método</label>
                <div class="flex gap-2">
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" name="payment_method" value="efectivo" checked class="peer hidden">
                        <div class="text-center py-2 px-3 rounded-lg border border-gray-300 peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-700 text-sm font-medium transition-all">💵 Efectivo</div>
                    </label>
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" name="payment_method" value="transferencia" class="peer hidden">
                        <div class="text-center py-2 px-3 rounded-lg border border-gray-300 peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-700 text-sm font-medium transition-all">🏦 Transfer.</div>
                    </label>
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" name="payment_method" value="otro" class="peer hidden">
                        <div class="text-center py-2 px-3 rounded-lg border border-gray-300 peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-700 text-sm font-medium transition-all">📋 Otro</div>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Referencia (opcional)</label>
                <input type="text" name="payment_reference" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Nro. transferencia, recibo, etc.">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                <input type="date" name="transaction_date" value="<?= e(date('Y-m-d')) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notas (opcional)</label>
                <input type="text" name="notes" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Ej: pago parcial, adelanto...">
            </div>

            <div class="flex gap-3 pt-1">
                <button type="button" @click="open = false" class="flex-1 py-2 px-4 border border-gray-300 rounded-lg text-gray-700 text-sm font-medium hover:bg-gray-50">Cancelar</button>
                <button type="submit" class="flex-1 py-2 px-4 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">Registrar pago</button>
            </div>
        </form>
    </div>
</div>

<script>
function pagoRapidoModal() {
    return {
        open: false,
        clientId: '',
        clientName: '',
        clientBalance: 0,
        quoteId: '',
        amount: '',
        returnTo: window.location.pathname + window.location.search,
        openForClient(id, name, balance) {
            this.clientId = id || '';
            this.clientName = name || '';
            this.clientBalance = parseFloat(balance) || 0;
            this.quoteId = '';
            this.amount = '';
            this.returnTo = window.location.pathname + window.location.search;
            this.open = true;
        },
        openForQuote(clientId, clientName, clientBalance, quoteId, quoteTotal) {
            this.clientId = clientId || '';
            this.clientName = clientName || '';
            this.clientBalance = parseFloat(clientBalance) || 0;
            this.quoteId = quoteId || '';
            this.amount = this.formatNumber(parseFloat(quoteTotal) || 0);
            this.returnTo = window.location.pathname + window.location.search;
            this.open = true;
        },
        formatNumber(n) {
            return Number(n || 0).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
    };
}
</script>
