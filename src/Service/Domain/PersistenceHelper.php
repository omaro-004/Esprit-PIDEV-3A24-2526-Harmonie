<?php

namespace App\Service\Domain;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
trait PersistenceHelper
{
    abstract protected function getEntityManager(): EntityManagerInterface;

    abstract protected function getValidator(): ValidatorInterface;

    /** Validation Symfony (Assert sur entités / Form) — en plus des règles métier */
    protected function validateEntity(object $entity): void
    {
        $violations = $this->getValidator()->validate($entity);
        if (\count($violations) > 0) {
            $messages = [];
            foreach ($violations as $violation) {
                $messages[] = $violation->getMessage();
            }
            throw new \DomainException(implode(', ', $messages));
        }
    }

    protected function persistAndFlush(object $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    protected function removeAndFlush(object $entity): void
    {
        $this->getEntityManager()->remove($entity);
        $this->getEntityManager()->flush();
    }
}
