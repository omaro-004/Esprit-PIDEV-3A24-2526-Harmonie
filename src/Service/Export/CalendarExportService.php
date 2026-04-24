<?php

namespace App\Service\Export;

use App\Entity\Evenement;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CalendarExportService
{
    /**
     * Génère et retourne un PDF du calendrier
     */
    public function exportToPdf(array $evenements, int $year, int $month): Response
    {
        $monthName = $this->getMonthName($month);
        $html = $this->generateCalendarHtml($evenements, $year, $month, $monthName);

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = sprintf('calendrier-%s-%d.pdf', strtolower($monthName), $year);

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
     * Génère et retourne un Excel du calendrier
     */
    public function exportToExcel(array $evenements, int $year, int $month): StreamedResponse
    {
        $monthName = $this->getMonthName($month);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Événements');

        // En-tête
        $sheet->setCellValue('A1', sprintf('Calendrier - %s %d', $monthName, $year));
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Colonnes
        $headers = ['Titre', 'Date début', 'Date fin', 'Type', 'Lieu', 'Statut'];
        $sheet->fromArray($headers, null, 'A3');

        // Style en-tête
        $headerStyle = [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6A5ACD']],
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('A3:F3')->applyFromArray($headerStyle);

        // Données
        $row = 4;
        foreach ($evenements as $ev) {
            $sheet->setCellValue('A' . $row, $ev->getTitre());
            $sheet->setCellValue('B' . $row, $ev->getDateDebut()?->format('d/m/Y H:i') ?? '');
            $sheet->setCellValue('C' . $row, $ev->getDateFin()?->format('d/m/Y H:i') ?? '');
            $sheet->setCellValue('D' . $row, $this->formatEventType($ev->getEventType()));
            $sheet->setCellValue('E' . $row, $ev->getLieuAdresse() ?? ($ev->getSalle()?->getNom() ?? ''));
            $sheet->setCellValue('F' . $row, $ev->isApprouve() ? 'Approuvé' : 'En attente');
            $row++;
        }

        // Largeurs
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(25);
        $sheet->getColumnDimension('F')->setWidth(15);

        // Bordures
        $borderStyle = ['borderStyle' => Border::BORDER_THIN];
        $sheet->getStyle('A3:F' . ($row - 1))->applyFromArray(['borders' => $borderStyle]);

        // Streaming
        $filename = sprintf('calendrier-%s-%d.xlsx', strtolower($monthName), $year);
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

    private function generateCalendarHtml(array $evenements, int $year, int $month, string $monthName): string
    {
        $firstDay = mktime(0, 0, 0, $month, 1, $year);
        $daysInMonth = date('t', $firstDay);
        $startWeekday = date('N', $firstDay);

        $eventsByDay = [];
        foreach ($evenements as $ev) {
            if ($ev->getDateDebut()) {
                $day = (int)$ev->getDateDebut()->format('j');
                if (!isset($eventsByDay[$day])) {
                    $eventsByDay[$day] = [];
                }
                $eventsByDay[$day][] = $ev;
            }
        }

        $html = <<<HTML
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { text-align: center; color: #6A5ACD; margin-bottom: 30px; }
        .calendar { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .calendar th { background: #6A5ACD; color: white; padding: 10px; text-align: left; border: 1px solid #ddd; }
        .calendar td { border: 1px solid #ddd; padding: 10px; height: 120px; vertical-align: top; }
        .calendar td.other-month { background: #f5f5f5; }
        .event-item { font-size: 11px; margin: 3px 0; padding: 3px; background: #e8e8ff; border-left: 3px solid #6A5ACD; }
        .event-time { font-weight: bold; color: #333; }
        .event-type { font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <h1>Calendrier - $monthName $year</h1>
    <table class="calendar">
        <tr>
            <th>Lun</th><th>Mar</th><th>Mer</th><th>Jeu</th><th>Ven</th><th>Sam</th><th>Dim</th>
        </tr>
HTML;

        $dayCounter = 1;
        $weekday = $startWeekday;

        while ($dayCounter <= $daysInMonth || $weekday > 1) {
            $html .= '<tr>';

            for ($i = 1; $i <= 7; $i++) {
                if ($dayCounter === 1 && $i < $weekday) {
                    $html .= '<td class="other-month"></td>';
                } elseif ($dayCounter > $daysInMonth) {
                    $html .= '<td class="other-month"></td>';
                } else {
                    $html .= '<td>';
                    $html .= '<strong>' . $dayCounter . '</strong><br>';

                    if (isset($eventsByDay[$dayCounter])) {
                        foreach ($eventsByDay[$dayCounter] as $ev) {
                            $time = $ev->getDateDebut()?->format('H:i') ?? '';
                            $type = $this->formatEventType($ev->getEventType());
                            $html .= '<div class="event-item">';
                            if ($time) {
                                $html .= '<div class="event-time">' . htmlspecialchars($time) . '</div>';
                            }
                            $html .= '<div>' . htmlspecialchars(substr($ev->getTitre() ?? '', 0, 20)) . '</div>';
                            $html .= '<div class="event-type">' . $type . '</div>';
                            $html .= '</div>';
                        }
                    }
                    $html .= '</td>';
                    $dayCounter++;
                }
            }

            $html .= '</tr>';
            $weekday = 1;
        }

        $html .= '</table></body></html>';

        return $html;
    }

    private function getMonthName(int $month): string
    {
        $months = [
            'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
            'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
        ];
        return $months[$month - 1] ?? '';
    }

    private function formatEventType(?string $type): string
    {
        return match($type) {
            'cours' => 'Cours',
            'reunion' => 'Réunion',
            'loisir' => 'Loisir',
            'autre' => 'Autre',
            default => 'Événement'
        };
    }
}
