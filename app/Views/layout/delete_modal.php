<div x-cloak x-show="deleteModal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60" x-transition:enter="transition-opacity duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @click="deleteModal.open = false"></div>
    <div class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-2 scale-95">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Confirmar eliminación</h3>
        <p class="text-sm text-gray-600 mb-6">
            ¿Estás seguro de que querés eliminar <span class="font-medium" x-text="deleteModal.itemName"></span>?
        </p>
        <div class="flex justify-end gap-2">
            <button type="button" @click="deleteModal.open = false" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
            <button
                type="button"
                class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700"
                @click="
                    const form = document.getElementById(deleteModal.formId);
                    if (form) { form.submit(); }
                    deleteModal.open = false;
                "
            >Sí, eliminar</button>
        </div>
    </div>
</div>
