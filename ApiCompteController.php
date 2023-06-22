<?php

namespace App\Controller;

use App\Entity\Affrete;
use App\Entity\Compte;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ApiCompteController extends AbstractController
{
    #[Route('/api/compte/exists', name: 'api_account_exists', methods: ['GET'])]
    public function accountExists(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$request->query->has('email'))
            return $this->json(["message" => "l'email est manquant"], 400, []);
        $email = $request->query->get('email');
        $compte = $entityManager->getRepository(Compte::class)->findOneBy(['email' => $email]);
        if ($compte == null) {
            return $this->json(["accountExists" => false], 200, []);
        } else {
            return $this->json(["accountExists" => true], 200, []);
        }
    }

    #[Route('/api/compte/insert-delete/{_locale}', name: 'api_account_insert_delete', methods: ['POST', 'DELETE'])]
    public function insertDeleteAccountManual(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, TranslatorInterface $translator, MailerInterface $mailer)
    {
        $content = json_decode($request->getContent(), true);
        /** verifier que les champs sont remplis */
        if (!isset($content['action'], $content['emailContact'], $content['idTiers']))
            return $this->json(["message" => "Veuillez remplir tous les champs"], 400, []);

        //return $this->json($content, 200, []);
        if (!strpos($content['emailContact'], '@'))
            return $this->json(["message" => "Veuillez fournir un email correct"], 400, []);

        $affreteExists = $entityManager->getRepository(Affrete::class)->findOneBy(['label' => $content['idTiers']]);
        if ($affreteExists == null)
            return $this->json(["message" => "Affreté inexistant"], 400, []);

        $accountExists = $entityManager->getRepository(Compte::class)->findOneBy(['email' => $content['emailContact']]);

        /** cas de suppression */
        if ($content['action'] == "Supprimer" && !empty($accountExists)) {
            $entityManager->remove($accountExists);
            $entityManager->flush();
            return $this->json(["message" => "Compte supprimé"], 200, []);
        } /** cas d'insertion */
        elseif ($content['action'] == "Ajouter" && empty($accountExists)) {
            $compte = new Compte();
            $compte->setEmail($content['emailContact'])
                ->setPassword($passwordHasher->hashPassword($compte, bin2hex(random_bytes(15))))
                ->setAffrete($affreteExists);
            $entityManager->persist($compte);
            $entityManager->flush();
            $message = "http://espacetest.local/forgot/password/" . $affreteExists->getLangue();
            $mail = (new Email())
                ->from('a4a392186a-d2a4c7+1@inbox.mailtrap.io')
                ->to($content['emailContact'])
                ->subject($translator->trans('subjectBeginning') . $translator->trans('creationAccount') . $translator->trans('inCharteringSpace'))
                ->html($translator->trans('mailBeginning') . '<p>' . $translator->trans('mailCreationFirstSentence') . '
 </p><p>' . $translator->trans('mailCreationSecondSentence') . ' <a href=' . $message . '>' . $translator->trans('clickingHere') . '</a></p>');
            $mailer->send($mail);
            //envoyer mail au compte
            return $this->json(["message" => "Compte ajouté"], 200, []);

        } /** cas d'erreur : exemple: action = Ajouter mais l'affrété existe*/
        else {
            return $this->json(["message" => "action impossible"], 400, []);
        }
    }
}