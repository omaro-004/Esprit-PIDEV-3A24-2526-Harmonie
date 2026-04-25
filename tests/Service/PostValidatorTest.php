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
}
