<?php

// src/Services/LocaleManager.php

namespace App\Services;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class LocaleManager
{
    private $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    public function setLocale(string $locale): void
    {
        $this->session->set('_locale', $locale);
    }

    public function getLocale(): string
    {
        return $this->session->get('_locale', 'fr'); // 'fr' est la langue par défaut si la variable locale n'est pas définie
    }
}
