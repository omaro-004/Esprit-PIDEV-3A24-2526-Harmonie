<?php
namespace App\Controller;


use App\Form\CategorieType;
use App\Form\PostType;
use App\Form\CommentaireType;
use App\Entity\Categorie;
use App\Entity\Post;
use App\Entity\Commentaire;
use App\Entity\Reaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Service\ModerationService;
use Knp\Component\Pager\PaginatorInterface;
use App\Service\TranslationService;
use App\Service\SpellCheckService;
use App\Service\ImageGen;
// ── Résumer la discussion ──────────────────────────────
use App\Service\SummaryService;
use App\Repository\CommentaireRepository;


use App\Repository\PostRepository;
use App\Service\SentimentService;
class ForumController extends AbstractController
{
    // ── analyse du sentiment comment ──────────────────────────────
    #[Route('/forum/comment/{id}/sentiment', name: 'comment_sentiment', methods: ['POST'])]
    public function analyzeSentiment(
        int $id,
        CommentaireRepository $commentaireRepo,
        SentimentService $sentimentService
    ): JsonResponse {
        try {
            $commentaire = $commentaireRepo->find($id);
            if (!$commentaire) {
                return new JsonResponse(['error' => 'Commentaire introuvable'], 404);
            }


            $result = $sentimentService->analyze($commentaire->getContenu());


            return new JsonResponse($result);


        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }


     // ── Résumer la discussion ──────────────────────────────


    #[Route('/forum/post/{id}/summarize', name: 'summarize_discussion', methods: ['POST'])]
    public function summarizeDiscussion(
        int $id,
        CommentaireRepository $commentaireRepo,
        PostRepository $postRepo,
        SummaryService $summaryService
    ): JsonResponse {
        try {
             // Vérifie si connecté, retourne JSON au lieu de rediriger
            if (!$this->getUser()) {
                return new JsonResponse(['error' => 'Non connecté'], 401);
            }


            $post = $postRepo->find($id);
            if (!$post) {
                return new JsonResponse(['error' => 'Post introuvable'], 404);
            }


            $commentaires = $commentaireRepo->findBy(['idPost' => $id]);


            if (empty($commentaires)) {
                return new JsonResponse(['summary' => 'Aucun commentaire à résumer pour le moment.']);
            }


            $resume = $summaryService->summarizeDiscussion(
                $post->getTitre(),
                $commentaires
            );


            return new JsonResponse(['summary' => $resume]);


        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }


   


    // ── GÉNÉRATION IMAGE IA ──────────────────────────────
    #[Route('/forum/generate-image', name: 'forum_generate_image', methods: ['POST'])]
    public function generateImage(
        Request $request,
        ImageGen $imageGenerator
    ): JsonResponse {
        $prompt = trim($request->request->get('prompt', ''));
        $style  = trim($request->request->get('style', ''));


        if (empty($prompt)) {
            return new JsonResponse(['error' => 'Merci de décrire l\'image.'], 400);
        }


        if (strlen($prompt) > 500) {
            return new JsonResponse(['error' => 'Prompt trop long (max 500 caractères).'], 400);
        }


        $imageBase64 = $imageGenerator->generateImageBytes($prompt, $style);


        if (!$imageBase64) {
            return new JsonResponse([
                'error' => 'Génération échouée. Vérifie ta connexion internet et réessaye.'
            ], 503);
        }


        return new JsonResponse([
            'image'  => $imageBase64,
            'prompt' => $prompt,
            'style'  => $style,
        ]);
    }




    // ── CORRECTION ORTHOGRAPHIQUE (AJAX temps réel) ──────
    #[Route('/forum/spellcheck', name: 'forum_spellcheck', methods: ['POST'])]
    public function spellcheck(
        Request $request,
        SpellCheckService $spellCheck
    ): JsonResponse {
        $text     = $request->request->get('text', '');
        $language = $request->request->get('language', 'fr');


        // Sécurité — texte trop long
        if (strlen($text) > 2000) {
            return new JsonResponse(['errors' => []]);
        }


        $errors = $spellCheck->check($text, $language);


        return new JsonResponse(['errors' => $errors]);
    }


    private function getCurrentUserId(): int
    {
        // ❌ AVANT — getUserId() n'existe pas dans UserInterface
        //return $this->getUser()->getUserId();
        // ✅ APRÈS — cast vers ton entité User
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        return (int) $user->getId();
    }


    // ── TRADUCTION D'UN POST (AJAX) ──────────────────────
    #[Route('/forum/post/{id}/translate', name: 'forum_post_translate', methods: ['POST'])]
    public function translatePost(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        TranslationService $translator
    ): JsonResponse {
        $post = $em->getRepository(Post::class)->find($id);
        if (!$post) {
            return new JsonResponse(['error' => 'Post introuvable'], 404);
        }


        // Récupère la langue cible depuis la requête AJAX
        $targetLang = $request->request->get('lang', 'en');


        // Langues supportées pour éviter les abus
        $supportedLangs = ['en', 'ar', 'es', 'de', 'it'];
        if (!in_array($targetLang, $supportedLangs)) {
            return new JsonResponse(['error' => 'Langue non supportée'], 400);
        }


        // Traduit le titre et le contenu séparément
        $translatedTitre   = $translator->translate($post->getTitre(), 'fr', $targetLang);
        $translatedContenu = $translator->translate($post->getContenu(), 'fr', $targetLang);


        return new JsonResponse([
            'titre'   => $translatedTitre,
            'contenu' => $translatedContenu,
            'lang'    => $targetLang,
        ]);
    }


    // ════════════════════════════════════════════════
    //  CATÉGORIES
    // ════════════════════════════════════════════════


    #[Route('/forum', name: 'forum')]
    public function index(EntityManagerInterface $em): Response
    {
        $categories = $em->getRepository(Categorie::class)->findAll();
        return $this->render('forum/index.html.twig', [
            'categories' => $categories,
        ]);
    }


    #[Route('/forum/categorie/new', name: 'forum_categorie_new', methods: ['GET','POST'])]
public function newCategorie(Request $request, EntityManagerInterface $em): Response
{
    $cat  = new Categorie();
    $form = $this->createForm(CategorieType::class, $cat, ['attr' => ['novalidate' => 'novalidate']]);
    $form->handleRequest($request);


    if ($form->isSubmitted() && $form->isValid()) {
        $cat->setDateCreation(new \DateTime());
        $em->persist($cat);
        $em->flush();
        return $this->redirectToRoute('forum');
    }


    return $this->render('forum/categorie_form.html.twig', [
        'form'   => $form->createView(),
        'cat'    => null,
    ]);
}


   // #[Route('/forum/categorie/{id}/edit', name: 'forum_categorie_edit', methods: ['GET','POST'])]
    //public function editCategorie(int $id, Request $request, EntityManagerInterface $em): Response
    #[Route('/forum/categorie/{id}/edit', name: 'forum_categorie_edit', methods: ['GET','POST'])]
public function editCategorie(int $id, Request $request, EntityManagerInterface $em): Response
{
    $cat = $em->getRepository(Categorie::class)->find($id);
    if (!$cat) throw $this->createNotFoundException();


    $form = $this->createForm(CategorieType::class, $cat, ['attr' => ['novalidate' => 'novalidate']]);
    $form->handleRequest($request);


    if ($form->isSubmitted() && $form->isValid()) {
        $em->flush();
        return $this->redirectToRoute('forum');
    }


    return $this->render('forum/categorie_form.html.twig', [
        'form' => $form->createView(),
        'cat'  => $cat,
    ]);
}


    #[Route('/forum/categorie/{id}/delete', name: 'forum_categorie_delete', methods: ['POST'])]
    public function deleteCategorie(int $id, EntityManagerInterface $em): Response
    {
        $cat = $em->getRepository(Categorie::class)->find($id);
        if ($cat) { $em->remove($cat); $em->flush(); }
        return $this->redirectToRoute('forum');
    }


















    // ════════════════════════════════════════════════
    //  POSTS — avec recherche, tri, pagination
    // ════════════════════════════════════════════════


   


#[Route('/forum/categorie/{id}', name: 'forum_posts')]
public function posts(
    int $id,
    Request $request,
    EntityManagerInterface $em,
    PaginatorInterface $paginator    // ← KnpPaginator injecté automatiquement
): Response {
    $categorie = $em->getRepository(Categorie::class)->find($id);
    if (!$categorie) throw $this->createNotFoundException();


    // ── Paramètres GET ──
    $search = trim($request->query->get('search', ''));
    $tri    = $request->query->get('tri', 'date_desc');


    // ── Construction de la requête Doctrine (QueryBuilder) ──
    // On passe le QueryBuilder au paginator, pas les résultats
    // Le paginator s'occupe lui-même de LIMIT et OFFSET selon la page
    $qb = $em->createQueryBuilder()
        ->select('p')
        ->from(Post::class, 'p')
        ->where('p.idCategorie = :idCat')
        ->setParameter('idCat', $id);


    if ($search !== '') {
        $qb->andWhere('p.titre LIKE :s OR p.contenu LIKE :s')
           ->setParameter('s', '%' . $search . '%');
    }


    match($tri) {
        'date_asc' => $qb->orderBy('p.dateCreation', 'ASC'),
        'likes'    => $qb->orderBy('p.dateCreation', 'DESC'),
        default    => $qb->orderBy('p.dateCreation', 'DESC'),
    };


    // ── KnpPaginator — remplace toute la logique manuelle ──
    // paginate(requête, numéro de page, nombre d'éléments par page)
    // Récupère automatiquement ?page=N dans l'URL
    $pagination = $paginator->paginate(
        $qb->getQuery(),                      // la requête Doctrine
        $request->query->getInt('page', 1),   // page courante (défaut: 1)
        5                                      // posts par page
    );


    // ── Les posts de la page courante ──
    // $pagination->getItems() retourne uniquement les posts de la page
    $posts = $pagination->getItems();


    // ── Likes ──
    $likesMap  = [];
    $likedByMe = [];
    foreach ($posts as $post) {
        $pid = $post->getIdPost();
        $reactions = $em->getRepository(Reaction::class)
            ->findBy(['idPost' => $pid, 'typeReaction' => 'like']);
        $likesMap[$pid]  = count($reactions);
        $likedByMe[$pid] = (bool) $em->getRepository(Reaction::class)
            ->findOneBy(['idPost' => $pid, 'userId' => $this->getCurrentUserId(), 'typeReaction' => 'like']);
    }


    // Tri par likes (après pagination — uniquement sur la page courante)
    if ($tri === 'likes') {
        $postsArray = $posts;
        usort($postsArray, fn($a, $b) =>
            ($likesMap[$b->getIdPost()] ?? 0) <=> ($likesMap[$a->getIdPost()] ?? 0)
        );
        $posts = $postsArray;
    }


    // ── Commentaires pour les posts affichés ──
    $commentairesMap = [];
    foreach ($posts as $post) {
        $commentairesMap[$post->getIdPost()] = $em->getRepository(Commentaire::class)
            ->findBy(['idPost' => $post->getIdPost()], ['dateCommentaire' => 'ASC']);
    }


    // ── Map userId => "Prénom Nom" ──
    $userIds = array_unique(array_map(fn($p) => $p->getUserId(), $posts));
    $allCommentUserIds = [];
    foreach ($commentairesMap as $comments) {
        foreach ($comments as $c) {
            $allCommentUserIds[] = $c->getUserId();
        }
    }
    $allUserIds = array_unique(array_merge($userIds, $allCommentUserIds));


    $usersMap = [];
    if (!empty($allUserIds)) {
        $users = $em->createQueryBuilder()
            ->select('u')->from(\App\Entity\User::class, 'u')
            ->where('u.userId IN (:ids)')->setParameter('ids', $allUserIds)
            ->getQuery()->getResult();
        foreach ($users as $u) {
            $usersMap[$u->getUserId()] = $u->getUserPrenom() . ' ' . $u->getUserNom();
        }
    }


    return $this->render('forum/posts.html.twig', [
        'categorie'       => $categorie,
        'posts'           => $posts,
        'pagination'      => $pagination,    // ← objet pagination pour le template
        'commentairesMap' => $commentairesMap,
        'likesMap'        => $likesMap,
        'likedByMe'       => $likedByMe,
        'usersMap'        => $usersMap,
        'search'          => $search,
        'tri'             => $tri,
        // Ces variables ne sont plus nécessaires — KnpPaginator les gère
        // 'page'       => supprimé
        // 'totalPages' => supprimé
        // 'total'      => supprimé
    ]);
}
    // ════════════════════════════════════════════════
    // ── LIKE toggle (AJAX) ──────────────────────────
    // ════════════════════════════════════════════════


    #[Route('/forum/post/{id}/like', name: 'forum_post_like', methods: ['POST', 'GET'])]
    public function toggleLike(int $id, EntityManagerInterface $em): JsonResponse
    {
        $post = $em->getRepository(Post::class)->find($id);
        if (!$post) return new JsonResponse(['error' => 'Post introuvable'], 404);


        $existing = $em->getRepository(Reaction::class)->findOneBy([
            'idPost'       => $id,
            'userId'       => $this->getCurrentUserId(),
            'typeReaction' => 'like',
        ]);


        if ($existing) {
            $em->remove($existing);
            $liked = false;
        } else {
            $r = new Reaction();
            $r->setIdPost($id);
            $r->setUserId($this->getCurrentUserId());
            $r->setTypeReaction('like');
            $r->setDateReaction(new \DateTime());
            $em->persist($r);
            $liked = true;
        }
        $em->flush();


        $count = count($em->getRepository(Reaction::class)
            ->findBy(['idPost' => $id, 'typeReaction' => 'like']));


        return new JsonResponse(['liked' => $liked, 'count' => $count]);
    }


    // ════════════════════════════════════════════════
    //  POSTS CRUD
    // ════════════════════════════════════════════════


   
#[Route('/forum/categorie/{idCat}/post/new', name: 'forum_post_new', methods: ['GET','POST'])]
public function newPost(
    int $idCat,
    Request $request,
    EntityManagerInterface $em,
    SluggerInterface $slugger,
    ModerationService $moderation   // ← ajouter
): Response {
    $categorie = $em->getRepository(Categorie::class)->find($idCat);
    if (!$categorie) throw $this->createNotFoundException();


    $post = new Post();
    $form = $this->createForm(PostType::class, $post, ['attr' => ['novalidate' => 'novalidate']]);
    $form->handleRequest($request);


    if ($form->isSubmitted() && $form->isValid()) {


        // ── Vérification gros mots ──
        $texteAVerifier = $post->getTitre() . ' ' . $post->getContenu();
        if ($moderation->containsProfanity($texteAVerifier)) {
            $this->addFlash('error_moderation',
                '🚫 Votre post contient des termes inappropriés. Merci de le reformuler.');
            return $this->render('forum/post_form.html.twig', [
                'form'      => $form->createView(),
                'post'      => null,
                'categorie' => $categorie,
            ]);
        }


        // ── Gestion upload image ──
        $imageFile = $form->get('imageFile')->getData();
        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename     = $slugger->slug($originalFilename);
            $newFilename      = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
            try {
                $imageFile->move($this->getParameter('posts_images_directory'), $newFilename);
                $post->setImagePath($newFilename);
            } catch (\Exception $e) {}
        }


        $post->setIdCategorie($idCat);
        $post->setUserId($this->getCurrentUserId());
        $post->setDateCreation(new \DateTime());
        $em->persist($post);
        $em->flush();


        return $this->redirectToRoute('forum_posts', ['id' => $idCat]);
    }


    return $this->render('forum/post_form.html.twig', [
        'form'      => $form->createView(),
        'post'      => null,
        'categorie' => $categorie,
    ]);
}


#[Route('/forum/post/{id}/edit', name: 'forum_post_edit', methods: ['GET','POST'])]
public function editPost(
    int $id,
    Request $request,
    EntityManagerInterface $em,
    SluggerInterface $slugger,
    ModerationService $moderation
): Response {
    $post      = $em->getRepository(Post::class)->find($id);
    if (!$post) throw $this->createNotFoundException();
    $categorie = $em->getRepository(Categorie::class)->find($post->getIdCategorie());


    $form = $this->createForm(PostType::class, $post, ['attr' => ['novalidate' => 'novalidate']]);
    $form->handleRequest($request);


    if ($form->isSubmitted() && $form->isValid()) {




        // ── Gestion upload image ──
        $imageFile = $form->get('imageFile')->getData();
        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename     = $slugger->slug($originalFilename);
            $newFilename      = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();


            // ✅ APRÈS — retire $moderation = null
            try {
                $imageFile->move(
                    $this->getParameter('posts_images_directory'),
                    $newFilename
                );
                $post->setImagePath($newFilename);
            } catch (FileException $e) {
                // log si besoin
            }
        }
        // ── Vérification gros mots ──
        $texteAVerifier = $post->getTitre() . ' ' . $post->getContenu();
        //$moderation = new \App\Service\ModerationService();
        if ($moderation->containsProfanity($texteAVerifier)) {
            $this->addFlash('error_moderation',
                '🚫 Votre post contient des termes inappropriés. Merci de le reformuler.');
            return $this->render('forum/post_form.html.twig', [
                'form'      => $form->createView(),
                'post'      => $post,
                'categorie' => $categorie,
            ]);
        }


        $em->flush();
        return $this->redirectToRoute('forum_posts', ['id' => $post->getIdCategorie()]);
    }


    return $this->render('forum/post_form.html.twig', [
        'form'      => $form->createView(),
        'post'      => $post,
        'categorie' => $categorie,
    ]);
}
    #[Route('/forum/post/{id}/delete', name: 'forum_post_delete', methods: ['POST'])]
    public function deletePost(int $id, EntityManagerInterface $em): Response
    {
        $post = $em->getRepository(Post::class)->find($id);
        if ($post) {
            $idCat = $post->getIdCategorie();
            $em->remove($post);
            $em->flush();
            return $this->redirectToRoute('forum_posts', ['id' => $idCat]);
        }
        return $this->redirectToRoute('forum');
    }


