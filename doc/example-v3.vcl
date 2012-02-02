
backend default {
	.host = "127.0.0.1";
	.port = "8080";
}

# vcl_fetch is called when an object has been retrieved from the backend.
sub vcl_fetch {
	# Set a header on the response to the URL, so we can ban based on it later.
	set beresp.http.X-URL = req.url;
	
	# If the backend told us to do ESI for this object, do so.
	if ( beresp.http.X-Do-ESI ) {
		set beresp.do_esi = true;
	}
}

# vcl_deliver is called when an object is about to be delivered to the client.
sub vcl_deliver {
	# Remove the X-URL header, as that is just for cache clearing purposes.
	unset resp.http.X-URL;
	
	# Remove the X-Do-ESI header, as that was meant for us, not the client.
	unset resp.http.X-Do-ESI;
}
