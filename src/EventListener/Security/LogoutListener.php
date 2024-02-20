<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\ManagerBundle\EventListener\Security;

use Contao\ManagerBundle\HttpKernel\JwtManager;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LogoutListener
{
    /**
     * @internal
     */
    public function __construct(private readonly JwtManager|null $jwtManager = null)
    {
    }

    public function __invoke(LogoutEvent $event): void
    {
        if (!$response = $event->getResponse()) {
            return;
        }

        $this->jwtManager?->clearResponseCookie($response);
    }
}
