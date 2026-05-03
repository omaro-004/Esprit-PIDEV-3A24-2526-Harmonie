<?php

namespace App\Tests\Form;

use App\Entity\User;
use App\Form\RegistrationStep2FormType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Validator\Validation;

class RegistrationStep2FormTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $validator = Validation::createValidator();
        return [new ValidatorExtension($validator)];
    }

    public function testDataClassEtValidationGroup(): void
    {
        $form = $this->factory->create(RegistrationStep2FormType::class);
        $this->assertSame(User::class, $form->getConfig()->getOption('data_class'));

        $groups = $form->getConfig()->getOption('validation_groups');
        $this->assertContains('step2', $groups);
    }

    public function testContientChampsEtapes(): void
    {
        $form = $this->factory->create(RegistrationStep2FormType::class);

        $expected = [
            'avatarFile',
            'userSexe',
            'userPoids',
            'userTaille',
            'userNiveauActivitePhysique',
            'userNiveauScolaire',
            'userEtablissementScolaire',
        ];

        foreach ($expected as $name) {
            $this->assertTrue($form->has($name));
        }
    }

    public function testAvatarFileEstNonMappeEtOptionnel(): void
    {
        $form = $this->factory->create(RegistrationStep2FormType::class);
        $config = $form->get('avatarFile')->getConfig();

        $this->assertFalse($config->getOption('mapped'));
        $this->assertFalse($config->getOption('required'));
        $this->assertInstanceOf(FileType::class, $config->getType()->getInnerType());

        $constraints = $config->getOption('constraints');
        $this->assertCount(1, array_filter($constraints, fn ($c) => $c instanceof File));
    }

    public function testPoidsAContraintesMinMax(): void
    {
        $form = $this->factory->create(RegistrationStep2FormType::class);
        $constraints = $form->get('userPoids')->getConfig()->getOption('constraints');

        $this->assertCount(1, array_filter($constraints, fn ($c) => $c instanceof GreaterThanOrEqual));
        $this->assertCount(1, array_filter($constraints, fn ($c) => $c instanceof LessThanOrEqual));
    }

    public function testTailleAContraintesMinMax(): void
    {
        $form = $this->factory->create(RegistrationStep2FormType::class);
        $constraints = $form->get('userTaille')->getConfig()->getOption('constraints');

        $this->assertCount(1, array_filter($constraints, fn ($c) => $c instanceof GreaterThanOrEqual));
        $this->assertCount(1, array_filter($constraints, fn ($c) => $c instanceof LessThanOrEqual));
    }

    public function testNiveauActiviteChoixAttendues(): void
    {
        $form = $this->factory->create(RegistrationStep2FormType::class);
        $choices = $form->get('userNiveauActivitePhysique')->getConfig()->getOption('choices');
        $values = array_values($choices);

        $this->assertContains('SEDENTAIRE', $values);
        $this->assertContains('LEGER', $values);
        $this->assertContains('MODERE', $values);
        $this->assertContains('INTENSE', $values);
        $this->assertContains('TRES_INTENSE', $values);
        $this->assertInstanceOf(ChoiceType::class, $form->get('userNiveauActivitePhysique')->getConfig()->getType()->getInnerType());
    }
}
