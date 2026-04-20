<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CoversController extends AbstractController
{
    #[Route('/covers/{filename}', name: 'app_cover_image', requirements: ['filename' => '.+'])]
    public function serve(string $filename): Response
    {
        $path = 'C:/wamp64/www/covers/' . $filename;

        if (!file_exists($path) || !is_file($path)) {
            throw $this->createNotFoundException('Cover image not found.');
        }

        return new BinaryFileResponse($path);
    }
}
