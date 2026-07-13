<div class="space-y-6 max-w-3xl">
    <div class="lo-card p-5">
        <p class="text-sm text-slate-600">
            Esta página se puede abrir desde cualquier lado (celular, notebook, en tu casa o en la calle) con solo entrar a
            <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">/prospeccion/instrucciones</code>. Todo el panel
            (prospectos, plantillas, campañas, bandeja) es una web común: lo administrás desde donde quieras, sin instalar nada.
        </p>
        <p class="text-sm text-slate-600 mt-2">
            <strong>Lo único que NO funciona "desde cualquier lado" es el envío en sí.</strong> Los mensajes de WhatsApp los manda
            un programa (el <em>worker</em>) que tiene que estar corriendo en tu computadora, con Chrome abierto y WhatsApp Web
            logueado. Si esa PC está apagada o el programa no está corriendo, podés seguir armando campañas y viendo respuestas
            desde el celular sin problema, pero no se va a mandar ni recibir nada hasta que la prendas y lo corras de nuevo.
        </p>
    </div>

    <section class="lo-card p-5">
        <h2 class="text-sm font-semibold text-slate-800 mb-2">El embudo (estados de un prospecto)</h2>
        <div class="flex flex-wrap items-center gap-1.5 mb-2">
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">Nuevo</span>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 text-slate-300"></i>
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">Contactado</span>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 text-slate-300"></i>
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Respondió</span>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 text-slate-300"></i>
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-900">Interesado</span>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 text-slate-300"></i>
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">Visita agendada</span>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 text-slate-300"></i>
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">Cotizado</span>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 text-slate-300"></i>
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Cliente</span>
        </div>
        <div class="flex flex-wrap gap-1.5">
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">No interesado</span>
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-600">Sin respuesta</span>
        </div>
        <p class="text-xs text-slate-500 mt-2">Los dos de abajo son salidas del embudo: a "No interesado" se llega a mano o solo por opt-out (si el prospecto contesta algo como "no me interesa"); a "Sin respuesta" llega el sistema solo cuando se agotan los seguimientos y recontactos automáticos.</p>
    </section>

    <section class="lo-card p-5">
        <h2 class="text-sm font-semibold text-slate-800 mb-2">1. Plantillas primero</h2>
        <p class="text-sm text-slate-600">
            Andá a <a href="<?= e(url('/prospeccion/plantillas')) ?>" class="text-lo-blue hover:underline">Plantillas</a> →
            <strong>Nueva plantilla</strong>. Cada una tiene un rubro (o "Todos" como comodín) y una etapa:
            <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">primer_contacto</code>,
            <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">seguimiento_7d</code> o
            <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">recontacto</code>. Variables disponibles:
            <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">{{nombre}}</code> y
            <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">{{ciudad}}</code>, con vista previa en vivo mientras escribís.
        </p>
        <p class="text-sm text-slate-600 mt-2">Con una plantilla de <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">primer_contacto</code> ya podés arrancar. Las de seguimiento y recontacto sumalas cuando quieras — sin ellas esos dos pasos automáticos simplemente no tienen qué mandar, no rompen nada.</p>
    </section>

    <section class="lo-card p-5">
        <h2 class="text-sm font-semibold text-slate-800 mb-2">2. Importar tu lista</h2>
        <p class="text-sm text-slate-600 mb-2">Se sube en <a href="<?= e(url('/prospeccion/importar')) ?>" class="text-lo-blue hover:underline">Importar</a>. Solo acepta <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">.xlsx</code>, con estas columnas (no importa mayúsculas ni tildes en el encabezado):</p>
        <div class="lo-table-wrap">
            <table class="min-w-full text-sm lo-table">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                    <tr><th class="text-left px-3 py-2">Columna</th><th class="text-left px-3 py-2">Obligatoria</th><th class="text-left px-3 py-2">Notas</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr><td class="px-3 py-2"><code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">nombre</code></td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Nombre del negocio</td></tr>
                    <tr><td class="px-3 py-2"><code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">telefono</code></td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Cualquier formato argentino, se normaliza solo</td></tr>
                    <tr><td class="px-3 py-2"><code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">rubro</code></td><td class="px-3 py-2">No</td><td class="px-3 py-2">parrilla, panaderia, restaurante, bar, hotel, clinica, escuela, revendedor — si no matchea ninguno, queda "otro"</td></tr>
                    <tr><td class="px-3 py-2"><code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">ciudad</code></td><td class="px-3 py-2">No</td><td class="px-3 py-2">Texto libre</td></tr>
                    <tr><td class="px-3 py-2"><code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">fuente</code></td><td class="px-3 py-2">No</td><td class="px-3 py-2">De dónde salió el contacto</td></tr>
                </tbody>
            </table>
        </div>
        <p class="text-sm text-slate-600 mt-2">Al terminar te muestra un resumen: importados, duplicados, ya son clientes (mismo teléfono que un cliente existente, no se importan) e inválidos con el motivo.</p>
        <div class="mt-3 rounded-xl border border-red-100 bg-red-50 p-3 text-sm text-red-800">
            <strong>El Excel no trae el estado.</strong> Todo lo que importás entra como "Nuevo", aunque en tu lista ya tuvieras marcado quién fue contactado. Si ya le escribiste a algún negocio por fuera del sistema, entrá a su ficha después de importar y cambiale el estado a mano — si no, una campaña puede volver a mandarle el primer contacto.
        </div>
    </section>

    <section class="lo-card p-5">
        <h2 class="text-sm font-semibold text-slate-800 mb-2">3. Armar y activar una campaña</h2>
        <p class="text-sm text-slate-600">
            En <a href="<?= e(url('/prospeccion/campanas/crear')) ?>" class="text-lo-blue hover:underline">Nueva campaña</a> elegís
            plantilla + filtro (rubro / ciudad / estado del prospecto, "Nuevo" por defecto) + tope diario propio. Se crea en
            <strong>Borrador</strong>.
        </p>
        <p class="text-sm text-slate-600 mt-2">
            La ficha de la campaña te muestra el <strong>dry-run</strong> antes de mandar nada: cuántos prospectos matchean, los
            primeros 20 mensajes ya armados con nombre y ciudad reales, y cuántos días tardaría al ritmo del tope diario. Recién
            ahí aparece el botón <strong>Activar campaña</strong>.
        </p>
        <p class="text-sm text-slate-600 mt-2">Una vez activa, la cola no se llena toda de una — se arma día a día, para poder repartir el cupo diario entre varias campañas activas a la vez.</p>
    </section>

    <section class="lo-card p-5">
        <h2 class="text-sm font-semibold text-slate-800 mb-2">4. El worker manda solo (esto sí necesita tu PC prendida)</h2>
        <p class="text-sm text-slate-600">
            Con el worker corriendo (<code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">python whatsapp_worker.py</code> en la carpeta <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">worker/</code>), pregunta cada tanto si hay mensajes para mandar, respetando lo que configures en
            <a href="<?= e(url('/settings')) ?>" class="text-lo-blue hover:underline">Configuración → Prospección / Envíos</a>: tope diario, ventana horaria, si manda fines de semana, y el delay entre mensajes.
        </p>
        <p class="text-sm text-slate-600 mt-2">
            En el <a href="<?= e(url('/prospeccion')) ?>" class="text-lo-blue hover:underline">Dashboard</a> el card del worker te dice si está vivo (verde, último aviso hace menos de 10 minutos) y cuánto lleva mandado hoy. El botón <strong>Pausar todo</strong> corta los envíos nuevos al toque desde cualquier lado, sin tener que tocar la PC.
        </p>
    </section>

    <section class="lo-card p-5">
        <h2 class="text-sm font-semibold text-slate-800 mb-2">5. Cuando te contestan</h2>
        <p class="text-sm text-slate-600">
            El worker también lee los chats sin leer de WhatsApp Web y avisa al sistema solo. Si el número matchea un prospecto,
            pasa a <strong>Respondió</strong> automáticamente y se cancela cualquier mensaje que le quedara pendiente en cola. Si
            el mensaje tiene una frase tipo "no me interesa" o "baja", pasa directo a <strong>No interesado</strong> sin que
            hagas nada.
        </p>
        <p class="text-sm text-slate-600 mt-2">
            Esas respuestas aparecen en <a href="<?= e(url('/prospeccion/bandeja')) ?>" class="text-lo-blue hover:underline">Bandeja</a>
            con el hilo completo y una sugerencia de respuesta armada por IA (interesado, pregunta de precio, quiere reagendar,
            rechazo, etc.). La editás si querés, tocás <strong>Copiar y abrir WhatsApp</strong> para mandarla vos —
            <strong>el sistema nunca contesta por su cuenta</strong> — y después <strong>Marcar respondido por mí</strong>.
        </p>
    </section>

    <section class="lo-card p-5">
        <h2 class="text-sm font-semibold text-slate-800 mb-2">6. Lo que pasa sin que toques nada</h2>
        <ul class="text-sm text-slate-600 list-disc pl-5 space-y-1.5">
            <li><strong>Seguimiento:</strong> si a los 7 días (configurable) del primer contacto nadie contestó, se manda solo el mensaje de etapa <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">seguimiento_7d</code>. Si tampoco hay respuesta, pasa a <strong>Sin respuesta</strong>.</li>
            <li><strong>Recontacto:</strong> a los que respondieron o se mostraron interesados pero se enfriaron (45 días sin novedades), les llega un <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">recontacto</code> — hasta 2 veces. Agotado eso, también pasan a <strong>Sin respuesta</strong>.</li>
        </ul>
        <p class="text-sm text-slate-600 mt-2">Prioridad cuando hay varias cosas para mandar el mismo día: recontactos primero, después seguimientos, y por último los primeros contactos de campañas activas.</p>
    </section>

    <section class="lo-card p-5">
        <h2 class="text-sm font-semibold text-slate-800 mb-2">Mapa de rutas</h2>
        <div class="lo-table-wrap">
            <table class="min-w-full text-sm lo-table">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-600">
                    <tr><th class="text-left px-3 py-2">Ruta</th><th class="text-left px-3 py-2">Para qué</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr><td class="px-3 py-2"><code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">/prospeccion</code></td><td class="px-3 py-2">Dashboard: embudo, worker, pendientes de hoy</td></tr>
                    <tr><td class="px-3 py-2"><code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">/prospeccion/prospectos</code></td><td class="px-3 py-2">Listado completo con filtros</td></tr>
                    <tr><td class="px-3 py-2"><code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">/prospeccion/importar</code></td><td class="px-3 py-2">Subir el Excel</td></tr>
                    <tr><td class="px-3 py-2"><code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">/prospeccion/plantillas</code></td><td class="px-3 py-2">Mensajes por rubro y etapa</td></tr>
                    <tr><td class="px-3 py-2"><code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">/prospeccion/campanas</code></td><td class="px-3 py-2">Crear, revisar dry-run, activar/pausar</td></tr>
                    <tr><td class="px-3 py-2"><code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">/prospeccion/cola</code></td><td class="px-3 py-2">Qué se mandó, falló o está pendiente hoy</td></tr>
                    <tr><td class="px-3 py-2"><code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">/prospeccion/bandeja</code></td><td class="px-3 py-2">Respuestas por atender, con sugerencia de IA</td></tr>
                    <tr><td class="px-3 py-2"><code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">/settings</code></td><td class="px-3 py-2">Tope diario, horario, delays (sección "Prospección / Envíos")</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>
