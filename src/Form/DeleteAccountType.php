<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

class DeleteAccountType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'profile_delete']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Votre mot de passe actuel',
                'row_attr' => ['class' => 'form-field'],
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [new Assert\NotBlank(), new UserPassword(message: 'Le mot de passe est incorrect.')],
            ])
            ->add('confirm', CheckboxType::class, [
                'label' => 'Je confirme la suppression définitive de mon compte et de mes ascensions.',
                'row_attr' => ['class' => 'form-checkbox'],
                'constraints' => [new Assert\IsTrue(message: 'Confirmez la suppression de votre compte.')],
            ]);
    }
}
