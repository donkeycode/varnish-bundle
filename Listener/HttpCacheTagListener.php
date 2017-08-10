<?php

namespace DonkeyCode\VarnishBundle\Listener;

use JMS\Serializer\EventDispatcher\PreSerializeEvent;
use FOS\HttpCache\ResponseTagger;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use FOS\HttpCacheBundle\CacheManager;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

class HttpCacheTagListener implements EventSubscriberInterface
{
    private $responseTagger;
    private $cacheManager;

    private $classTagged = [];
    private $objectsTagged  = [];

    public function __construct(ResponseTagger $responseTagger = null, CacheManager $cacheManager = null)
    {
        $this->responseTagger = $responseTagger;
        $this->cacheManager   = $cacheManager;
    }

    public function onPreSerialize(PreSerializeEvent $event)
    {        
        if (!$this->responseTagger) {
            return;
        }

        $classTag = $this->getClassTag($event->getObject());

        if ($event->getObject() instanceof ActiveRecordInterface) {
            $this->objectsTagged[] = $this->getObjectTag($event->getObject());
        }

        if (!in_array($classTag, $this->classTagged)) {
            $this->classTagged[] = $classTag;
        }
    }

    public function uncacheTag(GenericEvent $event)
    {
        if (!$this->cacheManager) {
            return;
        }

        // Invalidate the object tag
        $this->cacheManager->invalidateTags(array(
            $this->getObjectTag($event->getSubject())
        ));

        // Invalidate the related collection(s) tag to referesh all pages
        $this->cacheManager->invalidateTags(array(
            $this->getClassTag($event->getSubject()).'_list'
        ));

        // always clear search
        $this->cacheManager->invalidateTags(array(
            "search"
        ));
        
    }

    public function onRestCGet(GenericEvent $event)
    {
        $this->classTagged[] = str_replace('\\', '_', $event->getSubject()).'_list';
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$this->responseTagger || !count($this->classTagged)) {
            return;
        }

        $this->responseTagger->addTags($this->classTagged);

        if (count($this->objectsTagged) < 500) {
            $this->responseTagger->addTags($this->objectsTagged);
        }
    }

    private function getClassTag($object) : string
    {
        return str_replace('\\', '_', get_class($object));
    }

    private function getObjectTag(ActiveRecordInterface $object) : string
    {
        return $this->getClassTag($object).'-'.json_encode($object->getPrimaryKey());
    }

    public static function getSubscribedEvents()
    {
        return [
            'propel.post_save' => 'uncacheTag',
            'propel.post_delete' => 'uncacheTag',
        ];
    }
}
