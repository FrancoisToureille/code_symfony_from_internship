<?php

namespace App\Form;

use App\DTO\ChangePassword;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CompteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('oldPassword', PasswordType::class, ['label' => 'Mot de passe actuel', 'label_attr' => ['class' => 'label-bottom']])
            ->add('newPassword', PasswordType::class, ['label' => 'Nouveau mot de passe', 'label_attr' => ['class' => 'label-bottom']])
            ->add('confirmPassword', PasswordType::class, ['label' => 'Confirmer mot de passe', 'label_attr' => ['class' => 'label-bottom']])
            ->add('submit', SubmitType::class, ['label' => 'Modifier', 'attr' => ['class' => 'btn btn-block btn-success btn-valider']]);

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ChangePassword::class,
        ]);
    }
}
