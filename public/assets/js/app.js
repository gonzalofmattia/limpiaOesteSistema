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
    window.rebuildLucideIcons = function () {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    };

    window.renderLoChart = function (chartId, canvas, config) {
        if (!window.Chart || !canvas) return null;
        window.__loCharts = window.__loCharts || {};
        if (window.__loCharts[chartId]) {
            window.__loCharts[chartId].destroy();
        }
        window.__loCharts[chartId] = new window.Chart(canvas, config);
        return window.__loCharts[chartId];
    };

    window.rebuildLucideIcons();

});
