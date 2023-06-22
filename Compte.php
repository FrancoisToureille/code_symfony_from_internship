<?php

namespace App\Entity;

use App\Repository\CompteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * @method string getUserIdentifier()
 */
#[ORM\Entity(repositoryClass: CompteRepository::class)]
class Compte implements UserInterface
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'uuid')]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: "doctrine.uuid_generator")]
    private ?Uuid $id = null;

    #[ORM\Column(name: 'email', length: 255)]
    #[Groups(['affrete:read'])]
    private ?string $email = null;

    #[ORM\Column(name: 'password', length: 255)]
    #[Groups(['affrete:read'])]
    private ?string $password = null;

    #[ORM\ManyToOne(inversedBy: 'comptes')]
    #[ORM\JoinColumn(name: 'affrete_id', nullable: false)]
    private ?Affrete $affrete = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 8)]
    #[Assert\Regex(pattern: "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?:.;&])[A-Za-z\d@$!%.;:*?&]+$/")]
    public ?string $newPassword = null;

    #[Assert\NotBlank()]
    #[Assert\EqualTo(propertyPath: "newPassword", message: "Veuillez confirmer votre nouveau mot de passe")]
    public ?string $confirmPassword = null;


    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getNewPassword(): ?string
    {
        return $this->newPassword;
    }

    public function getAffrete(): ?Affrete
    {
        return $this->affrete;
    }

    public function setAffrete(?Affrete $affrete): self
    {
        $this->affrete = $affrete;

        return $this;
    }

    public function getRoles()
    {
        return ['ROLE_USER'];
    }

    public function getSalt()
    {
        // TODO: Implement getSalt() method.
    }

    public function eraseCredentials()
    {
        // TODO: Implement eraseCredentials() method.
    }

    public function getUsername()
    {
        return $this->email;
    }

    public function __call(string $name, array $arguments)
    {
        // TODO: Implement @method string getUserIdentifier()
    }
}
