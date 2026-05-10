<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Service de CAPTCHA mathématique.
 * Génère une opération addition aléatoire, stocke la réponse en session
 * et expose une méthode de validation.
 */
class CaptchaService
{
    /**
     * Génère un nouveau CAPTCHA (ex: "7 + 4") et le sauvegarde en session.
     * Retourne la question à afficher dans le template.
     */
    public function generateCaptcha(SessionInterface $session): string
    {
        $num1 = random_int(1, 15);
        $num2 = random_int(1, 15);

        $session->set('captcha_answer',   (string) ($num1 + $num2));
        $session->set('captcha_question', "$num1 + $num2");

        return "$num1 + $num2";
    }

    /**
     * Valide la réponse soumise par l'utilisateur contre la valeur en session.
     */
    public function isValid(SessionInterface $session, string $submitted): bool
    {
        $answer = $session->get('captcha_answer', '');

        if ($answer === '') {
            return false;
        }

        return trim($submitted) === $answer;
    }

    /**
     * Récupère la question courante stockée en session (sans régénérer).
     */
    public function getQuestion(SessionInterface $session): string
    {
        return $session->get('captcha_question', '');
    }
}
