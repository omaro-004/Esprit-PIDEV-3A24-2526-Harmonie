<?php

namespace App\Controller;

use App\Entity\Aliment;
use App\Repository\AlimentRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/nutrition')]
class AdminNutritionController extends AbstractController
{
    public function __construct(
        private readonly AlimentRepository      $repo,
        private readonly EntityManagerInterface $em,
    ) {}

    // ── Page principale avec pagination + filtres ────────────────────
    #[Route('', name: 'admin_nutrition_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // ── Lecture des filtres depuis la requête GET ─────────────────
        $filters = [
            'search'    => $request->query->get('search',    ''),
            'cal_min'   => $request->query->get('cal_min',   ''),
            'cal_max'   => $request->query->get('cal_max',   ''),
            'prot_min'  => $request->query->get('prot_min',  ''),
            'prot_max'  => $request->query->get('prot_max',  ''),
            'id_from'   => $request->query->get('id_from',   ''),
            'id_to'     => $request->query->get('id_to',     ''),
            'sort'      => $request->query->get('orderby',   'nomAliment'),
            'direction' => $request->query->get('orderdir',  'ASC'),
        ];

        // Validation de la direction pour éviter les injections
        if (!in_array(strtoupper($filters['direction']), ['ASC', 'DESC'])) {
            $filters['direction'] = 'ASC';
        }

        // Validation du champ de tri
        $allowedSorts = ['nomAliment', 'caloriesPour100g', 'proteines', 'glucides', 'lipides', 'id'];
        if (!in_array($filters['sort'], $allowedSorts)) {
            $filters['sort'] = 'nomAliment';
        }

        // ── Validation des bornes numériques ─────────────────────────
        foreach (['cal_min', 'cal_max', 'prot_min', 'prot_max', 'id_from', 'id_to'] as $key) {
            $val = $filters[$key];
            if ($val !== '' && (!is_numeric($val) || (float)$val < 0)) {
                $filters[$key] = '';
            }
        }

        // cal_min <= cal_max
        if ($filters['cal_min'] !== '' && $filters['cal_max'] !== ''
            && (int)$filters['cal_min'] > (int)$filters['cal_max']) {
            [$filters['cal_min'], $filters['cal_max']] = [$filters['cal_max'], $filters['cal_min']];
        }

        // prot_min <= prot_max
        if ($filters['prot_min'] !== '' && $filters['prot_max'] !== ''
            && (float)$filters['prot_min'] > (float)$filters['prot_max']) {
            [$filters['prot_min'], $filters['prot_max']] = [$filters['prot_max'], $filters['prot_min']];
        }

        // ── QueryBuilder filtré ───────────────────────────────────────
        $qb = $this->repo->createFilteredQueryBuilder($filters);

        // ── Pagination manuelle (sans KnpPaginator) ──────────────────
        $limit = max(5, min(100, (int)$request->query->get('limit', 20)));
        $page = max(1, (int)$request->query->get('page', 1));

        // Appliquer la pagination manuellement
        $qb->setMaxResults($limit);
        $qb->setFirstResult(($page - 1) * $limit);

        $aliments = $qb->getQuery()->getResult();

        // Créer un objet pagination simple
        $totalItems = $this->repo->countFiltered($filters);
        $totalPages = ceil($totalItems / $limit);

        $pagination = (object) [
            'items' => $aliments,
            'currentPageNumber' => $page,
            'pageCount' => $totalPages,
            'numItemsPerPage' => $limit,
            'totalItemCount' => $totalItems,
        ];

        // ── Stats globales pour les placeholders des filtres ──────────
        $stats = $this->repo->getGlobalStats();

        // ── Nombre total de résultats filtrés ─────────────────────────
        $totalFiltered = $this->repo->countFiltered($filters);

        // ── Indicateur « filtres actifs » ─────────────────────────────
        $hasActiveFilters = array_reduce(
            ['search', 'cal_min', 'cal_max', 'prot_min', 'prot_max', 'id_from', 'id_to'],
            fn(bool $carry, string $key) => $carry || ($filters[$key] !== ''),
            false
        );

