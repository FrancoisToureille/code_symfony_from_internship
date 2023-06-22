<?php

namespace App\Controller;

use App\Entity\Affrete;
use App\Entity\Compte;
use App\Entity\Document;
use App\Entity\Historique;
use App\Entity\TypeDocument;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class ApiDocumentController extends AbstractController
{
    #[Route('/api/documents/affrete', name: 'api_liste_documents_affrete', methods: ['GET'])]
    public function listeDocumentsAffrete(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Récupérer les paramètres de requête pour la pagination et le tri
        $page = $request->query->get('page', 1);
        $perPage = $request->query->get('perPage', 10);
        $sortBy = $request->query->get('sortBy', 'validityEnd');
        $sortOrder = $request->query->get('sortOrder', 'asc');
        if ($sortOrder != 'asc' && $sortOrder != 'desc') {
            $sortOrder = 'asc';
        }
        if (!$request->query->has('idTiers')) {
            return $this->json(['message' => 'il manque un id de tiers affrété'], 400);
        }
        // Récupérer les paramètres de requête pour les filtres
        $filters = [];
        $idTiers = $request->query->get('idTiers');
        $affrete = $entityManager->getRepository(Affrete::class)->findOneBy(['label' => $idTiers]);
        $filters['affrete'] = $affrete;

        if ($request->query->has('type')) {
            $filters['type'] = $entityManager->getRepository(TypeDocument::class)->find($request->query->get('type'));
        }

        if ($request->query->has('analysisState')) {
            $filters['analysisState'] = $request->query->get('analysisState');
        }
        if ($request->query->has('validityEnd')) {
            $validityEnd = $request->query->get('validityEnd');
            $filters['validityEnd'] = DateTime::createFromFormat('d/m/Y', $validityEnd)->setTime(0, 0, 0);
        }

        // Récupérer les documents en fonction des filtres, de la pagination et du tri
        // offset correspond au nombre d'éléments à sauter (pour la pagination)
        $documents = $entityManager->getRepository(Document::class)
            ->findBy($filters, [$sortBy => $sortOrder], $perPage, ($page - 1) * $perPage);

        // Retourner la réponse JSON avec les documents paginés et triés
        return $this->json($documents, 200, [], ['groups' => 'document:read']);
    }

    #[Route('/api/documents/types', name: 'api_types_documents_affrete', methods: ['GET'])]
    public function listeTypeDocumentsAffrete(Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $typesDocuments = $entityManager->getRepository(TypeDocument::class)->findAll();
        for ($i = 0; $i < count($typesDocuments); ++$i) {
            $typesDocuments[$i]->setLabel($translator->trans($typesDocuments[$i]->getLabel()));
        }

        // Retourner la réponse JSON avec les documents paginés et triés
        return $this->json($typesDocuments, 200, [], ['groups' => 'typeDocument:read']);
    }

    #[Route('/api/document/modifier/{id}/{_locale}', name: 'api_document_update', methods: ['PUT'])]
    public function updateDocument(Request $request, EntityManagerInterface $entityManager, string $id, MailerInterface $mailer, TranslatorInterface $translator): Response
    {
        $id = Uuid::fromString($id);
        $document = $entityManager->getRepository(Document::class)->find($id);

        if (!$document) {
            return $this->json(['message' => 'Document non trouvé'], 404);
        }

        $documentReceptionne = json_decode($request->getContent(), true);
        /** Récupération des données de la requête */
        $document
            ->setAnalysisState($documentReceptionne['analysisState'])
            ->setAnalysisDate(new \DateTime($documentReceptionne['analysisDate']))
            ->setUserCode($documentReceptionne['userCode'])
            ->setMessage($documentReceptionne['message']);

        if ($documentReceptionne['analysisState'] == 'invalide') {
            $refus = new Historique();
            $refus
                ->setAffrete($document->getAffrete())
                ->setDocumentType($document->getType())
                ->setDocument($document)
                ->setActionDate(new \DateTime('now'))
                ->setAction('refus du document')
                ->setUserCode($documentReceptionne['userCode'])
                ->setMotif($documentReceptionne['message']);
            $entityManager->persist($refus);
            $message = "http://espacetest.local/documents/mes-documents/" . $document->getAffrete()->getLangue();
            $contactsRelance = $entityManager->getRepository(Compte::class)->findBy(['affrete' => $document->getAffrete()]);
            for ($i = 0; $i < count($contactsRelance); ++$i) {
                $mail = (new Email())
                    ->from('a4a392186a-d2a4c7+1@inbox.mailtrap.io')
                    ->to($contactsRelance[$i]->getEmail())
                    ->subject($translator->trans('subjectBeginning') . $translator->trans('document') . '"' .
                        $translator->trans($document->getType()->getLabel()) . '"' . $translator->trans('refused') . $translator->trans('inCharteringSpace'))
                    ->html($translator->trans('mailBeginning') . '<p>' . $translator->trans('mailRefusedFirstSentence') .
                        $documentReceptionne['message'] . ' </p><p>' . $translator->trans('mailRefusedSecondSentence') . ' <a href=' . $message . '>' .
                        $translator->trans('clickingHere') . '</a></p>  ');
                $mailer->send($mail);
            }
        } else if ($documentReceptionne['analysisState'] == 'valide') {
            $validation = new Historique();
            $validation
                ->setAffrete($document->getAffrete())
                ->setDocumentType($document->getType())
                ->setDocument($document)
                ->setActionDate(new \DateTime('now'))
                ->setAction('validation du document')
                ->setUserCode($documentReceptionne['userCode']);
            $entityManager->persist($validation);
        }

        $entityManager->flush();
        return $this->json($document, 200, [], ['groups' => 'document:read']);
    }

    #[Route('/api/visualiser-document/{id}', name: 'api_visualiser_document_affrete')]
    public function viewDocument(Request $request, Document $document)
    {
        if (file_exists($fileToDownload = $this->getParameter('brochures_directory') . '/' . $document->getLabel())) {
            // Supprimer le fichier
            $response = new BinaryFileResponse($fileToDownload);
        } else {
            $response = new Response('Fichier non trouvé', 404);
        }
        return $response;
    }
}