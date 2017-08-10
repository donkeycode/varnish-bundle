<?php

namespace DonkeyCode\VarnishBundle\Annotation;

/**
 * Annotation for cache 
 *
 * @Annotation
 * @Target("METHOD")
 */
class InvalidateTag
{
    public $tag;

    public function __construct(array $values)
    {
        $this->tag = $values["value"];
    }

}