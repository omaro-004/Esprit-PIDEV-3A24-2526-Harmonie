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

    // ── RÈGLES COMMENTAIRE ───────────────────────────

    // Règle 4 : contenu obligatoire
    // Règle 5 : minimum 3 caractères
    public function validateCommentaire(Commentaire $c): bool
    {
        if (empty($c->getContenu())) {
            throw new \InvalidArgumentException('Le commentaire ne peut pas être vide');
        }

        if (strlen($c->getContenu()) < 3) {
            throw new \InvalidArgumentException('Le commentaire doit contenir au moins 3 caractères');
        }

        return true;
    }
    // ── RÈGLES CATÉGORIE ─────────────────────────────

    // Règle 6 : nom obligatoire
    // Règle 7 : nom minimum 3 caractères
    public function validateCategorie(Categorie $cat): bool
    {
        if (empty($cat->getNomCategorie())) {
            throw new \InvalidArgumentException('Le nom de la catégorie est obligatoire');
        }

        if (strlen($cat->getNomCategorie()) < 3) {
            throw new \InvalidArgumentException('Le nom doit contenir au moins 3 caractères');
        }

        return true;
    }
    
}