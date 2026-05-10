<?php

namespace App\Service;

use App\Entity\User;

class SuspicionScoreService
{
    private const SUSPICIOUS_WORDS = [
        'test', 'admin', 'user', 'fake', 'demo', 'lorem', 'ipsum',
        'aaa', 'bbb', 'ccc', 'xxx', 'yyy', 'zzz', 'abc', 'azerty',
        'qwerty', 'temp', 'null', 'undefined', 'sample', 'toto',
        'tata', 'titi', 'tutu', 'foo', 'bar', 'baz',
    ];

    private const SUSPICIOUS_EMAIL_DOMAINS = [
        'test.com', 'fake.com', 'temp.com', 'yopmail.com',
        'mailinator.com', 'guerrillamail.com', 'trashmail.com',
        'throwam.com', 'spam.la', 'example.com',
    ];

    public function compute(User $user): int
    {
        return array_sum(array_column($this->getBreakdown($user), 'points'));
    }

    /**
     * Retourne le détail de chaque critère avec son score partiel.
     *
     * @return array<int, array{critere: string, points: int, detail: string, flag: bool}>
     */
    public function getBreakdown(User $user): array
    {
        $breakdown = [];
        $nom    = strtolower(trim($user->getUserNom()));
        $prenom = strtolower(trim($user->getUserPrenom()));
        $email  = strtolower(trim($user->getUserEmail()));

        // 1. Mots suspects
        $hasSuspWord = false;
        foreach (self::SUSPICIOUS_WORDS as $word) {
            if (str_contains($nom, $word) || str_contains($prenom, $word)) {
                $hasSuspWord = true; break;
            }
        }
        $breakdown[] = [
            'critere' => 'Mots suspects dans nom/prénom',
            'points'  => $hasSuspWord ? 30 : 0,
            'detail'  => $hasSuspWord ? "Le nom ou prénom contient un mot suspect." : "Aucun mot suspect détecté.",
            'flag'    => $hasSuspWord,
        ];

        // 2. Caractères répétitifs
        $repet = preg_match('/(.)\1{2,}/', $nom) || preg_match('/(.)\1{2,}/', $prenom);
        $breakdown[] = [
            'critere' => 'Caractères répétitifs',
            'points'  => $repet ? 25 : 0,
            'detail'  => $repet ? "Le nom/prénom contient des caractères répétés (ex: aaaa)." : "Pas de répétition anormale.",
            'flag'    => $repet,
        ];

        // 3. Nom == Prénom
        $sameNP = ($nom === $prenom);
        $breakdown[] = [
            'critere' => 'Nom identique au prénom',
            'points'  => $sameNP ? 20 : 0,
            'detail'  => $sameNP ? "Le nom et le prénom sont identiques." : "Nom et prénom différents.",
            'flag'    => $sameNP,
        ];

        // 4. Domaine email suspect
        $parts = explode('@', $email);
        $domain = $parts[1] ?? '';
        $suspDomain = false;
        foreach (self::SUSPICIOUS_EMAIL_DOMAINS as $d) {
            if (str_contains($domain, $d)) { $suspDomain = true; break; }
        }
        $breakdown[] = [
            'critere' => 'Domaine email suspect',
            'points'  => $suspDomain ? 25 : 0,
            'detail'  => $suspDomain ? "Domaine «{$domain}» est un domaine temporaire/suspect." : "Domaine email normal.",
            'flag'    => $suspDomain,
        ];

        // 5. Email contient mots suspects
        $emailSusp = false;
        foreach (self::SUSPICIOUS_WORDS as $word) {
            if (str_contains($email, $word)) { $emailSusp = true; break; }
        }
        $breakdown[] = [
            'critere' => 'Mot suspect dans l\'email',
            'points'  => $emailSusp ? 15 : 0,
            'detail'  => $emailSusp ? "L'adresse email contient un terme suspect." : "Email sans terme suspect.",
            'flag'    => $emailSusp,
        ];

        // 6. Nom/prénom trop court
        $tooShort = (mb_strlen($nom) <= 1 || mb_strlen($prenom) <= 1);
        $breakdown[] = [
            'critere' => 'Nom/prénom trop court',
            'points'  => $tooShort ? 15 : 0,
            'detail'  => $tooShort ? "Un champ fait 1 caractère ou moins." : "Longueur correcte.",
            'flag'    => $tooShort,
        ];

        // 7. Séquences numériques
        $numSeq = preg_match('/\d{3,}/', $nom) || preg_match('/\d{3,}/', $prenom);
        $breakdown[] = [
            'critere' => 'Séquences numériques dans le nom',
            'points'  => $numSeq ? 10 : 0,
            'detail'  => $numSeq ? "Le nom/prénom contient une séquence de chiffres." : "Pas de séquence numérique.",
            'flag'    => $numSeq,
        ];

        // Cap total à 100
        $total = array_sum(array_column($breakdown, 'points'));
        if ($total > 100) {
            $excess = $total - 100;
            $last   = &$breakdown[count($breakdown) - 1];
            $last['points'] = max(0, $last['points'] - $excess);
        }

        return $breakdown;
    }

    /**
     * @param array<int, User> $users
     * @return array<int, User>
     */
    public function sortBySuspicion(array $users): array
    {
        usort($users, fn(User $a, User $b) => $this->compute($b) <=> $this->compute($a));
        return $users;
    }

    public function getLabel(int $score): string
    {
        return match(true) {
            $score >= 60 => 'Très suspect',
            $score >= 35 => 'Suspect',
            $score >= 15 => 'Modéré',
            default      => 'Normal',
        };
    }

    public function getColor(int $score): string
    {
        return match(true) {
            $score >= 60 => '#E05252',
            $score >= 35 => '#E5A44B',
            $score >= 15 => '#6A5ACD',
            default      => '#10B981',
        };
    }

    /** Couleur de bordure pour les cards */
    public function getBorderColor(int $score): string
    {
        return match(true) {
            $score >= 60 => '#FCA5A5',  // rouge clair
            $score >= 35 => '#FCD34D',  // orange clair
            $score >= 15 => '#C4B5FD',  // violet clair
            default      => '#6EE7B7',  // vert clair
        };
    }
}
