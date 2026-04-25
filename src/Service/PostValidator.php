<?php
namespace App\Service;

use App\Entity\Post;
use App\Entity\Commentaire;
use App\Entity\Categorie;

class PostValidator
{
    // ── RÈGLES POST ──────────────────────────────────

    // Règle 1 : titre obligatoire
    // Règle 2 : titre minimum 5 caractères
    // Règle 3 : contenu obligatoire
    public function validatePost(Post $post): bool
    {
        if (empty($post->getTitre())) {
            throw new \InvalidArgumentException('Le titre est obligatoire');
        }

        if (strlen($post->getTitre()) < 5) {
            throw new \InvalidArgumentException('Le titre doit contenir au moins 5 caractères');
        }

        if (empty($post->getContenu())) {
            throw new \InvalidArgumentException('Le contenu est obligatoire');
        }

        return true;
    }

    
}