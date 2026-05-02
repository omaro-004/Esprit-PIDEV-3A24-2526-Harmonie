<?php

namespace App\Service;

use App\Entity\Aliment;

class AlimentManager
{
    /**
     * Valide les règles métier d'un Aliment.
     *
     * Règles :
     * 1. Le nom de l'aliment est obligatoire (non vide)
     * 2. Les calories doivent être entre 0 et 9000 kcal/100g
     * 3. Les protéines doivent être entre 0 et 100 g/100g
     * 4. Les glucides doivent être entre 0 et 100 g/100g
     * 5. Les lipides doivent être entre 0 et 100 g/100g
     * 6. La somme des macros ne peut dépasser 100 g/100g
     *
     * @throws \InvalidArgumentException si une règle est violée
     */
    public function validate(Aliment $aliment): bool
    {
        if (empty(trim($aliment->getNomAliment() ?? ''))) {
            throw new \InvalidArgumentException('Le nom de l\'aliment est obligatoire.');
        }

        $cal = $aliment->getCaloriesPour100g();
        if ($cal === null || $cal < 0 || $cal > 9000) {
            throw new \InvalidArgumentException('Les calories doivent être comprises entre 0 et 9000 kcal/100g.');
        }

        $prot = $aliment->getProteines();
        if ($prot === null || $prot < 0 || $prot > 100) {
            throw new \InvalidArgumentException('Les protéines doivent être comprises entre 0 et 100 g/100g.');
        }

        $gluc = $aliment->getGlucides();
        if ($gluc === null || $gluc < 0 || $gluc > 100) {
            throw new \InvalidArgumentException('Les glucides doivent être compris entre 0 et 100 g/100g.');
        }

        $lip = $aliment->getLipides();
        if ($lip === null || $lip < 0 || $lip > 100) {
            throw new \InvalidArgumentException('Les lipides doivent être compris entre 0 et 100 g/100g.');
        }

        if (($prot + $gluc + $lip) > 100) {
            throw new \InvalidArgumentException('La somme des macronutriments ne peut pas dépasser 100 g/100g.');
        }

        return true;
    }
}