<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\ManagerBundle\EventListener;

use Contao\CoreBundle\Event\MenuEvent;
use Contao\ManagerBundle\HttpKernel\JwtManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
class BackendMenuListener
{
    /**
     * @var Security
     */
    private $security;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var string
     */
    private $managerPath;

    /**
     * @var JwtManager
     */
    private $jwtManager;

    public function __construct(Security $security, RouterInterface $router, RequestStack $requestStack, TranslatorInterface $translator, bool $debug, ?string $managerPath, ?JwtManager $jwtManager)
    {
        $this->security = $security;
        $this->router = $router;
        $this->requestStack = $requestStack;
        $this->translator = $translator;
        $this->debug = $debug;
        $this->managerPath = $managerPath;
        $this->jwtManager = $jwtManager;
    }

    public function __invoke(MenuEvent $event): void
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $this->addDebugButton($event);
        $this->addManagerLink($event);
    }

    /**
     * Adds a debug button to the back end header navigation.
     */
    private function addDebugButton(MenuEvent $event): void
    {
        if (null === $this->jwtManager) {
            return;
        }

        $tree = $event->getTree();

        if ('headerMenu' !== $tree->getName()) {
            return;
        }

        if (!$request = $this->requestStack->getCurrentRequest()) {
            throw new \RuntimeException('The request stack did not contain a request');
        }

        $params = [
            'do' => 'debug',
            'key' => $this->debug ? 'disable' : 'enable',
            'referer' => base64_encode($request->server->get('QUERY_STRING')),
            'ref' => $request->attributes->get('_contao_referer_id'),
        ];

        $debug = $event->getFactory()
            ->createItem('debug')
            ->setLabel('debug_mode')
            ->setUri($this->router->generate('contao_backend', $params))
            ->setLinkAttribute('class', 'icon-debug')
            ->setLinkAttribute('title', $this->translator->trans('debug_mode', [], 'ContaoManagerBundle'))
            ->setExtra('translation_domain', 'ContaoManagerBundle')
        ;

        $children = [];

        // Try adding the debug button after the alerts button
        foreach ($tree->getChildren() as $name => $item) {
            $children[$name] = $item;

            if ('alerts' === $name) {
                $children['debug'] = $debug;
            }
        }

        // Prepend the debug button if it could not be added above
        if (!isset($children['debug'])) {
            $children = ['debug' => $debug] + $children;
        }

        $tree->setChildren($children);
    }

    /**
     * Adds a link to the Contao Manager to the back end main navigation.
     */
    private function addManagerLink(MenuEvent $event): void
    {
        if (null === $this->managerPath) {
            return;
        }

        $categoryNode = $event->getTree()->getChild('system');

        if (null === $categoryNode) {
            return;
        }

        $item = $event->getFactory()
            ->createItem('contao_manager')
            ->setLabel('Contao Manager')
            ->setUri('/'.$this->managerPath)
            ->setLinkAttribute('class', 'navigation contao_manager')
        ;

        $categoryNode->addChild($item);
    }
}
