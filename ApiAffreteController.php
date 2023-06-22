<?php

namespace App\Controller;

use App\Entity\Affrete;
use App\Entity\Compte;
use App\Entity\Document;
use App\Entity\Historique;
use App\Entity\TypeDocument;
use App\Repository\AffreteRepository;
use App\Repository\CompteRepository;
use App\Repository\DocumentRepository;
use App\Repository\TypeDocumentRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;


class ApiAffreteController extends AbstractController
{
    #[Route('/api/affretes/list-code-tiers', name: 'api_liste_affretes_code', methods: ['GET'])]
    public function listLabelAffretes(EntityManagerInterface $entityManager): Response
    {
        $affretes = $entityManager->getRepository(Affrete::class)->findAll();
        $affretesLabel = [];
        for ($i = 0; $i < count($affretes); $i++) {
            $affrete = $affretes[$i];
            $affretesLabel[] = $affrete->getLabel();
        }
        return $this->json($affretesLabel, 200, []);
    }

    #[Route('/api/affretes', name: 'api_liste_affretes', methods: ['GET'])]
    public function listAffretes(Request $request, EntityManagerInterface $entityManager): Response
    {
        $types = $entityManager->getRepository(TypeDocument::class)->findBy(['mandatory' => true]);
        $label = $request->query->get('label');
        $companyName = $request->query->get('companyName');
        $page = 1;
        $perPage = 10000;
        $affretes = $entityManager->getRepository(Affrete::class)->findByFilters($label, $companyName, $page, $perPage);
        $maListe = [];

        $dateMin = new \DateTime('today');
        for ($j = 0; $j < count($affretes); $j++) {
            $affrete = $affretes[$j];
            $documentsPersonne = 0;
            $personneKO = false;
            for ($i = 0; $i < count($types); $i++) {
                $type = $types[$i];
                $documentsValides = $entityManager->getRepository(Document::class)->findDocumentsEtatType($affrete->getId(), $type->getId(), $dateMin, "valide");
                $documentsInvalides = $entityManager->getRepository(Document::class)->findDocumentsEtatType($affrete->getId(), $type->getId(), $dateMin, "invalide");
                $documentAttente = $entityManager->getRepository(Document::class)->findDocumentsEtatType($affrete->getId(), $type->getId(), $dateMin, "attente");
                if ($documentsValides) {
                    $documentsPersonne++;
                } elseif ($documentsInvalides || $documentAttente == []) {
                    $personneKO = true;
                }
            }
            $nombreDocumentsAttente = count($entityManager->getRepository(Document::class)->findDocumentsEtat($affrete->getId(), $dateMin, "attente"));

            // Calcul de l'état de l'affrété
            $etatAffrete = null;
            if ($documentsPersonne == count($types)) {
                $etatAffrete = "OK";
            } elseif ($personneKO == true) {
                $etatAffrete = "KO";
            } else {
                $etatAffrete = "POK";
            }

            // Ajout des filtres
            $filter = $request->query->get('etatAffrete');
            if ($filter) {
                if ($filter === 'OK' && $etatAffrete !== 'OK') {
                    continue; // Ignorer les affrètements qui ne sont pas dans l'état "OK"
                }
                if ($filter === 'POK' && $etatAffrete !== 'POK') {
                    continue; // Ignorer les affrètements qui ne sont pas dans l'état "POK"
                }
                if ($filter === 'KO' && $etatAffrete !== 'KO') {
                    continue; // Ignorer les affrètements qui ne sont pas dans l'état "KO"
                }
            }
            $maListe[$j] = ['affrete' => $affrete, 'etatAffrete' => $etatAffrete, 'docAttente' => $nombreDocumentsAttente];
        }
        $nb = count($maListe);
        $maListe = array_slice($maListe, ($request->query->getInt('page') - 1) * $request->query->getInt('perPage', 10), $request->query->getInt('perPage', 10));

        return $this->json([$maListe, $nb], 200, [], ['groups' => 'affrete:read']);
    }

