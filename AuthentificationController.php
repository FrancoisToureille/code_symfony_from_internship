<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthentificationController extends AbstractController
{
    #[Route('/logout', name: 'logout')]
    public function logout()
    {
    }

    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->redirectToRoute('login');
    }

    /** login traduit */
    #[Route('/{_locale}', name: 'login')]
    public function loginLangue(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            /** une fois connecté on arrive sur la page "mes documents"  */
            $request->getSession()->set('message', null);
            return $this->redirectToRoute("mes_documents");
        }
        if ($request->getSession()->get('message') != null) {
            $message = $request->getSession()->get('message');
            $request->getSession()->remove('message');
            return $this->render('espace_affretement/index.html.twig', ['message' => $message, 'langue' => $request->getLocale()]);
        }
        if ($authenticationUtils->getLastAuthenticationError() != null) {
            $message = $request->getSession()->get('message');
            $request->getSession()->remove('message');
            return $this->render('espace_affretement/index.html.twig', ['messageErreurLogin' => $message, 'langue' => $request->getLocale()]);
        }

        return $this->render('espace_affretement/index.html.twig', ['langue' => $request->getLocale()]);
    }
}