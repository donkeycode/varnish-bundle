# @InvalidateTag

By default content is autotagged and can be banned used FosHttpCacheBundle, you can specify your own Tag using `@Tag`

`@InvalidateTag` let you specify content to purge
By default auto purge is done on content save

# @ConditionnalMaxAge

`@ConditionalMaxAge(default=300, roles={ "ROLE_ADMIN": 0 })`

By default content is stored 300 seconds else if user has role `ROLE_AMIN` content is stored 0 seconds

