# Cache

The cache is managed using varnish.

First of all to understand this section be sure to understand RFC HTTP [https://tools.ietf.org/html/rfc7230](https://tools.ietf.org/html/rfc7230)

## Custom variant

To optimize caching strategy we need to remove informations about users.
We use for that a configuration fot varnish based on [http://asm89.github.io/2012/09/26/context-aware-http-caching.html](http://asm89.github.io/2012/09/26/context-aware-http-caching.html)

So when query is after varnish you can't have access token param. only

````
X-Auth-Group: free | registered | subscribed | admin
X-Auth-User: User hash
````

are sent so to check authentication use X-Auth-User.

## How is this X-Auth headers set/found

You will not see this headers in the website, they are only here for varnish caching.

As you have seen in @asm89 post we call profile to get user infos each time varnish recieve a call with an `access_token` parameter we call `/profile-varnish`.

````
header('X-Auth-Group: ' . ($this->getUser()->hasRole('ROLE_SUBSCRIBER') ? "subsribed" : "registered"));
header('X-Auth-User: ' . $this->getUser()->getUuid());
````

## Declare cache headers in symfony

````
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;

* @Cache(lastModified="page.getUpdatedAt()", ETag="'Page' ~ page.getId() ~ page.getUpdatedAt().getTimestamp()", Vary="X-Auth-Group", smaxage=300, maxage=60, public="true")
````

* public always true
* lastModified if applicable 
* Etag if applicable
* Vary : X-Auth-Group|X-Auth-user
* smaxage : quite short 
* maxage : short time

## How can i get current user now ?

Same as before ! See the magic with `varnish: true` in your `security.yml` provided by `CoreVarnishBundle` but of course set vary user or no cache for this controllers !!!

## View hits

`````
sudo varnishncsa -n asi
`````
