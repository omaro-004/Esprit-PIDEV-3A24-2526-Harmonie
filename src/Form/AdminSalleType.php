<?php

namespace App\Form;

use App\Entity\Salle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AdminSalleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['class' => 'harmony-input', 'placeholder' => 'Ex. Amphi 1, Salle A101'],
                'label_attr' => ['class' => 'harmony-label'],
            ])
            ->add('capacite', IntegerType::class, [
                'label' => 'Capacité',
                'attr' => ['class' => 'harmony-input', 'min' => 1],
                'label_attr' => ['class' => 'harmony-label'],
            ])
            ->add('disponible', CheckboxType::class, [
                'label' => 'Disponible',
                'required' => false,
                'attr' => ['class' => 'harmony-toggle-input'],
                'label_attr' => ['class' => 'harmony-toggle-ui'],
                'row_attr' => ['class' => 'harmony-field harmony-toggle-field'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['class' => 'harmony-textarea', 'rows' => 4],
                'label_attr' => ['class' => 'harmony-label'],
            ])
            ->add('equipements', TextareaType::class, [
                'label' => 'Équipements',
                'required' => false,
                'attr' => ['class' => 'harmony-textarea', 'rows' => 3],
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
            'data_class' => Salle::class,
        ]);
    }
}
