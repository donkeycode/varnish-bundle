<?php

namespace DonkeyCode\VarnishBundle\Annotation\Driver;

use Doctrine\Common\Annotations\Reader;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use DonkeyCode\VarnishBundle\Annotation\InvalidateTag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use FOS\HttpCacheBundle\CacheManager;

class InvalidateTagAnnotationDriver
{
    private $reader;

    private $cacheManager;

    private $tag;

    public function __construct(Reader $reader, CacheManager $cacheManager = null)
    {
        $this->reader = $reader;
        $this->cacheManager = $cacheManager;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        if (!$this->cacheManager) {
            return;
        }

        if (!is_array($controller = $event->getController())) { //return if no controller
            return;
        }

        $object = new \ReflectionObject($controller[0]);// get controller
        $method = $object->getMethod($controller[1]);// get method

        foreach ($this->reader->getMethodAnnotations($method) as $configuration) { //Start of annotations reading
            if ($configuration instanceof InvalidateTag) {
                $this->tag = $configuration->tag;
            }
        }
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$this->cacheManager || !$this->tag) {
            return;
        }

        // Invalidate the object tag
        $this->cacheManager->invalidateTags(array(
            $this->tag
        ));
    }
}