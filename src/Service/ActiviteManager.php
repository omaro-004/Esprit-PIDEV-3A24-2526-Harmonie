<?php

namespace App\Service;

use App\Entity\Activite;

class ActiviteManager
{
    /**
     * Valide les règles métier d'une Activité.
     *
     * Règles :
     * 1. L'exercice est obligatoire
     * 2. La durée doit être entre 1 et 300 minutes
     * 3. La date d'activité est obligatoire
     * 4. Les calories brûlées (si renseignées) doivent être positives
     * 5. Le nombre de séries (si renseigné) doit être positif
     *
     * @throws \InvalidArgumentException si une règle est violée
     */
    public function validate(Activite $activite): bool
    {
        if ($activite->getExercice() === null) {
            throw new \InvalidArgumentException('L\'exercice est obligatoire.');
        }

        $duree = $activite->getDureeMinutes();
        if ($duree === null || $duree < 1 || $duree > 300) {
            throw new \InvalidArgumentException('La durée doit être comprise entre 1 et 300 minutes.');
        }

        if ($activite->getDateActivite() === null) {
            throw new \InvalidArgumentException('La date de l\'activité est obligatoire.');
        }

        $calories = $activite->getCaloriesBrulees();
        if ($calories !== null && $calories < 0) {
            throw new \InvalidArgumentException('Les calories brûlées ne peuvent pas être négatives.');
        }

        $series = $activite->getNbSeries();
        if ($series !== null && $series < 1) {
            throw new \InvalidArgumentException('Le nombre de séries doit être supérieur à zéro.');
        }

        return true;
    }
}