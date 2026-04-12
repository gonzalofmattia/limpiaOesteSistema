/**
 * JS compartido — LIMPIA OESTE ABM
 * La mayoría de la interactividad usa Alpine.js en las vistas.
 */

/** Ruta absoluta al sitio (respeta subcarpeta, ej. /limpiaoestesistema/public). */
window.appUrl = function (path) {
    var b = (typeof window.APP_BASE_URL !== 'undefined' && window.APP_BASE_URL)
        ? String(window.APP_BASE_URL).replace(/\/$/, '')
        : '';
    path = String(path || '/').replace(/^\//, '');
    if (!b) {
        return '/' + path;
    }
    return b + '/' + path;
};

document.addEventListener('DOMContentLoaded', function () {
    // Reservado para extensiones futuras
});
