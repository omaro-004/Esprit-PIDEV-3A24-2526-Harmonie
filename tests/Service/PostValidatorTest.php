<?php

namespace App\Tests\Service;

use App\Entity\Post;
use App\Entity\Commentaire;
use App\Entity\Categorie;
use App\Service\PostValidator;
use PHPUnit\Framework\TestCase;

class PostValidatorTest extends TestCase
{
    private PostValidator $validator;

    // setUp s'exécute avant chaque test
    protected function setUp(): void
    {
        $this->validator = new PostValidator();
    }
    // ═══════════════════════════════════
    // TESTS POST
    // ═══════════════════════════════════

    // Test 1 : post avec titre et contenu valides → doit retourner true
    public function testPostValide(): void
    {
        $post = new Post();
        $post->setTitre('Comment apprendre Python en 2026 ?');
        $post->setContenu('Je cherche des ressources pour débuter en Python.');

        $this->assertTrue($this->validator->validatePost($post));
    }
    // Test 2 : titre vide → doit lancer une exception
    public function testPostTitreVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre est obligatoire');

        $post = new Post();
        $post->setTitre('');
        $post->setContenu('Contenu valide ici');

        $this->validator->validatePost($post);
    } 
    // Test 3 : titre trop court → doit lancer une exception
    public function testPostTitreTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre doit contenir au moins 5 caractères');

        $post = new Post();
        $post->setTitre('AI');
        $post->setContenu('Contenu valide ici');

        $this->validator->validatePost($post);
    }
    // Test 4 : contenu vide → doit lancer une exception
    public function testPostContenuVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu est obligatoire');

        $post = new Post();
        $post->setTitre('Titre valide ici');
        $post->setContenu('');

        $this->validator->validatePost($post);
    }
    // ═══════════════════════════════════
    // TESTS COMMENTAIRE
    // ═══════════════════════════════════

    // Test 5 : commentaire valide
    public function testCommentaireValide(): void
    {
        $c = new Commentaire();
        $c->setContenu('Super explication, merci beaucoup !');

        $this->assertTrue($this->validator->validateCommentaire($c));
    }
    // Test 6 : commentaire vide
    public function testCommentaireVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le commentaire ne peut pas être vide');

        $c = new Commentaire();
        $c->setContenu('');

        $this->validator->validateCommentaire($c);
    }
    // Test 7 : commentaire trop court
    public function testCommentaireTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le commentaire doit contenir au moins 3 caractères');

        $c = new Commentaire();
        $c->setContenu('OK');

        $this->validator->validateCommentaire($c);
    }
    // ═══════════════════════════════════
    // TESTS CATÉGORIE
    // ═══════════════════════════════════

    // Test 8 : catégorie valide
    public function testCategorieValide(): void
    {
        $cat = new Categorie();
        $cat->setNomCategorie('Intelligence Artificielle');

        $this->assertTrue($this->validator->validateCategorie($cat));
    }
    // Test 9 : nom catégorie vide
    public function testCategorieNomVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom de la catégorie est obligatoire');

        $cat = new Categorie();
        $cat->setNomCategorie('');

        $this->validator->validateCategorie($cat);
    }
    // Test 10 : nom catégorie trop court
    public function testCategorieNomTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom doit contenir au moins 3 caractères');

        $cat = new Categorie();
        $cat->setNomCategorie('IA');

        $this->validator->validateCategorie($cat);
    }
}
