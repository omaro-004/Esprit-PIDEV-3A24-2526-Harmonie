<?php

namespace App\Form;

use App\Entity\Tache;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TacheType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'required' => true,
                'attr' => [
                    'class' => 'harmony-input',
                    'placeholder' => 'Ex. Rendre le rapport, préparer la présentation…',
                    'maxlength' => 255,
                    'autocomplete' => 'off',
                ],
                'label_attr' => ['class' => 'harmony-label'],
            ])
            ->add('deadline', DateType::class, [
                'label' => 'Échéance',
                'required' => true,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'harmony-input',
                ],
                'label_attr' => ['class' => 'harmony-label'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => [
                    'class' => 'harmony-textarea',
                    'placeholder' => 'Détails, rappels, liens utiles…',
                    'rows' => 5,
                ],
                'label_attr' => ['class' => 'harmony-label'],
            ])
            ->add('statutTache', ChoiceType::class, [
                'label' => 'Statut',
                'required' => true,
                'choices' => [
                    'TODO' => 'A_FAIRE',
                    'DOING' => 'EN_COURS',
                    'DONE' => 'TERMINEE',
                ],
                'attr' => [
                    'class' => 'harmony-select',
                ],
                'label_attr' => ['class' => 'harmony-label'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => ['class' => 'harmony-btn-submit'],
                'row_attr' => ['class' => 'harmony-form-row-submit'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tache::class,
        ]);
    }
}
