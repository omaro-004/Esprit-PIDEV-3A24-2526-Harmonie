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
 * Installe avec : composer require "endroid/qr-code:^5.0"
 *
 * Fonctionnalités :
 *  - QR code violet aux couleurs d'Harmony
 *  - Logo centré optionnel (si le fichier existe)
 *  - Lien WhatsApp pré-formaté avec les détails de la séance
 *  - Résultat sous forme de Data URI PNG (prêt pour <img src="...">)
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
     * @throws \RuntimeException      Si la génération échoue pour toute raison
     */
    public function generateSessionQrCode(string $text, ?string $logoPath = null): string
    {
        // ── Blindage global : catch \Throwable attrape AUSSI les \Error PHP ──
        // (class not found, TypeError, ArgumentCountError, etc.)
        try {
            $writer = new PngWriter();

            // ── QR Code — couleurs violettes Harmony ──────────────────────────
            // Syntaxe endroid/qr-code v5.x avec arguments nommés PHP 8.0+
            $qrCode = new QrCode(
                data:               $text,
                encoding:           new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,   // enum PHP 8.1 v5.x
                size:               300,
                margin:             12,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
                foregroundColor:    new Color(106, 27, 154),         // violet Harmony
                backgroundColor:    new Color(255, 255, 255)
            );

            // ── Logo centré (optionnel) ────────────────────────────────────────
            $logo = null;
            if ($logoPath !== null && file_exists($logoPath)) {
                // Logo::create() est la factory statique de v5.x
                // Les setters sont en chaîne fluide et retournent $this
                $logo = Logo::create($logoPath)
                    ->setResizeToWidth(65)
                    ->setPunchoutBackground(true);
            }

            // ── Génération du résultat ────────────────────────────────────────
            $result = $writer->write($qrCode, $logo);

            // Retourne "data:image/png;base64,..." directement utilisable en <img>
            return $result->getDataUri();

        } catch (\Throwable $e) {
            // \Throwable couvre \Exception ET \Error (class not found, TypeError…)
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
     * @param  string  $dateLabel   Date en français (ex : "7 avril 2025")
     * @param  array[] $exercises   Tableau d'activités sérialisées (activiteToArray)
     * @return string               URL https://api.whatsapp.com/send?text=...
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