    #[Route('/api/affretes/statistics', name: 'api_liste_affretes_statistics', methods: ['GET'])]
    public function statisticsAffretes(Request $request, EntityManagerInterface $entityManager): Response
    {
        $types = $entityManager->getRepository(TypeDocument::class)->findBy(['mandatory' => true]);
        $affretes = $entityManager->getRepository(Affrete::class)->findAll();
        $nbOk = 0;
        $nbPok = 0;
        $nbKo = 0;

        $dateMin = new \DateTime('today');
        for ($j = 0; $j < count($affretes); $j++) {
            $affrete = $affretes[$j];
            $documentsPersonne = 0;
            $personneKO = false;
            for ($i = 0; $i < count($types); $i++) {
                $type = $types[$i];
                $documentsValides = $entityManager->getRepository(Document::class)->findDocumentsEtatType($affrete->getId(), $type->getId(), $dateMin, "valide");
                $documentsInvalides = $entityManager->getRepository(Document::class)->findDocumentsEtatType($affrete->getId(), $type->getId(), $dateMin, "invalide");
                $documentAttente = $entityManager->getRepository(Document::class)->findDocumentsEtatType($affrete->getId(), $type->getId(), $dateMin, "attente");
                if ($documentsValides) {
                    $documentsPersonne++;
                } elseif ($documentsInvalides || $documentAttente == []) {
                    $personneKO = true;
                }
            }
            // Calcul de l'état de l'affrété
            if ($documentsPersonne == count($types)) {
                $nbOk++;
            } elseif ($personneKO == true) {
                $nbKo++;
            } else {
                $nbPok++;
            }

        }
        return $this->json(["total" => $nbOk + $nbKo + $nbPok, "nbOk" => $nbOk, "nbPok" => $nbPok, "nbKo" => $nbKo], 200, []);
    }

    #[Route('/api/affrete/historique', name: 'api_affrete_historique', methods: ['GET'])]
    public function listeHistoriqueAffrete(Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        if ($request->query->get('idTiers') == null)
            return $this->json(['message' => 'Aucun tiers sélectionné'], 400);
        // Récupérer les paramètres de requête pour la pagination et le tri
        $page = 1;
        $perPage = 1000;
        $sortBy = 'actionDate';
        $sortOrder = $request->query->get('sortOrder', 'desc');
        if ($sortOrder != 'asc' && $sortOrder != 'desc')
            $sortOrder = 'desc';

        // Récupérer les paramètres de requête pour les filtres
        $filters = [];

        $idTiers = $request->query->get('idTiers');
        $affrete = $entityManager->getRepository(Affrete::class)->findOneBy(['label' => $idTiers]);
        $filters['affrete'] = $affrete;

        if ($request->query->has('type')) {
            $filters['type'] = $request->query->get('type');
        }

        // Ajouter d'autres filtres si nécessaire

        // Récupérer les documents en fonction des filtres, de la pagination et du tri
        // offset correspond au nombre d'éléments à sauter (pour la pagination)
        $nbHistorique = count($entityManager->getRepository(Historique::class)
            ->findBy($filters, [$sortBy => $sortOrder], $perPage, ($page - 1) * $perPage));
        $perPage = $request->query->get('perPage', 100);
        $page = $request->query->get('page', 1);
        $historique = $entityManager->getRepository(Historique::class)
            ->findBy($filters, [$sortBy => $sortOrder], $request->get('perPage'), ($page - 1) * $perPage);

        foreach ($historique as $h) {
            $h->getDocumentType()->setLabel($translator->trans($h->getDocumentType()->getLabel()));
        }
        // Retourner la réponse JSON avec les documents paginés et triés
        return $this->json([$historique, $nbHistorique], 200, [], ['groups' => 'historique:read']);
    }

    #[Route('/api/affrete/exists', name: 'api_affrete_exists', methods: ['GET'])]
    public function affreteExists(Request $request, EntityManagerInterface $entityManager): Response
    {
        $idTiers = $request->query->get('idTiers');
        $affrete = $entityManager->getRepository(Affrete::class)->findOneBy(['label' => $idTiers]);
        if ($affrete == null) {
            return $this->json(["affreteExists" => false], 200, []);
        } else {
            return $this->json(["affreteExists" => true], 200, []);
        }
    }