    // ════════════════════════════════════════════════
    //  COMMENTAIRES
    // ════════════════════════════════════════════════


    // Après — ajouter ModerationService
    #[Route('/forum/post/{idPost}/comment/new', name: 'forum_comment_new', methods: ['POST'])]
    public function newComment(
        int $idPost,
        Request $request,
        EntityManagerInterface $em,
        ModerationService $moderation
    ): Response
{
    $post = $em->getRepository(Post::class)->find($idPost);
    if (!$post) throw $this->createNotFoundException();


    $contenu = trim($request->request->get('contenu', ''));
    // ── Validation longueur ──
    if (strlen($contenu) < 3) {
        // Flash avec l'ID du post pour afficher l'erreur sur le bon post
        $this->addFlash('comment_error_' . $idPost, 'Le commentaire doit contenir au moins 3 caractères.');
        return $this->redirectToRoute('forum_posts', [
            'id'       => $post->getIdCategorie(),
            'open_post' => $idPost,  // pour rouvrir la section commentaires
        ]);
    }
    // ── Vérification gros mots ──
    if ($moderation->containsProfanity($contenu)) {
        $this->addFlash('comment_error_' . $idPost,
            '🚫 Votre commentaire contient des termes inappropriés.');
        return $this->redirectToRoute('forum_posts', [
            'id'        => $post->getIdCategorie(),
            'open_post' => $idPost,
        ]);
    }


    $c = new Commentaire();
    $c->setContenu($contenu);
    $c->setIdPost($idPost);
    $c->setUserId($this->getCurrentUserId());
    $c->setDateCommentaire(new \DateTime());
    $em->persist($c);
    $em->flush();


    return $this->redirectToRoute('forum_posts', ['id' => $post->getIdCategorie()]);
}


