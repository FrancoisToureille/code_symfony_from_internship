<?php

namespace App\DataFixtures;

use App\Entity\Affrete;
use App\Entity\Compte;
use App\Entity\TypeDocument;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AffreteFixtures extends Fixture
{
    public UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {

        //creer 6 affrete, 3 comptes par affrete, 2 documents par comptes et 6 types de documents
        $faker = Factory::create('fr_FR');
        //creer 6 affrete, 3 comptes par affrete, 2 documents par comptes et 6 types de documents


        for ($m = 0; $m < 6; $m++) {
            $types = array('KBIS', 'Attestation vigilance', 'Attestation régularité fiscale', 'Assurance marchandise ', 'Assurance Responsabilité Civile', 'Licence communautaire');
            $typeDocument = new TypeDocument();
            $typeDocument->setLabel($types[$m]);
            $m < 3 ? $typeDocument->setMandatory(true) : $typeDocument->setMandatory(false);
            $manager->persist($typeDocument);
        }

        for ($i = 0; $i < 4; $i++) {
            $affrete = new Affrete();
            $affrete->setLabel("" . $i)
            > setCompanyName($faker->company)
                ->setEmail($faker->email)
                ->setLangue('fr');

            $manager->persist($affrete);


            for ($j = 0; $j < 2; $j++) {
                $compte = new Compte();
                $compte->setAffrete($affrete);
                $compte->setEmail($faker->email);
                $compte->setPassword($this->passwordHasher->hashPassword($compte, 'password'));

                $manager->persist($compte);
            }

        }

        $manager->flush();


    }
}
