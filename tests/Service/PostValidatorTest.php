<?php

namespace App\Tests\Service;

use App\Entity\Post;
use App\Entity\Commentaire;
use App\Entity\Categorie;
use App\Service\PostValidator;
use PHPUnit\Framework\TestCase;
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
}
