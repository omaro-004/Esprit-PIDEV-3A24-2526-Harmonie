/**
 * Jours fériés Tunisie — API Nager.Date + cache sessionStorage + fallback statique.
 */
(function () {
    var API_TMPL = 'https://date.nager.at/api/v3/PublicHolidays/{year}/TN';

    var FALLBACK = [
        { m: 1, d: 1, name: "Jour de l'An" },
        { m: 3, d: 20, name: "Fête de l'Indépendance" },
        { m: 4, d: 9, name: 'Journée des Martyrs' },
        { m: 5, d: 1, name: 'Fête du Travail' },
        { m: 7, d: 25, name: 'Fête de la République' },
        { m: 8, d: 13, name: 'Fête de la Femme' },
        { m: 10, d: 15, name: "Fête de l'Évacuation" },
        { m: 11, d: 7, name: 'Fête de la Nouvelle Ère' },
    ];

    function pad(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function fallbackMap(year) {
        var o = {};
        FALLBACK.forEach(function (row) {
            var iso = year + '-' + pad(row.m) + '-' + pad(row.d);
            o[iso] = { date: iso, localName: row.name, name: row.name };
        });
        return o;
    }

    function mergeApiInto(map, rows) {
        if (!Array.isArray(rows)) return;
        rows.forEach(function (h) {
            if (!h || !h.date) return;
            map[h.date] = {
                date: h.date,
                localName: h.localName || h.name || 'Jour férié',
                name: h.name || h.localName || 'Jour férié',
            };
        });
    }

    function cacheKey(year) {
        return 'nager_holidays_TN_' + year;
    }

    function loadFromCache(year) {
        try {
            var raw = sessionStorage.getItem(cacheKey(year));
            if (!raw) return null;
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function saveCache(year, obj) {
        try {
            sessionStorage.setItem(cacheKey(year), JSON.stringify(obj));
        } catch (e) {
            /* ignore */
        }
    }

    function fetchYear(year, cb) {
        var cached = loadFromCache(year);
        if (cached && typeof cached === 'object') {
            cb(cached);
            return;
        }

        var map = fallbackMap(year);
        var url = API_TMPL.replace('{year}', String(year));

        fetch(url, { credentials: 'omit' })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP');
                return r.json();
            })
            .then(function (data) {
                mergeApiInto(map, data);
                saveCache(year, map);
                cb(map);
            })
            .catch(function () {
                cb(map);
            });
    }

    function shortName(full) {
        if (!full) return '';
        var max = 22;
        if (full.length <= max) return full;
        return full.slice(0, max - 1) + '…';
    }

    function applyToGrid(root, map) {
        if (!root) return;
        root.querySelectorAll('.cal-cell[data-iso-date]').forEach(function (cell) {
            var iso = cell.getAttribute('data-iso-date');
            if (!iso || !map[iso]) return;

            cell.classList.add('cal-cell--holiday');
            var full = map[iso].localName || map[iso].name || '';
            cell.setAttribute('title', full);

            if (cell.querySelector('.cal-holiday-name')) return;

            var dayNum = cell.querySelector('.cal-day-num');
            var line = document.createElement('div');
            line.className = 'cal-holiday-name';
            line.textContent = shortName(full);
            line.setAttribute('title', full);
            if (dayNum && dayNum.nextSibling) {
                cell.insertBefore(line, dayNum.nextSibling);
            } else if (dayNum) {
                dayNum.after(line);
            } else {
                cell.insertBefore(line, cell.firstChild);
            }
        });
    }

    function run() {
        var root = document.getElementById('evenement-calendar-root');
        if (!root) return;
        var y = parseInt(root.getAttribute('data-calendar-year'), 10);
        if (!y || y < 1970) return;

        fetchYear(y, function (map) {
            var grid = document.getElementById('cal-grid-root');
            applyToGrid(grid || root, map);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
