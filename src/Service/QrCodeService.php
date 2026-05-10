<?php

namespace App\Service;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Service de génération de QR codes — Harmony
 *
 * Compatible avec endroid/qr-code v5.x (PHP 8.1+ enum API).
 */
class QrCodeService
{
    /**
     * Génère un QR code PNG encodé en Data URI.
     *
     * @param  string      $text      Texte / URL à encoder dans le QR code
     * @param  string|null $logoPath  Chemin absolu vers le logo (optionnel)
     * @return string                 Data URI "data:image/png;base64,..."
     *
     * @throws \RuntimeException Si la génération échoue pour toute raison
     */
    public function generateSessionQrCode(string $text, ?string $logoPath = null): string
    {
        try {
            $writer = new PngWriter();

            $qrCode = new QrCode(
                data:                 $text,
                encoding:             new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size:                 300,
                margin:               12,
                roundBlockSizeMode:   RoundBlockSizeMode::Margin,
                foregroundColor:      new Color(106, 27, 154),
                backgroundColor:      new Color(255, 255, 255)
            );

            $logo = null;
            if ($logoPath !== null && file_exists($logoPath)) {
                $logo = Logo::create($logoPath)
                    ->setResizeToWidth(65)
                    ->setPunchoutBackground(true);
            }

            $result = $writer->write($qrCode, $logo);

            return $result->getDataUri();

        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Erreur lors de la génération du QR code : ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Construit une URL WhatsApp pré-remplie avec le résumé de la séance.
     *
     * @param  string                      $dateLabel  Date en français (ex : "7 avril 2025")
     * @param  array<int, array<string, mixed>> $exercises  Tableau d'activités sérialisées
     * @return string                      URL https://api.whatsapp.com/send?text=...
     */
    public function buildWhatsAppUrl(string $dateLabel, array $exercises): string
    {
        $text     = "🏋️ *Séance Harmony du {$dateLabel}*\n\n";
        $totalMin = 0;
        $totalCal = 0;

        foreach ($exercises as $ex) {
            $text .= '• *' . ($ex['exercice_nom'] ?? 'Exercice') . "*\n";
            $text .= '  ⏱ ' . ($ex['duree_minutes'] ?? 0) . ' min';

            if (!empty($ex['calories_brulees'])) {
                $text    .= ' | 🔥 ' . $ex['calories_brulees'] . ' kcal';
                $totalCal += (int) $ex['calories_brulees'];
            }
            if (!empty($ex['nb_series'])) {
                $text .= ' | 🔁 ' . $ex['nb_series'] . '×' . ($ex['nb_repetitions'] ?? '?');
            }
            if (!empty($ex['poids'])) {
                $text .= ' | ⚖ ' . $ex['poids'] . ' kg';
            }
            $text    .= "\n";
            $totalMin += (int) ($ex['duree_minutes'] ?? 0);
        }

        $text .= "\n📊 *Total : {$totalMin} min";
        if ($totalCal > 0) {
            $text .= " | {$totalCal} kcal";
        }
        $text .= "*\n\n💜 Partagé depuis *Harmony — Journal de Sport*";

        return 'https://api.whatsapp.com/send?text=' . urlencode($text);
    }
}