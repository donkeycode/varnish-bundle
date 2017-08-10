<?php

namespace DonkeyCode\VarnishBundle\Annotation\Driver;

use Doctrine\Common\Annotations\Reader;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use DonkeyCode\VarnishBundle\Annotation\ConditionalMaxAge;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ConditionalMaxAgeAnnotationDriver
{
    private $reader;

    private $authorizationChecker;

    private $maxAge;

    public function __construct(Reader $reader, AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->reader = $reader;
        $this->authorizationChecker = $authorizationChecker;
    }
   
    public function onKernelController(FilterControllerEvent $event)
    {
        if (!is_array($controller = $event->getController())) { //return if no controller
            return;
        }

        $object = new \ReflectionObject($controller[0]);// get controller
        $method = $object->getMethod($controller[1]);// get method

        foreach ($this->reader->getMethodAnnotations($method) as $configuration) { //Start of annotations reading
            if ($configuration instanceof ConditionalMaxAge) {
                $maxAge = $configuration->default;

                foreach ($configuration->roles as $role => $valueAge) {
                    if ($this->authorizationChecker->isGranted($role)) {
                        $maxAge = $valueAge;
                    }
                }

                $this->maxAge = $maxAge;
            }
        }

    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (null === $this->maxAge) {
            return;
        }

        $event->getResponse()->setMaxAge($this->maxAge);
    }
}