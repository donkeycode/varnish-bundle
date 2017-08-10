vcl 4.0;

import curl;

# Default backend definition. Set this to point to your content server.
backend default {
    .host = "127.0.0.1";
    .port = "8090";
}

acl invalidators {
    "localhost";
    # Add any other IP addresses that your application runs on and that you
    # want to allow invalidation requests from. For instance:
    # "192.168.1.0"/24;
}

# The routine handling received requests
sub vcl_recv {
  if (req.method == "POST" || req.method == "PUT" || req.method == "PATCH" || req.method == "DELETE") {
    return (pass);
  }

  call fos_refresh_recv;
  call fos_purge_recv;
  call fos_ban_recv;

  if (req.http.Range) {
    set req.http.x-range = req.http.Range;
  }

  # Remove all cookies
  if (req.http.Cookie) {
    unset req.http.Cookie;
  }

  # Happens before we check if we have this in cache already.
  #
  # Typically you clean up the request here, removing cookies you don\'t need,
  # rewriting the request, etc.
  
  # At first entry, authenticate  
  if (req.restarts == 0 && req.url !~ "/profile-varnish" && req.url !~ "/switch") {

    # Url should contain "access_token"
    if (req.url !~ "access_token" && !req.http.Authorization) {
        set req.http.X-Auth-Group = "free";
        set req.http.X-Auth-User  = "";
        set req.http.X-Auth-Origin-User = "";

        return (hash);
    }

    # TODO || req.http.Authorization )
    if (!req.http.Authorization) {
        curl.fetch("https://<your-api>/profile-varnish?access_token=" + regsub(req.url, "(.*)access_token=([^&]+)(.*)", "\2"));
    } else {
        curl.fetch("https://<your-api>/profile-varnish?access_token=" + regsub(req.http.Authorization, "Bearer (.*)", "\1"));
    }

    # extract the access token and call a specialized script to authenticate the token

    # check the status code of the response
    if (curl.status() != 200) {
        set req.http.X-Auth-Group = "free";
        set req.http.X-Auth-User  = "";
        set req.http.X-Auth-Origin-User = "";

        return (hash);
    }

    # Add additional headers to the original request
    set req.http.X-Auth-User  = curl.header("X-Auth-User");
    set req.http.X-Auth-Group = curl.header("X-Auth-Group");
    set req.http.X-Auth-Origin-User = curl.header("X-Auth-Origin-User");
    unset req.http.Authorization;
    
    curl.free();

    # Remove the access token from the original url
    if (!req.http.Authorization) {
        set req.url = regsub(req.url, "(.*)access_token=([^&]+)(.*)", "\1\3");
    }
  }
} 

sub vcl_hash {
    if (req.http.x-range) {
        hash_data(req.http.x-range);
        unset req.http.Range;
    }
}

sub vcl_backend_fetch {
    if (bereq.http.x-range) {
        set bereq.http.Range = bereq.http.x-range;
    }
}

sub vcl_backend_response {
    call fos_ban_backend_response;

    if (beresp.http.cache-control ~ "(no-cache|private)" ||
        beresp.http.pragma ~ "no-cache") {
            set beresp.ttl = 0s;
    }

    if (beresp.http.Accept-Ranges) {
        set beresp.http.X-Accept-Ranges = beresp.http.Accept-Ranges;
    }

    if (bereq.http.x-range && beresp.status == 206) {
        set beresp.http.CR = beresp.http.content-range;
    }

    # Happens after we have read the response headers from the backend.
    #
    # Here you clean the response headers, removing silly Set-Cookie headers
    # and other mistakes your backend does.
    unset beresp.http.Server;
    unset beresp.http.X-Powered-By;
    unset beresp.http.Cookie;

    # only cache status ok and 404
    if (beresp.status != 200 && beresp.status != 404) {
        set beresp.uncacheable = true;

        return (deliver);
    }

    # don't cache response to posted requests or those with basic auth
    if (bereq.method == "POST" || bereq.method == "PUT" || bereq.method == "PATCH" || bereq.method == "DELETE") {
        set beresp.uncacheable = true;

        return (deliver);
    }

    # Define the default grace period to serve cached content
    set beresp.grace = 30s;

    return (deliver);

}

