<?php

namespace App\Controller;

use App\Entity\Compte;
use App\Entity\Document;
use App\Entity\Historique;
use App\Entity\TypeDocument;
use App\Form\DocumentDepotType;
use App\Services\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

class DocumentController extends AbstractController
{
    #[Route('documents/depot-document/{_locale}', name: 'depot_document')]
    public function depotDocument(Request $request, EntityManagerInterface $entityManager, FileUploader $fileUploader): Response
    {
        $compte = $entityManager->getRepository(Compte::class)->find($this->getUser()->getId());
        $affrete = $compte->getAffrete();
        $document = new Document();

        /** On récupère le type de document choisi par l'affrété "dans mes documents" */
        $typeDocument = $request->get('typeDocument');
        if ($typeDocument != null) {
            $typeDocument = $entityManager->getRepository(TypeDocument::class)->find($typeDocument);
            $document->setType($typeDocument);
        }

        $form = $this->createForm(DocumentDepotType::class, $document, ['attr' => ['class' => 'formulaire-depot-document']]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dateMin = new \DateTime('today');;

            /** Si la personne dépose un document d'un type déja valide, on lui refuse le dépot  */
            $documentValideExistant = $entityManager->getRepository(Document::class)
                ->findDocumentsEtatType($affrete, $document->getType(), $dateMin, "valide");

            $perimeBientot = new \DateTime('today');
            $perimeBientot->add(new \DateInterval('P15D'));
            $dernierValide = count($documentValideExistant) - 1;
            if ($documentValideExistant && $documentValideExistant[$dernierValide]->getValidityEnd() > $perimeBientot) {
                return $this->redirectToRoute("mes_documents");
            }
            if ($document->getValidityEnd() < $dateMin) {
                return $this->redirectToRoute("mes_documents");
            }

            /** @var UploadedFile $brochureFile */
            $brochureFile = $form->get('brochure')->getData();
            if ($brochureFile) {
                $label = $fileUploader->upload($brochureFile);
                $document
                    ->setLabel($label)
                    ->setAffrete($affrete);
            }
            $document->setAnalysisState("attente");
            $entityManager->persist($document);

            /** On ajoute un tuple dans historique pour le dépot du document */
            $depot = new Historique();
            $depot
                ->setActionDate(new \DateTime('now'))
                ->setAction("Ajout d'un document")
                ->setDocumentType($document->getType())
                ->setAffrete($affrete)
                ->setDocument($document);
            $entityManager->persist($depot);

            /** A chaque dépot, on supprime les documents invalides ou en attente du type concerné pour cet affrété  */
            $anciensDocuments = $entityManager->getRepository(Document::class)
                ->findBy(['affrete' => $affrete, 'type' => $document->getType(),
                    'analysisState' => ['attente', 'invalide']]);

            if ($anciensDocuments != null) {
                foreach ($anciensDocuments as $ancienDocument) {
                    $suppression = new Historique();
                    $suppression
                        ->setActionDate(new \DateTime('now'))
                        ->setAction("Suppression entrainée par un dépot")
                        ->setDocumentType($ancienDocument->getType())
                        ->setAffrete($affrete);
                    $entityManager->getRepository(Historique::class)->cleanHistorique($ancienDocument);
                    $entityManager->persist($suppression);
                    $entityManager->remove($ancienDocument);
                    /** On supprime le document pdf */
                    if (file_exists($fileToDelete = $this->getParameter('brochures_directory') . '/' . $ancienDocument->getLabel())) {
                        unlink($fileToDelete);
                    }
                }
            }

            $entityManager->flush();
            return $this->redirectToRoute("mes_documents");
        }
        return $this->render('document/depot.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/documents/mes-documents/{_locale}', name: 'mes_documents')]
    public function mesDocuments(EntityManagerInterface $entityManager): Response
    {
        /** récupération de l'affrété lié au compte connecté */
        $compte = $entityManager->getRepository(Compte::class)->find($this->getUser()->getId());

        /** récupération de l'affrété lié au compte connecté */
        $affrete = $compte->getAffrete();

        /** récupération de tous les types de documents */
        $types = $entityManager->getRepository(TypeDocument::class)->findAll();

        /** @var $documentsAffrete liste comportant chaque document non archivé de l'affrété */
        $documentsAffrete = [];
        $dateMin = new \DateTime('today');

        $documentsValides = [];
        foreach ($types as $type) {
            $documents = $entityManager->getRepository(Document::class)->findByDocumentsTypeAffrete($affrete, $type, $dateMin);

            if (empty($documents)) {
                $documentsAffrete[] = null;
            } else {
                $size = count($documents);
                $documentsAffrete[] = $documents[$size - 1];
            }

            /** récupération des documents déjà valides  */
            $documentValideExistant = $entityManager->getRepository(Document::class)
                ->findDocumentsEtatType($affrete, $type, $dateMin, "valide");
            if ($documentValideExistant != null)
                $documentsValides[] = $documentValideExistant[count($documentValideExistant) - 1];
        }
        return $this->render('document/mes_documents.html.twig', ['types' => $types,
            'documents' => $documentsAffrete, 'perimeBientot' => $dateMin->add(new \DateInterval('P15D')), 'documentsValides' => $documentsValides]);
    }


    #[Route('documents/supprimer-document/{_locale}/{id}', name: 'supprimer_document')]
    public function deleteDocument(string $id, EntityManagerInterface $entityManager): Response
    {
        /** récupération du document de l'affrété lié au compte connecté pour empécher la suppression d'un document d'un autre affrété*/
        $affrete = $entityManager->getRepository(Compte::class)->find($this->getUser()->getId())->getAffrete();
        if ($affrete == null || $affrete != $entityManager->getRepository(Document::class)->find($id)->getAffrete())
            return $this->redirectToRoute("login");
        $document = $entityManager->getRepository(Document::class)->findOneBy(['id' => $id, 'affrete' => $affrete]);

        /** On ne peut pas supprimer un fichier valide */
        if ($document->getAnalysisState() == "valide") {
            return $this->redirectToRoute("mes_documents");
        }

        /** Le fichier est supprimé de la base de données et du répertoire */
        $action = new Historique();
        $action->setAffrete($affrete)
            ->setAction("Suppression du document")
            ->setActionDate(new \DateTime('now'))
            ->setDocumentType($document->getType());
        $entityManager->getRepository(Historique::class)->cleanHistorique($document);
        $entityManager->persist($action);

        $entityManager->remove($document);
        if (file_exists($fileToDelete = $this->getParameter('brochures_directory') . '/' . $document->getLabel())) {
            // Supprimer le fichier
            unlink($fileToDelete);
        }
        $entityManager->flush();
        return $this->redirectToRoute("mes_documents");
    }


    #[Route('documents/visualiser-mon-document/{id}', name: 'visualiser_mon_document')]
    public function viewMyDocument(string $id, EntityManagerInterface $entityManager)
    {
        $affrete = $entityManager->getRepository(Compte::class)->find($this->getUser()->getId())->getAffrete();
        $document = $entityManager->getRepository(Document::class)->findOneBy(['id' => $id, 'affrete' => $affrete]);

        if ($document == null) {
            return $this->redirectToRoute("mes_documents");
        }

        $fileToDownload = $this->getParameter('brochures_directory') . '/' . $document->getLabel();

        if (file_exists($fileToDownload)) {
            $response = new BinaryFileResponse($fileToDownload);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
            return $response;
        } else {
            return $this->redirectToRoute("mes_documents");
        }
    }
}
