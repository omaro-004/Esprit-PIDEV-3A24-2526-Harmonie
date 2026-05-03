<?php

namespace App\Form;

use App\Entity\JournalHumeur;
use App\Enum\Humeur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JournalHumeurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('humeur', EnumType::class, [
                'class'        => Humeur::class,
                'label'        => 'Comment vous sentez-vous ?',
                'choice_label' => fn(Humeur $h) => $h->emoji() . ' ' . $h->label(),
                'placeholder'  => '-- Choisir une humeur --',
            ])
            ->add('dateJournal', DateType::class, [
                'label'  => 'Date',
                'widget' => 'single_text',
            ])
            ->add('contenu', TextareaType::class, [
                'label' => 'Vos pensées...',
                'attr'  => [
                    'placeholder' => 'Décrivez votre journée, vos émotions...',
                    'rows' => 6,
                    'maxlength' => 1000,
                ],
                'empty_data' => '',
            ])
            ->add('avatarImageUrl', HiddenType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => JournalHumeur::class]);
    }
}
