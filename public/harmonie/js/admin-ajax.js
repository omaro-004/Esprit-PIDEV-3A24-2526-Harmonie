/**
 * Admin : toggle disponibilité salle, accepter / refuser demandes (JSON + CSRF).
 */
(function () {
    function postForm(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body,
            credentials: 'same-origin',
        }).then(function (res) {
            return res.json().catch(function () {
                return { ok: false, error: 'Réponse invalide' };
            });
        });
    }

    function setDemandeResolved(card, statut, label, commentaire) {
        card.style.opacity = '1';
        var badge = card.querySelector('.js-demande-badge');
        if (badge) {
            badge.textContent = label;
            badge.classList.remove('admin-badge--wait', 'admin-badge--pulse');
            if (statut === 'ACCEPTEE') {
                badge.classList.add('admin-badge--ok');
                badge.classList.remove('admin-badge--no');
            } else {
                badge.classList.add('admin-badge--no');
                badge.classList.remove('admin-badge--ok');
            }
        }
        var actions = card.querySelector('.js-demande-actions');
        if (actions) {
            actions.innerHTML = '';
        }
        var note = card.querySelector('.js-demande-admin-note');
        if (note) {
            if (commentaire) {
                note.textContent = commentaire;
                note.hidden = false;
            } else {
                note.textContent = '';
                note.hidden = true;
            }
        }
        card.classList.add('admin-module-card--resolved');
    }

    function initDemandes(root) {
        root.querySelectorAll('[data-admin-demande-card]').forEach(function (card) {
            if (card.dataset.ajaxDemandeBound) return;
            card.dataset.ajaxDemandeBound = '1';

            var acc = card.querySelector('.js-demande-accepter');
            var ref = card.querySelector('.js-demande-refuser');
            var csrf = card.getAttribute('data-csrf') || '';
            var urlAcc = card.getAttribute('data-url-accepter');
            var urlRef = card.getAttribute('data-url-refuser');

            function fadeStart() {
                card.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
                card.style.opacity = '0.65';
            }

            if (acc && urlAcc) {
                acc.addEventListener('click', function () {
                    fadeStart();
                    var fd = new FormData();
                    fd.append('_token', csrf);
                    postForm(urlAcc, fd).then(function (d) {
                        if (!d.ok) {
                            card.style.opacity = '1';
                            window.alert(d.error || 'Action impossible.');
                            return;
                        }
                        setDemandeResolved(card, d.statut, d.label, null);
                    });
                });
            }

            if (ref && urlRef) {
                ref.addEventListener('click', function () {
                    fadeStart();
                    var tx = card.querySelector('.js-demande-refuse-comment');
                    var fd = new FormData();
                    fd.append('_token', csrf);
                    fd.append('commentaire_admin', tx ? tx.value : '');
                    postForm(urlRef, fd).then(function (d) {
                        if (!d.ok) {
                            card.style.opacity = '1';
                            window.alert(d.error || 'Action impossible.');
                            return;
                        }
                        setDemandeResolved(card, d.statut, d.label, d.commentaire || '');
                    });
                });
            }
        });
    }

    function initSalles(root) {
        root.querySelectorAll('.js-salle-toggle').forEach(function (btn) {
            if (btn.dataset.ajaxToggleBound) return;
            btn.dataset.ajaxToggleBound = '1';

            btn.addEventListener('click', function () {
                var url = btn.getAttribute('data-toggle-url');
                var token = btn.getAttribute('data-csrf');
                if (!url || !token) return;

                var fd = new FormData();
                fd.append('_token', token);
                btn.disabled = true;
                postForm(url, fd).then(function (d) {
                    btn.disabled = false;
                    if (!d.ok) {
                        window.alert(d.error || 'Action impossible.');
                        return;
                    }
                    var card = btn.closest('[data-admin-salle-card]');
                    if (card) {
                        card.classList.toggle('admin-module-card--unavailable', !d.disponible);
                    }
                    btn.classList.toggle('is-on', d.disponible);
                    btn.classList.toggle('is-off', !d.disponible);
                    var lab = btn.querySelector('.js-toggle-label');
                    if (lab) {
                        lab.textContent = d.disponible ? 'ON' : 'OFF';
                    }
                });
            });
        });
    }

    function scan(root) {
        root = root || document;
        initDemandes(root);
        initSalles(root);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            scan(document);
        });
    } else {
        scan(document);
    }
})();