    #[Route('/api/affrete/insert-affrete-manual/{_locale}', name: 'api_affrete_insert_manual', methods: ['POST'])]
    public function insertAffreteManual(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, MailerInterface $mailer, TranslatorInterface $translator)
    {
        $content = json_decode($request->getContent(), true);
        /** vérification des champs */
        if (!isset($content['idTiers'], $content['companyName'], $content['email'], $content['contacts'])) {
            return $this->json(["message" => "Veuillez remplir tous les champs"], 400, []);
        }
        if (empty($content['contacts'])) {
            return $this->json(["message" => "Veuillez fournir au moins un contact réglementaire"], 400, []);
        }
        if (!strpos($content['email'], '@')) {
            return $this->json(["message" => "Veuillez fournir un email correct pour l'affrété"], 400, []);
        }

        $affreteExists = $entityManager->getRepository(Affrete::class)->findOneBy(['label' => $content['idTiers']]);
        /** insertion de l'affrete */
        /** affrete n'existe pas encore */
        if ($affreteExists == null) {
            $affrete = new Affrete();
            $langue = $request->getLocale();

            $affrete->setLabel($content['idTiers'])->setCompanyName($content['companyName'])->setEmail($content['email'])->setLangue($langue);
            $entityManager->persist($affrete);

            /** insertion des contacts */
            $contacts = array_unique($content['contacts']);
            $nbContacts = 0;
            foreach ($contacts as $contact) {
                if (strpos($contact, '@')) {
                    if ($entityManager->getRepository(Compte::class)->findOneBy(['email' => $contact]) == null) {
                        $compteContact = new Compte();
                        $compteContact
                            ->setEmail($contact)
                            ->setPassword($passwordHasher->hashPassword($compteContact, bin2hex(random_bytes(15))))
                            ->setAffrete($affrete);
                        $entityManager->persist($compteContact);
                        $nbContacts++;
                        $message = "http://espacetest.local/forgot/password/" . $affrete->getLangue();
                        $mail = (new Email())
                            ->from('a4a392186a-d2a4c7+1@inbox.mailtrap.io')
                            ->to($contact)
                            ->subject($translator->trans('subjectBeginning') . $translator->trans('creationAccount') . $translator->trans('inCharteringSpace'))
                            ->html(' ' . $translator->trans('mailBeginning') . '<p>' . $translator->trans('mailCreationFirstSentence') . '
 </p><p>' . $translator->trans('mailCreationSecondSentence') . ' <a href=' . $message . '>' . $translator->trans('clickingHere') . '</a></p>');
                        $mailer->send($mail);
                    }
                }
            }
            if ($nbContacts == 0) {
                return $this->json(["message" => "Echec de l'insertion des contacts"], 400, []);
            }
            $entityManager->flush();
            // mail à tous les comptes
            return $this->json(["message" => "Affreté ajouté"], 200, []);
        } /** affrete existe déjà */
        elseif ($affreteExists != null) {
            return $this->json(["message" => "Affreté existe déjà"], 400, []);
        } else {
            return $this->json(["message" => "erreur innatendue"], 400, []);
        }
    }

    /** route servant à insérer les comptes à partir de l'extraction contenu dans un fichier csv */
    #[Route('/api/inserer-affretes/{file}', name: 'api_insert_file_csv', methods: ['POST'])]
    public function insertAffretesWithFile(string $file, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, TranslatorInterface $translator, MailerInterface $mailer): Response
    {
        $csvFilePath = $file;
        if (!$csvFilePath) {
            // Gestion de l'erreur lors du déplacement du fichier CSV
            return new Response('Erreur lors de l\'import du fichier CSV', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $file = fopen($csvFilePath, 'r');
        if ($file === false) {
            // Gestion de l'erreur lors de l'ouverture du fichier CSV
            return new Response('Erreur lors de l\'ouverture du fichier CSV', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        while (($row = fgetcsv($file, 0, ';')) !== false) {
            $email = explode(';', $row[2]);
            $contacts = explode(',', $row[3]);
            for ($i = 0; $i < count($email); $i++) {
                if (!strpos($email[$i], '@')) {
                    unset($email[$i]);
                }
            }
            for ($i = 0; $i < count($contacts); $i++) {
                if (!strpos($contacts[$i], '@')) {
                    unset($contacts[$i]);
                }
            }

            /** verifier que les champs sont corrects  et que l'affrete n'existe pas déjà */
            if ($row[0] != null && $row[1] != null && count($email) > 0 && count($contacts) > 0 && $entityManager->getRepository(Affrete::class)->findOneBy(['email' => $email[0]]) == null) {
                // Création de l'entité Affrete
                $langue = "en";
                if ($row[4] == "F")
                    $langue = "fr";
                if ($row[4] == "E")
                    $langue = "es";
                $affrete = new Affrete();
                $affrete->setLabel($row[0])
                    ->setEmail($email[0])
                    ->setCompanyName($row[1])
                    ->setLangue($langue);
                $entityManager->persist($affrete);

                // Création des entités Compte pour les contacts
                foreach ($contacts as $contact) {
                    if ($entityManager->getRepository(Compte::class)->findOneBy(['email' => $contact]) == null) {
                        $compte = new Compte();
                        $compte->setEmail($contact)
                            ->setPassword($passwordHasher->hashPassword($compte, bin2hex(random_bytes(15))))
                            ->setAffrete($affrete);
                        $entityManager->persist($compte);
                        $message = "http://espacetest.local/forgot/password/" . $affrete->getLangue();
                        $mail = (new Email())
                            ->from('a4a392186a-d2a4c7+1@inbox.mailtrap.io')
                            ->to($contact)
                            ->subject($translator->trans('subjectBeginning') . $translator->trans('creationAccount') . $translator->trans('inCharteringSpace'))
                            ->html(' ' . $translator->trans('mailBeginning') . '<p>' . $translator->trans('mailCreationFirstSentence') . '
 </p><p>' . $translator->trans('mailCreationSecondSentence') . ' <a href=' . $message . '>' . $translator->trans('clickingHere') . '</a>');
                        $mailer->send($mail);
                        $entityManager->flush();
                    }
                }
            }

        }
        fclose($file);
        return new Response('Fichier CSV importé avec succès', Response::HTTP_OK);

    }

    /** route servant à relancer les affrétés valides si un des documents n'est bientôt plus valide */
    #[Route('/api/relancer-affretes/{_locale}', name: 'api_relaunch_affretes', methods: ['GET'])]
    public function relaunchAffretes(Request $request, CompteRepository $compteRepository, AffreteRepository $affreteRepository, DocumentRepository $documentRepository, TypeDocumentRepository $typeDocumentRepository, TranslatorInterface $translator, MailerInterface $mailer): Response
    {
        $types = $typeDocumentRepository->findBy(['mandatory' => true]);
        $affretes = $affreteRepository->findBy(['langue' => $request->getLocale()]);
        $today = new DateTime();
        $today->setTime(0, 0, 0);

        $expirationDateTwoWeeks = new DateTime();
        $expirationDateTwoWeeks->setTime(0, 0, 0)
            ->modify('+15 days');

        $expirationDateOneWeek = new DateTime();
        $expirationDateOneWeek->setTime(0, 0, 0)
            ->modify('+7 days');
        $docs = array();
        foreach ($affretes as $affrete) {
            $documentsAffrete = array();
            foreach ($types as $type) {
                $documentsValides = $documentRepository->findDocumentsEtatType($affrete->getId(), $type->getId(), $today, "valide");
                if (count($documentsValides) == 0) {
                    break;
                }
                $documentsAffrete[] = $documentsValides[count($documentsValides) - 1];
            }
            if (count($documentsAffrete) == count($types)) {
                $comptes = $compteRepository->findBy(['affrete' => $affrete->getId()]);
                for ($i = 0; $i < count($documentsAffrete); $i++) {
                    if (($documentsAffrete[$i]->getValidityEnd() == $expirationDateOneWeek || $documentsAffrete[$i]->getValidityEnd() == $expirationDateTwoWeeks) && $documentRepository->findDocumentsEtatType($affrete->getId(), $documentsAffrete[$i]->getType()->getId(), $today, "attente") == []) {
                        foreach ($comptes as $compte) {
                            $message = "http://espacetest.local/documents/depot-document/" . $affrete->getLangue() . "?typeDocument=" . $documentsAffrete[$i]->getType()->getId();
                            $mail = (new Email())
                                ->from('toureillefrancois@gmail.com')
                                ->to($compte->getEmail())
                                ->subject($translator->trans('subjectBeginning') . $translator->trans('expirationOf') .
                                    $translator->trans($documentsAffrete[$i]->getType()->getLabel()) . $translator->trans('inCharteringSpace'))
                                ->html($translator->trans('mailBeginning') . '<p>' . $translator->trans('mailExpirationFirstSentence') . '
         </p><p>' . $translator->trans('mailExpirationSecondSentence') . ' <a href=' . $message . '>' . $translator->trans('clickingHere') . '</a>');
                            $mailer->send($mail);
                        }
                    }
                }
            }
        }
        return new Response('Relances effectuées avec succès', Response::HTTP_OK);
    }


}