        return $this->render('admin/nutrition.html.twig', [
            'pagination'       => $pagination,
            'filters'          => $filters,
            'stats'            => $stats,
            'totalFiltered'    => $totalFiltered,
            'hasActiveFilters' => $hasActiveFilters,
            'limit'            => $limit,
        ]);
    }

    // ── EXPORT EXCEL ─────────────────────────────────────────────────
    /**
     * Génère et télécharge un fichier Excel (.xlsx) contenant
     * tous les aliments de la base de données, avec :
     *   - Le logo Harmony en haut à gauche (A1)
     *   - Un titre centré en ligne 1-2
     *   - Les en-têtes en gras sur fond violet (ligne 3)
     *   - La première ligne d'en-tête figée (freeze pane)
     *   - Les données triées par nom
     *   - Un formatage alterné des lignes pour la lisibilité
     *   - Les largeurs de colonnes ajustées automatiquement
     */
    #[Route('/export', name: 'admin_nutrition_export', methods: ['GET'])]
    public function export(): StreamedResponse
    {
        // ── 1. Récupération de tous les aliments triés par nom ────────
        $aliments = $this->repo->findAllOrdered();

        // ── 2. Création du classeur PhpSpreadsheet ────────────────────
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Catalogue Aliments');

        // ── 3. Métadonnées du classeur ────────────────────────────────
        $spreadsheet->getProperties()
            ->setCreator('Harmony – Plateforme Étudiante')
            ->setLastModifiedBy('Admin Harmony')
            ->setTitle('Catalogue Alimentaire Harmony')
            ->setSubject('Export Nutrition')
            ->setDescription('Base de données des aliments – générée automatiquement par Harmony')
            ->setKeywords('nutrition aliments calories protéines')
            ->setCategory('Nutrition');

        // ── 4. Insertion des lignes de titre (2 lignes réservées + 1 pour en-têtes) ──
        // Ligne 1 : Logo  |  Titre principal
        // Ligne 2 : vide  |  Sous-titre / date
        // Ligne 3 : EN-TÊTES (figée)
        // Ligne 4+ : données

        // ── 5. Logo Harmony (A1) ──────────────────────────────────────
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/image/logo.png';

        if (file_exists($logoPath)) {
            $drawing = new Drawing();
            $drawing->setName('Logo Harmony');
            $drawing->setDescription('Logo Harmony');
            $drawing->setPath($logoPath);
            $drawing->setCoordinates('A1');
            // Hauteur de 2 lignes (≈ 80 px) pour occuper les lignes 1 et 2
            $drawing->setWidth(80);
            $drawing->setHeight(70);
            $drawing->setOffsetX(6);
            $drawing->setOffsetY(4);
            $drawing->setWorksheet($sheet);
        }

        // ── 6. Titre principal (B1 → F1) ─────────────────────────────
        $sheet->mergeCells('B1:F1');
        $sheet->setCellValue('B1', '🥗 Catalogue Alimentaire — Harmony');
        $sheet->getStyle('B1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'size'  => 16,
                'color' => ['rgb' => '4C1D95'],   // violet très foncé
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(38);

        // ── 7. Sous-titre / date (B2 → F2) ───────────────────────────
        $sheet->mergeCells('B2:F2');
        $sheet->setCellValue('B2', 'Exporté le ' . (new \DateTime())->format('d/m/Y à H:i') . '  •  ' . count($aliments) . ' aliment(s)');
        $sheet->getStyle('B2')->applyFromArray([
            'font' => [
                'italic' => true,
                'size'   => 10,
                'color'  => ['rgb' => '6D28D9'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(22);

        // ── 8. En-têtes de colonnes (ligne 3) ────────────────────────
        $headers = [
            'A3' => 'ID',
            'B3' => 'Aliment',
            'C3' => 'Calories (kcal/100g)',
            'D3' => 'Protéines (g/100g)',
            'E3' => 'Lipides (g/100g)',
            'F3' => 'Glucides (g/100g)',
        ];

        foreach ($headers as $cell => $label) {
            $sheet->setCellValue($cell, $label);
        }

        // Style des en-têtes : fond violet, texte blanc, gras
        $headerStyle = [
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 11,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '7C3AED'],  // violet Harmony
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '5B21B6'],
                ],
            ],
        ];
        $sheet->getStyle('A3:F3')->applyFromArray($headerStyle);
        $sheet->getRowDimension(3)->setRowHeight(24);

        // ── 9. Figer la ligne 3 (en-têtes) ───────────────────────────
        // freezePane('A4') → lignes 1-3 figées (logo + titre + en-têtes)
        $sheet->freezePane('A4');

        // ── 10. Données ───────────────────────────────────────────────
        // Styles alternés pour les lignes paires / impaires
        $styleOdd = [
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFFFF'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_HAIR,
                    'color'       => ['rgb' => 'E5E7EB'],
                ],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];
        $styleEven = [
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F5F3FF'],   // violet très pâle
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_HAIR,
                    'color'       => ['rgb' => 'E5E7EB'],
                ],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];

        // Style spécial pour la colonne calories (orange)
        $calStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'D97706']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];

        // Style pour les macros (centré)
        $macroStyle = [
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'numberFormat' => ['formatCode' => '0.0'],
        ];

        // Style pour ID (centré, gris)
        $idStyle = [
            'font'      => ['color' => ['rgb' => '6B7280'], 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];

        $rowIndex = 4;
        foreach ($aliments as $aliment) {
            $isEven = ($rowIndex % 2 === 0);

            $sheet->setCellValue("A{$rowIndex}", $aliment->getId());
            $sheet->setCellValue("B{$rowIndex}", $aliment->getNomAliment());
            $sheet->setCellValue("C{$rowIndex}", $aliment->getCaloriesPour100g());
            $sheet->setCellValue("D{$rowIndex}", $aliment->getProteines());
            $sheet->setCellValue("E{$rowIndex}", $aliment->getLipides());
            $sheet->setCellValue("F{$rowIndex}", $aliment->getGlucides());

            // Fond alterné
            $sheet->getStyle("A{$rowIndex}:F{$rowIndex}")->applyFromArray($isEven ? $styleEven : $styleOdd);

            // Styles spécifiques par colonne
            $sheet->getStyle("A{$rowIndex}")->applyFromArray($idStyle);
            $sheet->getStyle("C{$rowIndex}")->applyFromArray($calStyle);
            $sheet->getStyle("D{$rowIndex}:F{$rowIndex}")->applyFromArray($macroStyle);

            $sheet->getRowDimension($rowIndex)->setRowHeight(20);
            $rowIndex++;
        }

        // ── 11. Largeurs automatiques des colonnes ────────────────────
        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        // Largeur minimale pour la colonne A (logo)
        $sheet->getColumnDimension('A')->setWidth(max(10, 10));

        // ── 12. Bordure extérieure du tableau de données ──────────────
        if ($rowIndex > 4) {
            $lastDataRow = $rowIndex - 1;
            $sheet->getStyle("A3:F{$lastDataRow}")->applyFromArray([
                'borders' => [
                    'outline' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color'       => ['rgb' => '7C3AED'],
                    ],
                ],
            ]);
        }

        // ── 13. Onglet coloré ─────────────────────────────────────────
        $sheet->getTabColor()->setRGB('7C3AED');

        // ── 14. Génération de la réponse HTTP en streaming ────────────
        $filename = 'harmony_catalogue_aliments_' . (new \DateTime())->format('Y-m-d_His') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

        $response = new StreamedResponse(static function () use ($writer): void {
            $writer->save('php://output');
        });

        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="' . $filename . '"'
        );
        $response->headers->set('Cache-Control', 'max-age=0, no-store, no-cache');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    // ── API JSON : liste paginée (pour rechargement AJAX partiel) ────
    #[Route('/api/list', name: 'admin_nutrition_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $filters = [
            'search'    => $request->query->get('search',    ''),
            'cal_min'   => $request->query->get('cal_min',   ''),
            'cal_max'   => $request->query->get('cal_max',   ''),
            'prot_min'  => $request->query->get('prot_min',  ''),
            'prot_max'  => $request->query->get('prot_max',  ''),
            'id_from'   => $request->query->get('id_from',   ''),
            'id_to'     => $request->query->get('id_to',     ''),
            'sort'      => $request->query->get('orderby',   'nomAliment'),
            'direction' => $request->query->get('orderdir',  'ASC'),
        ];

        $aliments = $this->repo->createFilteredQueryBuilder($filters)->getQuery()->getResult();

        return $this->json(array_map([$this, 'serialize'], $aliments));
    }

    // ── CREATE ───────────────────────────────────────────────────────
    #[Route('/api/create', name: 'admin_nutrition_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $error = $this->validateData($data);
        if ($error) {
            return $this->json(['error' => $error], 400);
        }

        $aliment = new Aliment();
        $this->hydrate($aliment, $data);
        $this->em->persist($aliment);
        $this->em->flush();

        return $this->json($this->serialize($aliment), 201);
    }

    // ── UPDATE ───────────────────────────────────────────────────────
    #[Route('/api/update/{id}', name: 'admin_nutrition_update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $aliment = $this->repo->find($id);
        if (!$aliment) {
            return $this->json(['error' => 'Aliment introuvable'], 404);
        }

        $data  = json_decode($request->getContent(), true);
        $error = $this->validateData($data);
        if ($error) {
            return $this->json(['error' => $error], 400);
        }

        $this->hydrate($aliment, $data);
        $this->em->flush();

        return $this->json($this->serialize($aliment));
    }

    // ── DELETE ───────────────────────────────────────────────────────
    #[Route('/api/delete/{id}', name: 'admin_nutrition_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $aliment = $this->repo->find($id);
        if (!$aliment) {
            return $this->json(['error' => 'Aliment introuvable'], 404);
        }
        $this->em->remove($aliment);
        $this->em->flush();

        return $this->json(['deleted' => true, 'id' => $id]);
    }

    // ── HELPERS ─────────────────────────────────────────────────────
    private function hydrate(Aliment $aliment, array $data): void
    {
        $aliment->setNomAliment(trim($data['nomAliment']));
        $aliment->setCaloriesPour100g((int) round((float) $data['calories']));
        $aliment->setProteines((float) $data['proteines']);
        $aliment->setLipides((float) $data['lipides']);
        $aliment->setGlucides((float) $data['glucides']);
    }

    private function serialize(Aliment $a): array
    {
        return [
            'id'        => $a->getId(),
            'nom'       => $a->getNomAliment(),
            'calories'  => $a->getCaloriesPour100g(),
            'proteines' => $a->getProteines(),
            'lipides'   => $a->getLipides(),
            'glucides'  => $a->getGlucides(),
        ];
    }

    private function validateData(?array $data): ?string
    {
        if (!$data) {
            return 'Aucune donnée reçue.';
        }
        if (empty(trim($data['nomAliment'] ?? ''))) {
            return 'Le nom est obligatoire.';
        }
        foreach (['calories', 'proteines', 'lipides', 'glucides'] as $f) {
            $v = $data[$f] ?? null;
            if ($v === null || $v === '' || !is_numeric($v) || (float) $v < 0) {
                return "Valeur invalide pour le champ « $f ».";
            }
        }
        if ((float) $data['calories'] > 9000) {
            return 'Calories trop élevées (max 9000).';
        }
        foreach (['proteines', 'lipides', 'glucides'] as $f) {
            if ((float) $data[$f] > 100) {
                return "Valeur trop élevée pour « $f » (max 100g/100g).";
            }
        }

        return null;
    }
}