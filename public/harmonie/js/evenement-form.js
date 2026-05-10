/**
 * Gestion du formulaire "Nouvel événement"
 * 
 * Logique :
 * 1. Titre, Date début, Date fin, Type → toujours visibles
 * 2. Si Cours ou Réunion → afficher les boutons [Présentiel] [En ligne]
 *    - Si Présentiel → input "Où ?" + détection "esprit" → dropdown salles
 *    - Si En ligne → rien
 * 3. Si Loisir ou Autre → afficher directement input "Où ?" libre
 */

(function () {
    'use strict';

    const TYPES_WITH_LIEU = ['cours', 'reunion'];
    const TYPES_WITH_FREE_LIEU = ['loisir', 'autre'];

    function getEventType(form) {
        const select = form.querySelector('.js-event-type-select');
        return select ? select.value : '';
    }

    function getLieuTypeValue(form) {
        const radio = form.querySelector('.js-lieu-button:checked');
        return radio ? radio.value : '';
    }

    function updateFormVisibility(form) {
        const eventType = getEventType(form);
        const lieuButtonsContainer = form.querySelector('.js-lieu-buttons-container');
        const lieuFreeContainer = form.querySelector('.js-lieu-free-container');
        const lieuPresentielContainer = form.querySelector('.js-lieu-presentiel-container');

        // Masquer tous les conteneurs
        lieuButtonsContainer.style.display = 'none';
        lieuFreeContainer.style.display = 'none';
        lieuPresentielContainer.style.display = 'none';

        // Si Cours ou Réunion → afficher les boutons
        if (TYPES_WITH_LIEU.includes(eventType)) {
            lieuButtonsContainer.style.display = 'block';
            
            // Si un mode a été sélectionné et que c'est Présentiel
            if (getLieuTypeValue(form) === 'presentiel') {
                lieuPresentielContainer.style.display = 'block';
                updateLieuPresentielVisibility(form);
            }
        }
        // Si Loisir ou Autre → afficher l'input libre
        else if (TYPES_WITH_FREE_LIEU.includes(eventType)) {
            lieuFreeContainer.style.display = 'block';
        }
    }

    function updateLieuPresentielVisibility(form) {
        const input = form.querySelector('.js-lieu-adresse[data-toggle-salles="true"]');
        const salleSelectContainer = form.querySelector('.js-salle-select-container');
        const salleSelect = form.querySelector('.js-salle-select');

        if (!input || !salleSelectContainer || !salleSelect) return;

        const value = input.value.toLowerCase();
        const hasEsprit = value.includes('esprit');

        if (hasEsprit && salleSelect.options.length > 0) {
            // Afficher le select salle
            salleSelectContainer.style.display = 'block';
        } else {
            // Masquer le select salle
            salleSelectContainer.style.display = 'none';
            // Réinitialiser le select
            salleSelect.selectedIndex = 0;
        }
    }

    function syncRadioWithHiddenField(form) {
        const eventType = getEventType(form);
        const lieuType = form.querySelector('input[name="evenement[lieuType]"]');
        const presentielRadio = form.querySelector('#lieu_presentiel');
        const enlignRadio = form.querySelector('#lieu_enligne');

        // Si on change de type et le radio n'est pas visible, réinitialiser
        if (!TYPES_WITH_LIEU.includes(eventType)) {
            if (lieuType) lieuType.value = '';
            if (presentielRadio) presentielRadio.checked = false;
            if (enlignRadio) enlignRadio.checked = false;
        }
    }

    function bindForm(form) {
        if (form.dataset.evenementFormBound) return;
        form.dataset.evenementFormBound = '1';

        const eventTypeSelect = form.querySelector('.js-event-type-select');
        const lieuButtons = form.querySelectorAll('.js-lieu-button');
        const lieuAdresseInput = form.querySelector('.js-lieu-adresse[data-toggle-salles="true"]');

        // Changement du type d'événement
        if (eventTypeSelect) {
            eventTypeSelect.addEventListener('change', function () {
                syncRadioWithHiddenField(form);
                updateFormVisibility(form);
            });
        }

        // Changement du mode (Présentiel / En ligne)
        lieuButtons.forEach(btn => {
            btn.addEventListener('change', function () {
                updateFormVisibility(form);
            });
        });

        // Saisie dans le champ "Où ?" → détection "esprit"
        if (lieuAdresseInput) {
            lieuAdresseInput.addEventListener('input', function () {
                updateLieuPresentielVisibility(form);
            });
            lieuAdresseInput.addEventListener('change', function () {
                updateLieuPresentielVisibility(form);
            });
        }

        // Initialisation
        updateFormVisibility(form);
    }

    function scan() {
        document.querySelectorAll('form.evenement-form').forEach(form => {
            bindForm(form);
        });
    }

    // Scan au chargement
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }

    // Re-scan après chargement AJAX du formulaire (panel new/edit)
    document.addEventListener('harmony:evenement-form-mounted', function (e) {
        var root = e.detail && e.detail.root;
        if (root) {
            root.querySelectorAll('form.evenement-form').forEach(function (form) {
                bindForm(form);
            });
        } else {
            scan();
        }
    });
})();
