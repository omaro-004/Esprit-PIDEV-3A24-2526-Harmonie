<?php
namespace App\DataFixtures;

use App\Entity\Post;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PostFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $post = new Post();
            $post->setTitre("Post de test numéro $i");
            $post->setContenu("Ceci est le contenu du post $i pour tester la pagination.");
            $post->setDateCreation(new \DateTime());
            $post->setUserId(1);
            $post->setIdCategorie(24); // ← ton id de catégorie
            $manager->persist($post);
        }
        $manager->flush();
    }
}