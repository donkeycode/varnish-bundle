<?php

namespace DonkeyCode\VarnishBundle\Security\Authentication\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

class VarnishUserToken extends AbstractToken
{
    private $originUserName;
    private $originUser;

    public function __construct(array $roles = array())
    {
        parent::__construct($roles);

        // If the user has roles, consider it authenticated
        $this->setAuthenticated(true);
    }

    public function getCredentials()
    {
        return '';
    }

    public function setOriginUserName($userName)
    {
        $this->originUserName = $userName;
    }

    public function getOriginUserName()
    {
        return $this->originUserName;
    }

    public function setOriginUser($user)
    {
        $this->originUser = $user;
    }

    public function getOriginUser()
    {
        return $this->originUser;
    }
}