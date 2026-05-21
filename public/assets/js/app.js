/**
 * JS compartido — LIMPIA OESTE ABM
 * La mayoría de la interactividad usa Alpine.js en las vistas.
 */

/**
 * Filtros de listados persistidos en localStorage (prefijo lo_filters_).
 */
window.TableFilters = {
    save: function (page, filters) {
        try {
            localStorage.setItem('lo_filters_' + page, JSON.stringify(filters || {}));
        } catch (e) { /* ignore */ }
    },
    load: function (page) {
        try {
            var saved = localStorage.getItem('lo_filters_' + page);
            return saved ? JSON.parse(saved) : null;
        } catch (e) {
            return null;
        }
    },
    clear: function (page) {
        try {
            localStorage.removeItem('lo_filters_' + page);
        } catch (e) { /* ignore */ }
    },
};

/**
 * Serializa query string con claves ordenadas (comparar URLs de forma estable).
 */
function loFilterCanonicalQuery(sp) {
    var buckets = {};
    sp.forEach(function (v, k) {
        if (!buckets[k]) buckets[k] = [];
        buckets[k].push(v);
    });
    return Object.keys(buckets).sort().map(function (k) {
        return buckets[k].sort().map(function (v) {
            return encodeURIComponent(k) + '=' + encodeURIComponent(v);
        }).join('&');
    }).join('&');
}

function loFilterPickFromSearchParams(searchParams, keys) {
    var o = {};
    keys.forEach(function (k) {
        if (!searchParams.has(k)) return;
        var v = searchParams.get(k);
        if (v === null || v === '') return;
        o[k] = v;
    });
    return o;
}

function loFilterMergeSavedIntoUrl(currentSearch, saved, keys) {
    if (!saved || typeof saved !== 'object') return null;
    var p = new URLSearchParams(currentSearch);
    var changed = false;
    keys.forEach(function (k) {
        if (!Object.prototype.hasOwnProperty.call(saved, k)) return;
        var sv = saved[k];
        if (sv === null || sv === undefined || sv === '') return;
        if (!p.has(k) || p.get(k) === '') {
            p.set(k, String(sv));
            changed = true;
        }
    });
    if (!changed) return null;
    keys.forEach(function (k) {
        if (p.has(k) && p.get(k) === '') p.delete(k);
    });
    if (keys.indexOf('with_debt') !== -1 || keys.indexOf('with_favor') !== -1) {
        if (p.get('with_debt') === '1') p.delete('with_favor');
        if (p.get('with_favor') === '1') p.delete('with_debt');
    }
    return p;
}

function loFilterInitPersistRoot(root) {
    var TF = window.TableFilters;
    if (!TF || !root) return;
    var pageKey = root.getAttribute('data-lo-filter-page');
    var keysStr = root.getAttribute('data-lo-filter-keys') || '';
    var listPath = root.getAttribute('data-lo-filter-list-path') || '';
    var clearUrl = root.getAttribute('data-lo-filter-clear-url') || '';
    if (!pageKey || !keysStr || !listPath) return;
    var keys = keysStr.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
    if (keys.length === 0) return;

    var pathNow = window.location.pathname.replace(/\/$/, '') || '/';
    var listNorm = listPath.replace(/\/$/, '') || '/';
    if (pathNow !== listNorm) return;

    var saved = TF.load(pageKey);
    var merged = loFilterMergeSavedIntoUrl(window.location.search, saved, keys);
    if (merged) {
        var qs = loFilterCanonicalQuery(merged);
        var cur = loFilterCanonicalQuery(new URLSearchParams(window.location.search));
        if (qs !== cur) {
            window.location.replace(listPath + (qs ? '?' + qs : ''));
            return;
        }
    }
    TF.save(pageKey, loFilterPickFromSearchParams(new URLSearchParams(window.location.search), keys));

    root.querySelectorAll('[data-lo-filter-clear]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            TF.clear(pageKey);
            window.location.href = clearUrl || listPath;
        });
    });
}

function loFilterBindPersistHandlers() {
    var TF = window.TableFilters;
    if (!TF) return;

    document.addEventListener('click', function (ev) {
        var a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
        if (!a) return;
        var root = a.closest('[data-lo-filter-persist]');
        if (!root) return;
        var pageKey = root.getAttribute('data-lo-filter-page');
        var keysStr = root.getAttribute('data-lo-filter-keys') || '';
        var listPath = root.getAttribute('data-lo-filter-list-path') || '';
        if (!pageKey || !keysStr || !listPath) return;
        var keys = keysStr.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        var href = a.getAttribute('href');
        if (!href || href.indexOf('#') === 0) return;
        var u;
        try {
            u = new URL(href, window.location.href);
        } catch (e) {
            return;
        }
        if (u.origin !== window.location.origin) return;
        var aPath = u.pathname.replace(/\/$/, '') || '/';
        var listNorm = listPath.replace(/\/$/, '') || '/';
        if (aPath !== listNorm) return;
        TF.save(pageKey, loFilterPickFromSearchParams(u.searchParams, keys));
    }, true);

    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form || form.nodeName !== 'FORM' || !form.method) return;
        if (String(form.method).toLowerCase() !== 'get') return;
        var root = form.closest('[data-lo-filter-persist]');
        if (!root) return;
        var pageKey = root.getAttribute('data-lo-filter-page');
        var keysStr = root.getAttribute('data-lo-filter-keys') || '';
        if (!pageKey || !keysStr) return;
        var keys = keysStr.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        var fd;
        try {
            fd = new FormData(form);
        } catch (e) {
            return;
        }
        var sp = new URLSearchParams();
        fd.forEach(function (val, key) {
            sp.append(key, typeof val === 'string' ? val : String(val));
        });
        TF.save(pageKey, loFilterPickFromSearchParams(sp, keys));
    }, true);
}

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

/** URL pública HTTPS de imagen de producto (/producto-imagen/{id}/{filename}). */
window.productImageUrl = function (productId, filename) {
    var b = (typeof window.PRODUCT_IMAGE_PUBLIC_BASE !== 'undefined' && window.PRODUCT_IMAGE_PUBLIC_BASE)
        ? String(window.PRODUCT_IMAGE_PUBLIC_BASE).replace(/\/$/, '')
        : 'https://limpiaoeste.com.ar/sistema/public';
    return b + '/producto-imagen/' + productId + '/' + encodeURIComponent(filename);
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

    if (!window.__loFilterPersistBound) {
        window.__loFilterPersistBound = true;
        loFilterBindPersistHandlers();
    }
    document.querySelectorAll('[data-lo-filter-persist]').forEach(function (root) {
        loFilterInitPersistRoot(root);
    });

});
