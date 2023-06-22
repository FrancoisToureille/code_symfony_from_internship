<?php

namespace App\Form;

use App\Entity\Document;
use App\Entity\TypeDocument;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Contracts\Translation\TranslatorInterface;

class DocumentDepotType extends AbstractType
{
    private TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('validityEnd', DateType::class, ['label' => 'Fin de validité', 'widget' => 'single_text'])
            ->add('type', EntityType::class, ['class' => TypeDocument::class, 'choice_label' => function ($type) {
                if ($type->isMandatory())
                    return $this->translator->trans($type->getLabel()) . " *";
                return $this->translator->trans($type->getLabel());
            }, 'attr' => ['class' => 'div-type']])
            ->add('brochure', FileType::class, [
                'label' => 'Fichier PDF',

                // unmapped means that this field is not associated to any entity property
                'mapped' => false,

                // make it optional so you don't have to re-upload the PDF file
                // every time you edit the Product details
                'required' => true,

                // unmapped fields can't define their validation using annotations
                // in the associated entity, so you can use the PHP constraint classes
                'constraints' => [
                    new File([
                        'maxSize' => '10000k',
                        'mimeTypes' => [
                            'application/pdf',
                            'application/x-pdf',
                        ],
                        'mimeTypesMessage' => $this->translator->trans('form.brochure.constraints.mimeTypesMessage'),
                        'maxSizeMessage' => $this->translator->trans('form.brochure.constraints.maxSizeMessage'),
                    ])
                ],
            ])
            ->add('sauvegarder', SubmitType::class, [
                'attr' => ['class' => 'btn btn-success'],
            ]);// ...
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
        ]);
    }
}
