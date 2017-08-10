# Example of ProfileController

````
/**
* @ApiDoc(
*  resource=true,
*  description="Get my profile"
* )
* @Rest\Get("/profile-varnish")
* @Rest\View()
* @ConditionalMaxAge(default=300, roles={ "ROLE_ADMIN": 0 })
* @Cache(Vary="X-Auth-User", smaxage=3600, public="true")
* @Security("has_role('ROLE_USER')")
* @Tag("profile")
*/
public function varnishAction()
{
    $this->getUser()->reload();
    header('X-Auth-Group: ' . $this->getUser()->getVariantGroup());
    header('X-Auth-User: ' . $this->getUser()->getUuid());

    return '';
}
````
