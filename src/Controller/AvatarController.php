<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AvatarController extends AbstractController
{
    #[Route('/avatar_creation', name: 'avatar_creation', methods: ['GET'])]
    public function create(Request $request): Response
    {
        $returnUrl = $request->query->get('returnUrl', $this->generateUrl('journal_new'));

        return $this->render('journal/avatar_create.html.twig', [
            'returnUrl' => $returnUrl,
        ]);
    }
}
