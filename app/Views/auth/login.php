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
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
            <input type="password" name="password" required autocomplete="current-password"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#1a6b3c] focus:border-[#1a6b3c]">
        </div>
        <button type="submit" class="w-full bg-[#1a6b3c] hover:bg-[#2db368] text-white font-medium py-2.5 rounded-lg text-sm transition">
            Ingresar
        </button>
    </form>
</div>
