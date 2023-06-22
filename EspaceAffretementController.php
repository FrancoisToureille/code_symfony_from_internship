<?php

namespace App\Controller;

use App\DTO\ChangePassword;
use App\Entity\Compte;
use App\Form\CompteType;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class EspaceAffretementController extends AbstractController
{
    #[Route('/profile/{_locale}', name: 'profile')]
    public function profile(EntityManagerInterface $entityManager): Response
    {
        $compte = $entityManager->getRepository(Compte::class)->find($this->getUser()->getId());
        return $this->render('espace_affretement/profile.html.twig', ['compte' => $compte]);
    }

    #[Route('/profile/change-password/{_locale}', name: 'profile_change_password')]
    public function profileChangePassword(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, TranslatorInterface $translator): Response
    {
        $compte = $entityManager->getRepository(Compte::class)->find($this->getUser()->getId());
        $changePassword = new ChangePassword();
        $form = $this->createForm(CompteType::class, $changePassword, ['attr' => ['id' => 'formulaire-profil']]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** Vérification du mot de passe actuel */
            if (!$passwordHasher->isPasswordValid($compte, $form->get('oldPassword')->getData())) {
                $form->get('oldPassword')->addError(new FormError($translator->trans('badPassword')));
                return $this->render('espace_affretement/profile_change_password.html.twig', ['form' => $form->createView(), 'compte' => $compte]);
            }
            try {
                $compte->setPassword($passwordHasher->hashPassword($compte, $changePassword->getNewPassword()));
                $entityManager->persist($compte);
                $entityManager->flush();
                return $this->redirectToRoute("profile", ["id" => $compte->getId(), "afficher" => true]);
            } catch (Exception $e) {
                $form->addError(new FormError('Le changement de mot de passe a échoué. Veuillez réessayer.'));
            }
        }
        $entityManager->flush();
        return $this->render('espace_affretement/profile_change_password.html.twig', ['form' => $form->createView(), 'compte' => $compte]);
    }
}
