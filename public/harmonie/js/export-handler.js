/**
 * Export du calendrier en PDF/Excel
 */
(function () {
    'use strict';

    function getCurrentMonthYear() {
        var url = new URL(window.location.href);
        return {
            year: parseInt(url.searchParams.get('year')) || new Date().getFullYear(),
            month: parseInt(url.searchParams.get('month')) || new Date().getMonth() + 1
        };
    }

    function exportCalendar(format) {
        var params = getCurrentMonthYear();
        var form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        var routes = {
            'pdf': '/evenement/export/pdf',
            'excel': '/evenement/export/excel'
        };

        form.action = routes[format] || routes.pdf;

        form.innerHTML = '<input type="hidden" name="year" value="' + params.year + '">';
        form.innerHTML += '<input type="hidden" name="month" value="' + params.month + '">';

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    function setupExportButtons() {
        document.addEventListener('click', function (e) {
            var pdfBtn = e.target.closest('.js-export-pdf');
            var excelBtn = e.target.closest('.js-export-excel');

            if (pdfBtn) {
                e.preventDefault();
                exportCalendar('pdf');
            }

            if (excelBtn) {
                e.preventDefault();
                exportCalendar('excel');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupExportButtons);
    } else {
        setupExportButtons();
    }
})();

/**
 * Export du tableau Kanban en PDF/Excel
 */
(function () {
    'use strict';

    function exportKanban(format) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        var routes = {
            'pdf': '/tache/export/pdf',
            'excel': '/tache/export/excel'
        };

        form.action = routes[format] || routes.pdf;

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    function setupKanbanExportButtons() {
        document.addEventListener('click', function (e) {
            var pdfBtn = e.target.closest('.js-kanban-export-pdf');
            var excelBtn = e.target.closest('.js-kanban-export-excel');

            if (pdfBtn) {
                e.preventDefault();
                exportKanban('pdf');
            }

            if (excelBtn) {
                e.preventDefault();
                exportKanban('excel');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupKanbanExportButtons);
    } else {
        setupKanbanExportButtons();
    }
})();
