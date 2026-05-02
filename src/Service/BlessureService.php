<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * BlessureService
 * ---------------
 * Interroge l'API Groq (LLaMA-3) pour obtenir des conseils de récupération
 * personnalisés en fonction de l'articulation blessée et du type d'activité.
 *
 * Dépendances injectées :
 *  - HttpClientInterface  (Symfony HTTP client, autowired)
 *  - $groqApiKey          (env GROQ_API_KEY)
 *  - $groqChatUrl         (env GROQ_CHAT_URL)
 *  - $groqChatModel       (env GROQ_CHAT_MODEL)
 */
class BlessureService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $groqApiKey,
        private readonly string $groqChatUrl,
        private readonly string $groqChatModel,
    ) {}

    /**
     * Retourne un tableau structuré de conseils pour une articulation donnée.
     *
     * @param string $articulation  ex. "genou_gauche", "epaule_droite"
     * @param string $typeActivite  ex. "musculation", "cardio", "yoga"
     * @param string $intensite     "légère" | "modérée" | "intense"
     * @param int    $userId        Identifiant de l'utilisateur (pour logging)
     *
     * @return array{
     *   articulation: string,
     *   label: string,
     *   traitements: array<string>,
     *   exercices_recuperation: array<string>,
     *   conseils_nutrition: array<string>,
     *   quand_consulter: string,
     *   duree_recuperation: string,
     *   raw: string
     * }
     */
    public function getRecoveryAdvice(
        string $articulation,
        string $typeActivite = 'général',
        string $intensite    = 'modérée',
        int    $userId       = 0
    ): array {
        $label  = $this->getLabel($articulation);
        $prompt = $this->buildPrompt($label, $typeActivite, $intensite);

        try {
            $response = $this->httpClient->request('POST', $this->groqChatUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => $this->groqChatModel,
                    'temperature' => 0.4,
                    'max_tokens'  => 900,
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => $this->getSystemPrompt(),
                        ],
                        [
                            'role'    => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ],
            ]);

            $data    = $response->toArray();
            $content = $data['choices'][0]['message']['content'] ?? '';

            return $this->parseResponse($articulation, $label, $content);

        } catch (\Throwable $e) {
            // Fallback gracieux si l'API est indisponible
            return $this->getFallbackAdvice($articulation, $label, $e->getMessage());
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // PRIVÉ
    // ────────────────────────────────────────────────────────────────────────

    private function getSystemPrompt(): string
    {
        return <<<SYSTEM
        Tu es un kinésithérapeute du sport expert et un médecin du sport certifié.
        Tu réponds TOUJOURS en français, de manière claire, pratique et bienveillante.
        Tu fournis des conseils fondés sur des preuves médicales pour aider les sportifs
        à récupérer de leurs blessures sportives courantes.
        
        FORMAT DE RÉPONSE OBLIGATOIRE (JSON strict, sans markdown autour) :
        {
          "traitements": ["traitement1", "traitement2", "traitement3"],
          "exercices_recuperation": ["exercice1", "exercice2", "exercice3"],
          "conseils_nutrition": ["conseil1", "conseil2"],
          "quand_consulter": "Description courte des signaux d'alarme",
          "duree_recuperation": "Estimation de la durée de récupération"
        }
        SYSTEM;
    }

    private function buildPrompt(string $label, string $typeActivite, string $intensite): string
    {
        return sprintf(
            'Donne-moi des conseils de récupération détaillés pour une blessure/douleur au(x) %s '
            . 'survenu(e) lors d\'une séance de %s avec une intensité %s. '
            . 'Inclus : traitements immédiats (RICE/POLICE), exercices de récupération progressifs, '
            . 'conseils nutritionnels anti-inflammatoires, quand consulter un médecin, '
            . 'et une estimation de la durée de récupération. '
            . 'Réponds UNIQUEMENT avec le JSON demandé.',
            $label,
            $typeActivite,
            $intensite
        );
    }

    /**
     * Parse la réponse JSON de Groq.
     */
    private function parseResponse(string $articulation, string $label, string $content): array
    {
        // Nettoyage éventuel de markdown ```json ... ```
        $clean = preg_replace('/```(?:json)?\s*([\s\S]*?)```/', '$1', $content);
        $clean = trim($clean ?? $content);

        $parsed = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
            // Fallback : on découpe le texte brut en sections
            return $this->parseFallbackText($articulation, $label, $content);
        }

        return [
            'articulation'           => $articulation,
            'label'                  => $label,
            'traitements'            => $parsed['traitements']            ?? [],
            'exercices_recuperation' => $parsed['exercices_recuperation'] ?? [],
            'conseils_nutrition'     => $parsed['conseils_nutrition']     ?? [],
            'quand_consulter'        => $parsed['quand_consulter']        ?? '',
            'duree_recuperation'     => $parsed['duree_recuperation']     ?? '',
            'raw'                    => $content,
        ];
    }

    private function parseFallbackText(string $articulation, string $label, string $content): array
    {
        return [
            'articulation'           => $articulation,
            'label'                  => $label,
            'traitements'            => ['Repos immédiat', 'Application de glace 15-20 min', 'Compression légère', 'Élévation du membre'],
            'exercices_recuperation' => ['Mobilisation douce après 48h', 'Étirements passifs progressifs', 'Renforcement isométrique léger'],
            'conseils_nutrition'     => ['Alimentation riche en oméga-3', 'Hydratation suffisante (2L/jour)'],
            'quand_consulter'        => 'Douleur intense, gonflement important, impossibilité de bouger l\'articulation.',
            'duree_recuperation'     => '3 à 10 jours selon la gravité',
            'raw'                    => $content,
        ];
    }

    private function getFallbackAdvice(string $articulation, string $label, string $errorMsg): array
    {
        return [
            'articulation'           => $articulation,
            'label'                  => $label,
            'traitements'            => [
                'Méthode RICE : Repos, Glace (15-20 min), Compression, Élévation',
                'Anti-inflammatoires locaux (gel ibuprofène) si pas de contre-indication',
                'Éviter toute activité douloureuse pendant 48-72h',
            ],
            'exercices_recuperation' => [
                'Mobilisation douce sans douleur après 48h',
                'Exercices de proprioception en phase de récupération',
                'Reprise progressive de l\'activité après disparition de la douleur',
            ],
            'conseils_nutrition'     => [
                'Augmenter les aliments riches en oméga-3 (saumon, noix)',
                'Consommer des protéines de qualité pour favoriser la cicatrisation',
            ],
            'quand_consulter'        => 'Consultez immédiatement si : douleur insupportable, craquement, gonflement rapide.',
            'duree_recuperation'     => '3 à 14 jours selon la sévérité',
            'raw'                    => 'API indisponible : ' . $errorMsg,
        ];
    }

    /**
     * Mapping articulation_id → label lisible en français.
     */
    private function getLabel(string $articulation): string
    {
        return match ($articulation) {
            'tete'           => 'tête / nuque',
            'cou'            => 'cou / cervicales',
            'epaule_gauche'  => 'épaule gauche',
            'epaule_droite'  => 'épaule droite',
            'coude_gauche'   => 'coude gauche',
            'coude_droit'    => 'coude droit',
            'poignet_gauche' => 'poignet gauche',
            'poignet_droit'  => 'poignet droit',
            'dos_haut'       => 'haut du dos / dorsales',
            'dos_bas'        => 'bas du dos / lombaires',
            'thorax'         => 'thorax / côtes',
            'abdomen'        => 'abdomen / core',
            'hanche_gauche'  => 'hanche gauche',
            'hanche_droite'  => 'hanche droite',
            'genou_gauche'   => 'genou gauche',
            'genou_droit'    => 'genou droit',
            'cheville_gauche'=> 'cheville gauche',
            'cheville_droite'=> 'cheville droite',
            'pied_gauche'    => 'pied gauche',
            'pied_droit'     => 'pied droit',
            default          => str_replace('_', ' ', $articulation),
        };
    }
}