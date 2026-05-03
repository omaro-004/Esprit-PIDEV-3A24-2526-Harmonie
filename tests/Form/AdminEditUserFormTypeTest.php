<?php

namespace App\Tests\Form;

use App\Entity\User;
use App\Form\AdminEditUserFormType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Validator\Validation;

class AdminEditUserFormTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $validator = Validation::createValidator();
        return [new ValidatorExtension($validator)];
    }

    public function testDataClassUser(): void
    {
        $form = $this->factory->create(AdminEditUserFormType::class);
        $this->assertSame(User::class, $form->getConfig()->getOption('data_class'));
    }

    public function testContientTousLesChamps(): void
    {
        $form = $this->factory->create(AdminEditUserFormType::class);

        $expected = [
            'userNom',
            'userPrenom',
            'userEmail',
            'userDateDeNaissance',
            'userSexe',
            'userPoids',
            'userTaille',
            'userNiveauActivitePhysique',
            'userNiveauScolaire',
            'userEtablissementScolaire',
            'typeUtilisateur',
            'isActive',
            'avatarFile',
        ];

        foreach ($expected as $name) {
            $this->assertTrue($form->has($name));
        }
    }

    public function testAvatarFileEstNonMappeEtOptionnel(): void
    {
        $form = $this->factory->create(AdminEditUserFormType::class);
        $config = $form->get('avatarFile')->getConfig();

        $this->assertFalse($config->getOption('mapped'));
        $this->assertFalse($config->getOption('required'));
        $this->assertInstanceOf(FileType::class, $config->getType()->getInnerType());
    }

    public function testAvatarFileAUneContrainteFile(): void
    {
        $form = $this->factory->create(AdminEditUserFormType::class);
        $constraints = $form->get('avatarFile')->getConfig()->getOption('constraints');

        $fileConstraints = array_filter($constraints, fn ($c) => $c instanceof File);
        $this->assertCount(1, $fileConstraints);
    }

    public function testChoixSexeContiennentValeursAttendues(): void
    {
        $form = $this->factory->create(AdminEditUserFormType::class);
        $choices = $form->get('userSexe')->getConfig()->getOption('choices');
        $values = array_values($choices);

        $this->assertContains('HOMME', $values);
        $this->assertContains('FEMME', $values);
        $this->assertContains('AUTRE', $values);
        $this->assertInstanceOf(ChoiceType::class, $form->get('userSexe')->getConfig()->getType()->getInnerType());
    }

    public function testChoixRoleEtStatutSontPresentes(): void
    {
        $form = $this->factory->create(AdminEditUserFormType::class);

        $roleValues = array_values($form->get('typeUtilisateur')->getConfig()->getOption('choices'));
        $this->assertContains('ETUDIANT', $roleValues);
        $this->assertContains('ADMIN', $roleValues);

        $statusValues = array_values($form->get('isActive')->getConfig()->getOption('choices'));
        $this->assertContains(true, $statusValues);
        $this->assertContains(false, $statusValues);
    }
}
