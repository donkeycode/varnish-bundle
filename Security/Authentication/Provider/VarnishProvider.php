<?php

namespace DonkeyCode\VarnishBundle\Security\Authentication\Provider;

use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\NonceExpiredException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use DonkeyCode\VarnishBundle\Security\Authentication\Token\VarnishUserToken;

class VarnishProvider implements AuthenticationProviderInterface
{
    private $userProvider;

    public function __construct(UserProviderInterface $userProvider)
    {
        $this->userProvider = $userProvider;
    }

    public function authenticate(TokenInterface $token)
    {
        $user = $this->userProvider->loadUserByUsername($token->getUsername());

        if ($token->getOriginUsername()) {
            $originUser = $this->userProvider->loadUserByUsername($token->getOriginUsername());
        }

        if ($user) {
            $authenticatedToken = new VarnishUserToken($user->getRoles());
            $authenticatedToken->setUser($user);

            if (isset($originUser)) {
                $authenticatedToken->setOriginUser($originUser);
            }

            return $authenticatedToken;
        }

        throw new AuthenticationException('The varnish authentication failed.');
    }

    public function supports(TokenInterface $token)
    {
        return $token instanceof VarnishUserToken;
    }
}