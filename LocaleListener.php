<?php
// src/EventListener/LocaleListener.php

namespace App\EventListener;

use App\Services\LocaleManager;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class LocaleListener
{
    private $localeManager;

    public function __construct(LocaleManager $localeManager)
    {
        $this->localeManager = $localeManager;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();
        $locale = $request->getLocale();

        // Définir la variable locale
        $this->localeManager->setLocale($locale);
    }
}
