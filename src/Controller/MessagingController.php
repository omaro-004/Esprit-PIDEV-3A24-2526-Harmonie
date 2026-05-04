<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[IsGranted('ROLE_USER')]
#[Route('/api/messaging')]
class MessagingController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ConversationRepository $convRepo,
        private readonly MessageRepository      $msgRepo,
        private readonly UserRepository         $userRepo,
        private readonly HubInterface          $hub,
    ) {}

    #[Route('/conversations', name: 'api_messaging_conversations', methods: ['GET'])]
    public function conversations(): JsonResponse
    {
        /** @var User $me */
        $me    = $this->getUser();
        $convs = $this->convRepo->findForUser($me);

        $data = array_map(function ($conv) use ($me) {
            $other   = $conv->getOtherUser($me);
            $msgs    = $conv->getMessages();
            $lastMsg = $msgs->count() > 0 ? $msgs->last() : null;
            $unread  = $conv->countUnread($me);

            return [
                'convId'       => $conv->getId(),
                'otherId'      => $other->getUserId(),
                'otherName'    => $other->getUserPrenom() . ' ' . $other->getUserNom(),
                'otherAvatar'  => $other->getUserImagePath(),
                'otherInitial' => strtoupper(mb_substr($other->getUserPrenom(), 0, 1)),
                'lastMessage'  => $lastMsg ? $lastMsg->getContent() : '',
                'lastAt'       => $lastMsg ? $lastMsg->getSentAt()->format('H:i') : '',
                'unread'       => $unread,
            ];
        }, $convs);

        return new 
        JsonResponse($data);
    }

    #[Route('/conversations/{id}/messages', name: 'api_messaging_messages', methods: ['GET'])]
    public function messages(int $id): JsonResponse
    {
        /** @var User $me */
        $me   = $this->getUser();
        $conv = $this->convRepo->find($id);

        if (!$conv
            || ($conv->getUser1()->getUserId() !== $me->getUserId()
                && $conv->getUser2()->getUserId() !== $me->getUserId())) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $this->msgRepo->markAsRead($conv, $me);
        $this->em->flush();

        $messages = $this->msgRepo->findByConversation($conv);
        $other    = $conv->getOtherUser($me);

        return new JsonResponse([
            'convId'       => $conv->getId(),
            'otherId'      => $other->getUserId(),
            'otherName'    => $other->getUserPrenom() . ' ' . $other->getUserNom(),
            'otherAvatar'  => $other->getUserImagePath(),
            'otherInitial' => strtoupper(mb_substr($other->getUserPrenom(), 0, 1)),
            'messages'     => array_map(fn(Message $m) => $m->toArray(), $messages),
        ]);
    }

    #[Route('/send', name: 'api_messaging_send', methods: ['POST'])]
    public function send(Request $request): JsonResponse
    {
        // LOG DIRECT — à supprimer après debug
        file_put_contents(
            __DIR__ . '/../../var/log/send_debug.log',
            date('Y-m-d H:i:s') . ' | Method: ' . $request->getMethod()
            . ' | Body: ' . $request->getContent()
            . ' | User: ' . ($this->getUser() ? $this->getUser()->getUserIdentifier() : 'none')
            . "\n",
            FILE_APPEND
        );

        try {
            /** @var User $me */
            $me   = $this->getUser();
            $data = json_decode($request->getContent(), true);

            $recipientId = (int)($data['recipientId'] ?? 0);
            $content     = trim($data['content'] ?? '');

            if (!$content || !$recipientId) {
                return new JsonResponse(['error' => 'Invalid payload'], 400);
            }

            $recipient = $this->userRepo->find($recipientId);
            if (!$recipient) {
                return new JsonResponse(['error' => 'Recipient not found: ' . $recipientId], 404);
            }
            if ($recipient->getUserId() === $me->getUserId()) {
                return new JsonResponse(['error' => 'Cannot send to yourself'], 400);
            }

            $conv = $this->convRepo->findOrCreate($me, $recipient, $this->em);

            file_put_contents(
                __DIR__ . '/../../var/log/send_debug.log',
                date('Y-m-d H:i:s') . ' | Conv ID: ' . $conv->getId() . "\n",
                FILE_APPEND
            );

            $msg = (new Message())
                ->setConversation($conv)
                ->setSender($me)
                ->setContent($content);

            $this->em->persist($msg);
            $conv->setUpdatedAt(new \DateTime());
            $this->em->flush();

            $payload = [
                'id'       => $msg->getId(),
                'convId'   => $conv->getId(),
                'senderId' => $me->getUserId(),
                'content'  => $msg->getContent(),
                'sentAt'   => $msg->getSentAt()->format('H:i'),
            ];

            try {
                $this->hub->publish(new Update(
                    'user/' . $recipient->getUserId() . '/messages',
                    json_encode($payload, JSON_THROW_ON_ERROR),
                ));

                // Keep sender tabs in sync too.
                $this->hub->publish(new Update(
                    'user/' . $me->getUserId() . '/messages',
                    json_encode($payload, JSON_THROW_ON_ERROR),
                ));
            } catch (\Throwable $mercureError) {
                file_put_contents(
                    __DIR__ . '/../../var/log/send_debug.log',
                    date('Y-m-d H:i:s') . ' | MERCURE ERROR: ' . $mercureError->getMessage() . "\n",
                    FILE_APPEND
                );
            }

            file_put_contents(
                __DIR__ . '/../../var/log/send_debug.log',
                date('Y-m-d H:i:s') . ' | Message saved, ID: ' . $msg->getId() . "\n",
                FILE_APPEND
            );

            return new JsonResponse([
                'id'             => $msg->getId(),
                'conversationId' => $conv->getId(),
                'senderId'       => $me->getUserId(),
                'content'        => $msg->getContent(),
                'sentAt'         => $msg->getSentAt()->format('H:i'),
                'isRead'         => false,
            ], 201);

        } catch (\Throwable $e) {
            file_put_contents(
                __DIR__ . '/../../var/log/send_debug.log',
                date('Y-m-d H:i:s') . ' | EXCEPTION: ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n",
                FILE_APPEND
            );
            return new JsonResponse([
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'file'  => basename($e->getFile()),
                'line'  => $e->getLine(),
            ], 500);
        }
    }

    #[Route('/test-send', name: 'api_messaging_test', methods: ['GET'])]
    public function testSend(): JsonResponse
    {
        try {
            /** @var User $me */
            $me = $this->getUser();

            // Test 1 : DB accessible ?
            $users = $this->userRepo->findBy([], [], 10); // Limit test query to 10 users

            // Test 2 : EntityManager ok ?
            $this->em->getConnection()->executeQuery('SELECT 1');

            return new JsonResponse([
                'status'    => 'ok',
                'user'      => $me->getUserEmail(),
                'userCount' => count($users),
                'db'        => 'connected',
            ]);

        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ], 500);
        }
    }
    #[Route('/debug-send', name: 'api_messaging_debug', methods: ['POST'])]
    public function debugSend(Request $request): JsonResponse
    {
        $rawContent  = $request->getContent();
        $contentType = $request->headers->get('Content-Type');
        $data        = json_decode($rawContent, true);

        return new JsonResponse([
            'raw'         => $rawContent,
            'contentType' => $contentType,
            'decoded'     => $data,
            'method'      => $request->getMethod(),
        ]);
    }

    #[Route('/search-users', name: 'api_messaging_search_users', methods: ['GET'])]
    public function searchUsers(Request $request): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        $q  = trim($request->query->get('q', ''));

        if (strlen($q) < 2) {
            return new JsonResponse([]);
        }

        $users = array_filter(
            $this->userRepo->searchByName($q),
            fn(User $u) => $u->getUserId() !== $me->getUserId() && $u->isActive()
        );

        return new JsonResponse(array_values(array_map(fn(User $u) => [
            'id'      => $u->getUserId(),
            'name'    => $u->getUserPrenom() . ' ' . $u->getUserNom(),
            'avatar'  => $u->getUserImagePath(),
            'initial' => strtoupper(mb_substr($u->getUserPrenom(), 0, 1)),
        ], $users)));
    }

    #[Route('/unread-count', name: 'api_messaging_unread_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        return new JsonResponse(['count' => $this->msgRepo->countUnreadForUser($me)]);
    }

    #[Route('/mercure-token', name: 'api_messaging_mercure_token', methods: ['GET'])]
    public function mercureToken(): JsonResponse
    {
        /** @var User $me */
        $me  = $this->getUser();
        $key = $_ENV['MERCURE_JWT_SECRET'] ?? 'harmony-secret-key-change-in-prod';

        $b64url = static fn(string $data): string =>
        rtrim(strtr(base64_encode($data), '+/', '-_'), '=');

        $header  = $b64url((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $b64url((string) json_encode([
            'mercure' => ['subscribe' => ['user/' . $me->getUserId() . '/messages']]
        ]));
        $sig = $b64url(hash_hmac('sha256', "$header.$payload", $key, true));

        return new JsonResponse(['token' => "$header.$payload.$sig"]);
    }
}
