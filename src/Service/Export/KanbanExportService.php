<?php

namespace App\Service\Export;

use App\Entity\Tache;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KanbanExportService
{
    /**
     * Génère et retourne un PDF du tableau Kanban
     *
     * @param array<string, Tache[]> $tachesByStatus
     */
    public function exportToPdf(array $tachesByStatus): Response
    {
        $html = $this->generateKanbanHtml($tachesByStatus);

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = sprintf('kanban-%s.pdf', date('Y-m-d'));

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ]
        );
    }

    /**
     * Génère et retourne un Excel du tableau Kanban
     *
     * @param Tache[] $taches
     */
    public function exportToExcel(array $taches): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tâches');

        // En-tête
        $sheet->setCellValue('A1', sprintf('Tableau des tâches - %s', date('d/m/Y')));
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Colonnes
        $headers = ['Nom', 'Statut', 'Deadline', 'Notes', 'Date d\'ajout'];
        $sheet->fromArray($headers, null, 'A3');

        // Style en-tête
        $headerStyle = [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6A5ACD']],
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('A3:E3')->applyFromArray($headerStyle);

        // Données
        $row = 4;
        foreach ($taches as $tache) {
            $sheet->setCellValue('A' . $row, $tache->getNom());
            $sheet->setCellValue('B' . $row, $this->formatStatut($tache->getStatutTache()));
            $sheet->setCellValue('C' . $row, $tache->getDeadline()?->format('d/m/Y') ?? '');
            $sheet->setCellValue('D' . $row, $tache->getNotes() ?? '');

            // Style statut avec couleur
            $statusStyle = $this->getStatusStyle($tache->getStatutTache());
            $sheet->getStyle('B' . $row)->applyFromArray($statusStyle);

            $row++;
        }

        // Largeurs
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(30);
        $sheet->getColumnDimension('E')->setWidth(15);

        // Bordures
        $borderStyle = ['borderStyle' => Border::BORDER_THIN];
        $sheet->getStyle('A3:E' . ($row - 1))->applyFromArray(['borders' => $borderStyle]);

        // Streaming
        $filename = sprintf('kanban-%s.xlsx', date('Y-m-d'));
        return new StreamedResponse(
            function () use ($spreadsheet): void {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            },
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ]
        );
    }

    /**
     * @param array<string, Tache[]> $tachesByStatus
     */
    private function generateKanbanHtml(array $tachesByStatus): string
    {
        $columns = ['A_FAIRE', 'EN_COURS', 'FAIT'];
        $columnNames = ['À faire', 'En cours', 'Fait'];

        $html = <<<HTML
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 15px; }
        h1 { text-align: center; color: #6A5ACD; margin-bottom: 25px; }
        .kanban-container { display: flex; gap: 20px; }
        .column { flex: 1; }
        .column-header { background: #6A5ACD; color: white; padding: 12px; text-align: center; font-weight: bold; margin-bottom: 15px; }
        .task-card { background: #f9f9f9; border-left: 4px solid #6A5ACD; padding: 10px; margin-bottom: 10px; border: 1px solid #ddd; }
        .task-title { font-weight: bold; margin-bottom: 5px; }
        .task-deadline { font-size: 11px; color: #666; margin: 3px 0; }
        .task-notes { font-size: 10px; color: #999; margin-top: 5px; }
        .status-todo { border-left-color: #ff6b6b; }
        .status-doing { border-left-color: #ffa94d; }
        .status-done { border-left-color: #51cf66; }
    </style>
</head>
<body>
    <h1>Tableau des tâches - {{ date('d/m/Y') }}</h1>
    <div class="kanban-container">
HTML;

        foreach ($columns as $idx => $status) {
            $html .= '<div class="column">';
            $html .= '<div class="column-header">' . htmlspecialchars($columnNames[$idx]) . '</div>';

            if (isset($tachesByStatus[$status])) {
                foreach ($tachesByStatus[$status] as $tache) {
                    $statusClass = match($status) {
                        'A_FAIRE' => 'status-todo',
                        'EN_COURS' => 'status-doing',
                        'FAIT' => 'status-done',
                    };
                    $html .= '<div class="task-card ' . $statusClass . '">';
                    $html .= '<div class="task-title">' . htmlspecialchars($tache->getNom()) . '</div>';
                    if ($tache->getDeadline()) {
                        $html .= '<div class="task-deadline">Deadline: ' . $tache->getDeadline()->format('d/m/Y') . '</div>';
                    }
                    if ($tache->getNotes()) {
                        $html .= '<div class="task-notes">' . htmlspecialchars($tache->getNotes()) . '</div>';
                    }
                    $html .= '</div>';
                }
            }

            $html .= '</div>';
        }

        $html .= '</div></body></html>';

        return $html;
    }

    private function formatStatut(string $statut): string
    {
        return match($statut) {
            'A_FAIRE' => 'À faire',
            'EN_COURS' => 'En cours',
            'FAIT' => 'Fait',
            default => $statut
        };
    }

    /**
     * @return array<mixed>
     */
    private function getStatusStyle(string $statut): array
    {
        $color = match($statut) {
            'A_FAIRE' => 'ffcccc',
            'EN_COURS' => 'ffe5cc',
            'FAIT' => 'ccffcc',
            default => 'cccccc'
        };

        return [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
    }
}
