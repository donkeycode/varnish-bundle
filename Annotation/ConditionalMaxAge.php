<?php

namespace DonkeyCode\VarnishBundle\Annotation;

/**
 * Annotation for cache 
 *
 * @Annotation
 * @Target("METHOD")
 */
class ConditionalMaxAge
{
    public $default;

    public $roles = [];
}