sub vcl_deliver {
    call fos_ban_deliver;
    
    set resp.http.Access-Control-Allow-Origin = "*";
    set resp.http.Access-Control-Allow-Credentials = "true";

    if (req.method == "OPTIONS") {
        set resp.http.Access-Control-Max-Age = "1728000";
        set resp.http.Access-Control-Allow-Methods = "GET, POST, PUT, DELETE, PATCH, OPTIONS";
        set resp.http.Access-Control-Allow-Headers = "Authorization,Content-Type,Accept,Origin,User-Agent,DNT,Cache-Control,Keep-Alive,If-Modified-Since,Range,X-Request-With";
        set resp.http.Access-Control-Expose-Headers = "Content-Range";

        set resp.http.Content-Length = "0";
        set resp.http.Content-Type = "text/plain charset=UTF-8";
        set resp.status = 204;
    }

    if (resp.http.CR) {
        set resp.http.Content-Range = resp.http.CR;
        unset resp.http.CR;
    }
    
    if (resp.http.X-Accept-Ranges) {
        set resp.http.Accept-Ranges = resp.http.X-Accept-Ranges;
        unset resp.http.X-Accept-Ranges;
    }

    # Happens when we have all the pieces we need, and are about to send the
    # response to the client.
    #
    # You can do accounting or modifying the final object here.

    if (obj.hits > 0) {
        set resp.http.X-Cache = "cached";
    } else {
        set resp.http.X-Cache = "uncached";
    }

    # Remove some headers: PHP version
    unset resp.http.X-Powered-By;

    # Remove some headers: Apache version & OS
    unset resp.http.Server;

    # Remove some heanders: Varnish
    unset resp.http.Via;
    unset resp.http.X-Varnish;
    unset resp.http.Cookie;

    unset resp.http.Vary;
    set resp.http.Vary = "Accept-Encoding";
    set resp.http.Cache-Control = regsub(resp.http.Cache-Control, "(.*)s-maxage=([^ ]+)(.*)", "\1\3");

    return (deliver);
}

### FOS CONFIGS BASED
sub fos_purge_recv {
    if (req.method == "PURGE") {
        if (!client.ip ~ invalidators) {
            return (synth(405, "Not allowed"));
        }

        return (purge);
    }
}


sub fos_ban_recv {

    if (req.method == "BAN") {
        if (!client.ip ~ invalidators) {
            return (synth(405, "Not allowed"));
        }

        if (req.http.X-Cache-Tags) {
            ban("obj.http.X-Host ~ " + req.http.X-Host
                + " && obj.http.X-Url ~ " + req.http.X-Url
                + " && obj.http.content-type ~ " + req.http.X-Content-Type
                # the left side is the response header, the right side the invalidation header
                + " && obj.http.X-Cache-Tags ~ " + req.http.X-Cache-Tags
            );
        } else {
            ban("obj.http.X-Host ~ " + req.http.X-Host
                + " && obj.http.X-Url ~ " + req.http.X-Url
                + " && obj.http.content-type ~ " + req.http.X-Content-Type
            );
        }

        return (synth(200, "Banned"));
    }
}

sub fos_ban_backend_response {

    # Set ban-lurker friendly custom headers
    set beresp.http.X-Url = bereq.url;
    set beresp.http.X-Host = bereq.http.host;
}

sub fos_ban_deliver {

    # Keep ban-lurker headers only if debugging is enabled
    if (!resp.http.X-Cache-Debug) {
        # Remove ban-lurker friendly custom headers when delivering to client
        unset resp.http.X-Url;
        unset resp.http.X-Host;

        # Unset the tagged cache headers
        unset resp.http.X-Cache-Tags;
    }
}

sub fos_refresh_recv {
    if (req.http.Cache-Control ~ "no-cache" && client.ip ~ invalidators) {
        set req.hash_always_miss = true;
    }
}