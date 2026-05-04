<?php

namespace App\Controller;

use App\Entity\Categorie;
use App\Entity\Post;
use App\Entity\Commentaire;
use App\Form\CategorieType;
use App\Repository\CategorieRepository;
use App\Repository\PostRepository;
use App\Repository\CommentaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/back', name: 'back_')]
class BackController extends AbstractController
{
    // ─── DASHBOARD ────────────────────────────────────────────────────────────

    #[Route('', name: 'dashboard')]
    public function dashboard(
        CategorieRepository   $catRepo,
        PostRepository        $postRepo,
        CommentaireRepository $comRepo
    ): Response {
        return $this->render('back/dashboard.html.twig', [
            'nb_categories'   => $catRepo->count([]),
            'nb_posts'        => $postRepo->count([]),
            'nb_commentaires' => $comRepo->count([]),
        ]);
    }

    // ─── CATÉGORIES ───────────────────────────────────────────────────────────

    #[Route('/categories', name: 'categories')]
    public function categories(CategorieRepository $catRepo, PostRepository $postRepo): Response
    {
        $categories = $catRepo->findBy([], ['idCategorie' => 'ASC'], 100); // Limit to 100

        $postCounts = [];
        foreach ($categories as $cat) {
            $idCat = $cat->getIdCategorie();
            if ($idCat !== null) {
                $postCounts[$idCat] = $postRepo->count(['idCategorie' => $idCat]);
            }
        }

        return $this->render('back/categories.html.twig', [
            'categories' => $categories,
            'postCounts' => $postCounts,
        ]);
    }

    #[Route('/categories/{id}/delete', name: 'categorie_delete', methods: ['POST'])]
    public function deleteCategorie(
        int $id,
        CategorieRepository $catRepo,
        EntityManagerInterface $em
    ): Response {
        $cat = $catRepo->find($id);
        if ($cat) {
            $em->remove($cat);
            $em->flush();
            $this->addFlash('success', 'Catégorie supprimée avec succès.');
        }
        return $this->redirectToRoute('back_categories');
    }

    #[Route('/categories/new', name: 'categorie_new', methods: ['GET', 'POST'])]
    public function newCategorie(Request $request, EntityManagerInterface $em): Response
    {
        $cat = new Categorie();
        $form = $this->createForm(CategorieType::class, $cat, ['attr' => ['novalidate' => 'novalidate']]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $cat->setDateCreation(new \DateTime());
            $em->persist($cat);
            $em->flush();
            $this->addFlash('success', 'Catégorie créée avec succès.');

            return $this->redirectToRoute('back_categories');
        }

        return $this->render('back/categorie_form.html.twig', [
            'form' => $form->createView(),
            'cat' => null,
        ]);
    }

    #[Route('/categories/{id}/edit', name: 'categorie_edit', methods: ['GET', 'POST'])]
    public function editCategorie(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $cat = $em->getRepository(Categorie::class)->find($id);
        if (!$cat) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(CategorieType::class, $cat, ['attr' => ['novalidate' => 'novalidate']]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Catégorie mise à jour avec succès.');

            return $this->redirectToRoute('back_categories');
        }

        return $this->render('back/categorie_form.html.twig', [
            'form' => $form->createView(),
            'cat' => $cat,
        ]);
    }

    // ─── POSTS ────────────────────────────────────────────────────────────────

    #[Route('/posts', name: 'posts')]
    public function posts(
        PostRepository $postRepo,
        CategorieRepository $catRepo,
        EntityManagerInterface $em
    ): Response {
        $posts      = $postRepo->findBy([], ['dateCreation' => 'DESC']);
        $categories = $catRepo->findBy([], ['idCategorie' => 'ASC'], 100); // Limit to 100

        // Map catId => nomCategorie
        $catMap = [];
        foreach ($categories as $cat) {
            $idCat = $cat->getIdCategorie();
            if ($idCat !== null) {
                $catMap[$idCat] = $cat->getNomCategorie();
            }
        }

        // Map userId => "Prénom Nom"
        $userIds = array_unique(array_filter(array_map(fn($p) => $p->getUserId(), $posts)));
        $usersMap = [];
        if (!empty($userIds)) {
            $users = $em->createQueryBuilder()
                ->select('u')
                ->from(\App\Entity\User::class, 'u')
                ->where('u.userId IN (:ids)')
                ->setParameter('ids', $userIds)
                ->getQuery()
                ->getResult();
            foreach ($users as $u) {
                $usersMap[$u->getUserId()] = $u->getUserPrenom() . ' ' . $u->getUserNom();
            }
        }

        return $this->render('back/posts.html.twig', [
            'posts'      => $posts,
            'categories' => $categories,
            'catMap'     => $catMap,
            'usersMap'   => $usersMap,
        ]);
    }

    #[Route('/posts/{id}/delete', name: 'post_delete', methods: ['POST'])]
    public function deletePost(
        int $id,
        PostRepository $postRepo,
        EntityManagerInterface $em
    ): Response {
        $post = $postRepo->find($id);
        if ($post) {
            $em->remove($post);
            $em->flush();
            $this->addFlash('success', 'Post supprimé avec succès.');
        }
        return $this->redirectToRoute('back_posts');
    }

    // ─── COMMENTAIRES ─────────────────────────────────────────────────────────

    #[Route('/commentaires', name: 'commentaires')]
    public function commentaires(
        CommentaireRepository $commentaireRepo,
        EntityManagerInterface $em
    ): Response {
        $commentaires = $commentaireRepo->findBy([], ['dateCommentaire' => 'DESC']);

        // Map postId => Post object
        $postsMap = [];
        foreach ($commentaires as $c) {
            $pid = $c->getIdPost();
            if ($pid && !isset($postsMap[$pid])) {
                $postsMap[$pid] = $em->getRepository(Post::class)->find($pid);
            }
        }

        // Map catId => nomCategorie
        $catMap = [];
        foreach ($postsMap as $post) {
            if ($post && !isset($catMap[$post->getIdCategorie()])) {
                $cat = $em->getRepository(Categorie::class)->find($post->getIdCategorie());
                $catMap[$post->getIdCategorie()] = $cat ? $cat->getNomCategorie() : null;
            }
        }

        // Map userId => "Prénom Nom"
        $userIds = array_unique(array_filter(array_map(fn($c) => $c->getUserId(), $commentaires)));
        $usersMap = [];
        if (!empty($userIds)) {
            $users = $em->createQueryBuilder()
                ->select('u')
                ->from(\App\Entity\User::class, 'u')
                ->where('u.userId IN (:ids)')
                ->setParameter('ids', $userIds)
                ->getQuery()
                ->getResult();
            foreach ($users as $u) {
                $usersMap[$u->getUserId()] = $u->getUserPrenom() . ' ' . $u->getUserNom();
            }
        }

        return $this->render('back/commentaires.html.twig', [
            'commentaires' => $commentaires,
            'postsMap'     => $postsMap,
            'catMap'       => $catMap,
            'usersMap'     => $usersMap,
        ]);
    }

    #[Route('/commentaires/{id}/delete', name: 'commentaire_delete', methods: ['POST'])]
    public function deleteCommentaire(
        int $id,
        CommentaireRepository $comRepo,
        EntityManagerInterface $em
    ): Response {
        $com = $comRepo->find($id);
        if ($com) {
            $em->remove($com);
            $em->flush();
            $this->addFlash('success', 'Commentaire supprimé.');
        }
        return $this->redirectToRoute('back_commentaires');
    }
}