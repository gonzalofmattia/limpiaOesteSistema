<div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
    <div class="text-center mb-8">
        <img src="<?= e(url('/assets/img/logoLimpiaOeste.png')) ?>" alt="LIMPIA OESTE" class="h-16 mx-auto mb-4">
        <h1 class="text-2xl font-semibold text-[#1a6b3c]">LIMPIA OESTE</h1>
    </div>
    <form method="post" action="<?= e(url('/login')) ?>" class="space-y-4">
        <?= csrfField() ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Usuario</label>
            <input type="text" name="username" required autocomplete="username"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#1a6b3c] focus:border-[#1a6b3c]">
        </div>
        <div x-data="{ showPw: false }">
            <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
            <div class="relative">
                <input :type="showPw ? 'text' : 'password'" name="password" required autocomplete="current-password"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 text-sm focus:ring-2 focus:ring-[#1a6b3c] focus:border-[#1a6b3c]">
                <button type="button" @click="showPw = !showPw" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600" tabindex="-1" title="Mostrar/ocultar contraseña">
                    <svg x-show="!showPw" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <svg x-show="showPw" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                </button>
            </div>
        </div>
        <button type="submit" class="w-full bg-[#1a6b3c] hover:bg-[#2db368] text-white font-medium py-2.5 rounded-lg text-sm transition">
            Ingresar
        </button>
    </form>
</div>
