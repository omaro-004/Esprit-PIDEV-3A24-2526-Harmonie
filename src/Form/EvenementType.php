<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\Salle;
use App\Entity\User;
use App\Repository\SalleRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Evenement>
 */
class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex. Réunion projet, Examen, Soirée…',
                ],
            ])
            ->add('dateDebut', DateTimeType::class, [
                'label' => 'Date de début',
                'required' => true,
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('dateFin', DateTimeType::class, [
                'label' => 'Date de fin',
                'required' => true,
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('eventType', ChoiceType::class, [
                'label' => 'Type d\'événement',
                'required' => true,
                'choices' => [
                    'Cours' => 'cours',
                    'Réunion' => 'reunion',
                    'Loisir' => 'loisir',
                    'Autre' => 'autre',
                ],
                'placeholder' => '— Choisir —',
                'attr' => ['class' => 'form-control js-event-type-select'],
            ])
            ->add('lieuType', ChoiceType::class, [
                'label' => 'Mode',
                'required' => false,
                'choices' => [
                    'Présentiel' => 'presentiel',
                    'En ligne' => 'en_ligne',
                ],
                'expanded' => true,
                'attr' => ['class' => 'js-lieu-type'],
            ])
            ->add('lieuAdresse', TextType::class, [
                'label' => 'Où ?',
                'required' => false,
                'attr' => [
                    'class' => 'form-control js-lieu-adresse',
                    'placeholder' => 'Adresse, campus, ou tapez "esprit" pour les salles…',
                ],
            ])
            ->add('salle', EntityType::class, [
                'class' => Salle::class,
                'label' => 'Salle',
                'required' => false,
                'placeholder' => '— Sélectionner une salle —',
                'choice_label' => static fn (Salle $s): string => $s->getNom().' · '.$s->getCapacite().' pers.',
                'attr' => ['class' => 'form-control js-salle-select'],
                'query_builder' => static function (SalleRepository $r) {
                    return $r->createQueryBuilder('s')
                        ->andWhere('s.disponible = :d')
                        ->setParameter('d', true)
                        ->orderBy('s.nom', 'ASC');
                },
            ])
            ->add('rappelActif', CheckboxType::class, [
                'label'    => 'Activer le rappel Telegram',
                'required' => false,
            ])
            ->add('reminderMinutes', ChoiceType::class, [
                'label'   => 'Rappel avant l\'événement',
                'required' => false,
                'choices' => [
                    '5 minutes'  => 5,
                    '10 minutes' => 10,
                    '15 minutes' => 15,
                    '30 minutes' => 30,
                    '1 heure'    => 60,
                    '2 heures'   => 120,
                    '1 jour'     => 1440,
                ],
                'attr' => ['class' => 'form-control'],
            ]);

        if ($options['admin_mode']) {
            $builder
                ->add('proprietaire', EntityType::class, [
                    'class' => User::class,
                    'label' => 'Utilisateur (propriétaire)',
                    'required' => false,
                    'placeholder' => '— Aucun —',
                    'choice_label' => static fn (User $u): string => $u->getUserPrenom().' '.$u->getUserNom().' ('.$u->getUserEmail().')',
                    'attr' => ['class' => 'form-control'],
                ]);
        }

        $builder->add('submit', SubmitType::class, [
            'label' => 'Enregistrer',
            'attr' => ['class' => 'btn btn-primary'],
        ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!\is_array($data)) {
                return;
            }

            if (($data['lieuType'] ?? '') === 'en_ligne') {
                $data['salle'] = null;
                $data['lieuAdresse'] = null;
            } elseif (($data['lieuType'] ?? '') === 'presentiel' && !empty($data['salle'])) {
                $data['lieuAdresse'] = null;
            }

            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
            'admin_mode' => false,
        ]);
        $resolver->setAllowedTypes('admin_mode', 'bool');
    }
}
