<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ChangePassword
{

    #[Assert\NotBlank]
    private $oldPassword;

    #[Assert\NotBlank]
    #[Assert\Length(min: 8)]
    #[Assert\Regex(pattern: "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!.:;%*?&])[A-Za-z\d@$!.:;%*?&]+$/")]
    private $newPassword;

    #[Assert\NotBlank]
    #[Assert\EqualTo(propertyPath: "newPassword", message: "Veuillez confirmer votre nouveau mot de passe")]
    private $confirmPassword;


    public function __construct()
    {
    }

    public function getOldPassword(): ?string
    {
        return $this->oldPassword;
    }

    public function getNewPassword(): ?string
    {
        return $this->newPassword;
    }

    public function getConfirmPassword(): ?string
    {
        return $this->confirmPassword;
    }

    public function setOldPassword(string $oldPassword): ChangePassword
    {
        $this->oldPassword = $oldPassword;

        return $this;
    }

    public function setNewPassword(string $newPassword): ChangePassword
    {
        $this->newPassword = $newPassword;

        return $this;
    }

    public function setConfirmPassword(string $confirmPassword): ChangePassword
    {
        $this->confirmPassword = $confirmPassword;

        return $this;
    }
}