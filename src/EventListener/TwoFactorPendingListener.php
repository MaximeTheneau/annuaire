<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class TwoFactorPendingListener
{
    private const ALLOWED_ROUTES = [
        'app_login',
        'app_logout',
        'app_two_factor',
        'app_two_factor_resend',
    ];

    public function __construct(
        private readonly RouterInterface $router,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        if (!$session->get('two_factor_pending')) {
            return;
        }

        $route = $request->attributes->get('_route');
        if (in_array($route, self::ALLOWED_ROUTES, true)) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->router->generate('app_two_factor')));
    }
}
