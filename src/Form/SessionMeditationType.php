<?php

namespace App\Form;

use App\Entity\SessionMeditation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SessionMeditation>
 */
class SessionMeditationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('theme', TextType::class, [
                'label' => 'Thème',
                'attr'  => ['placeholder' => 'Thème de la séance...'],
                'empty_data' => '',
            ])
            ->add('auteur', TextType::class, [
                'label' => 'Auteur',
                'attr'  => ['placeholder' => "Nom de l'auteur..."],
                'empty_data' => '',
            ])
            ->add('duree', IntegerType::class, [
                'label' => 'Durée (minutes)',
                'attr'  => ['min' => 5, 'max' => 60, 'placeholder' => '5-60 min'],
            ])
            ->add('audioUrl', UrlType::class, [
                'label'    => 'Lien YouTube',
                'attr'     => ['placeholder' => 'https://www.youtube.com/...'],
                'empty_data' => '',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SessionMeditation::class]);
    }
}
