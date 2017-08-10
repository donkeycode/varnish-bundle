To convert varnish headers to symfony authenticated token set in security.yml

Create VarnishUserProvider :

````
<?php

namespace DonkeyCode\VarnishBundle\Security\User;

use DonkeyCode\UserBundle\Propel\User;
use DonkeyCode\UserBundle\Propel\UserQuery;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

class VarnishUserProvider implements UserProviderInterface
{
    public function loadUserByUsername($username)
    {
        if ($user = UserQuery::create()->findOneByUuid($username)) {
            return $user;
        }

        throw new UsernameNotFoundException(
            sprintf('Username "%s" does not exist.', $username)
        );
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class)
    {
        return User::class === $class;
    }
}
````

Register it in `varnish.security.user_provider`

````
<service id="varnish.security.user_provider" 
    class="DonkeyCode\VarnishBundle\Security\User\VarnishUserProvider">
</service>
````

And in security.yml

````
providers:
    varnish:
        id: varnish.security.user_provider 

firewalls:
    secure:
        pattern:    ^/api
        varnish:
            provider: varnish
        fos_oauth:  true
        stateless:  true
        anonymous:  true
````