    #[Route('/forum/comment/{id}/edit', name: 'forum_comment_edit', methods: ['GET','POST'])]
public function editComment(int $id, Request $request, EntityManagerInterface $em): Response
{
    $c = $em->getRepository(Commentaire::class)->find($id);
    if (!$c) throw $this->createNotFoundException();
    $post = $em->getRepository(Post::class)->find($c->getIdPost());


    $form = $this->createForm(CommentaireType::class, $c, ['attr' => ['novalidate' => 'novalidate']]);
    $form->handleRequest($request);


    if ($form->isSubmitted() && $form->isValid()) {
        $em->flush();
        return $this->redirectToRoute('forum_posts', ['id' => $post->getIdCategorie()]);
    }


    return $this->render('forum/comment_form.html.twig', [
        'form'    => $form->createView(),
        'comment' => $c,
        'post'    => $post,
    ]);
}


    #[Route('/forum/comment/{id}/delete', name: 'forum_comment_delete', methods: ['POST'])]
    public function deleteComment(int $id, EntityManagerInterface $em): Response
    {
        $c = $em->getRepository(Commentaire::class)->find($id);
        if ($c) {
            $post  = $em->getRepository(Post::class)->find($c->getIdPost());
            $idCat = $post ? $post->getIdCategorie() : null;
            $em->remove($c);
            $em->flush();
            if ($idCat) return $this->redirectToRoute('forum_posts', ['id' => $idCat]);
        }
        return $this->redirectToRoute('forum');
    }
}



