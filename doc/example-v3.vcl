// These includes are needed by the EAS/1.0 implementation below, as we there
// need to convert a string from a header into a number, which VCL doesn't do
// natively, meaning we have to drop out into C for that task.
C{
#include <errno.h>
#include <limits.h>
#include <stdlib.h>
}C

// ================================ //

// We set a header on the object to be able to ban based on it later, but don't
// want that header to be sent to the client, as it's just for internal use.
sub vcl_fetch
{
	set beresp.http.X-URL = req.url;
}
sub vcl_deliver
{
	unset resp.http.X-URL;
}

// ================================ //

// If the backend tells us to do ESI with the custom header, do so, and remove
// it from the headers being sent to the client, as it's just for internal use.
// Note that this header is also used below to work around a bug in Varnish, so
// it can be set even if the backend server didn't send it.
sub vcl_fetch
{
	if ( beresp.http.X-Do-ESI )
	{
		set beresp.do_esi = true;
	}
}
sub vcl_deliver
{
	unset resp.http.X-Do-ESI;
}

// ================================ //

// We try to support the Edge Architecture Specification 1.0, in our role as a
// surrogate, as specified at http://www.w3.org/TR/2001/NOTE-edge-arch-20010804
//
// Do note that we actually lie a bit to the backend here, by saying we support
// ESI/1.0, while Varnish really only supports a subset of ESI/1.0.
//
// I am not sure how complete this implementation is, but we try our best
// within our limits, and have even included the optional targetting feature.
//
// However, the current implementation only looks at the first directive that
// matches us (either non-targetted or targetted to us), ignoring any other
// directives of the same type, so this support is technically partial - but
// for most cases, this should work just fine.
//
// The freshness extension of the max-age directive is implemented as grace.
//
// This implementation is a bit involved, so see further comments below.

// Advertise our capabilities to the backend.
sub vcl_recv
{
	if ( req.restarts == 0 )
	{
		if ( req.http.Surrogate-Capability )
		{
			set req.http.Surrogate-Capability =
				req.http.Surrogate-Capability + {", varnish="Surrogate/1.0 ESI/1.0""};
		}
		else
		{
			set req.http.Surrogate-Capability = {"varnish="Surrogate/1.0 ESI/1.0""};
		}
	}
}

// If we got a Surrogate-Control header, and have no downstream surrogates,
// we should remove the Surrogate-Control header so the client doesn't see it.
sub vcl_deliver
{
	if ( resp.http.Surrogate-Control )
	{
		if (
			! req.http.Surrogate-Capability ||
			req.http.Surrogate-Capability ~ "^\s*$" ||
			req.http.Surrogate-Capability ~ {"^\s*varnish="[^"]*"\s*$"}
		) {
			unset resp.http.Surrogate-Control;
		}
	}
}

// Try to parse and honor the Surrogate-Control header, if it is present.
sub vcl_fetch
{
	if ( beresp.http.Surrogate-Control )
	{
		// Check the content directive for ESI/1.0 and set do_esi=true if so.
		if ( beresp.http.Surrogate-Control ~ {"(^|,)\s*content="[^"]+"(;([^,]+;)?varnish(;[^,]+)?)?\s*(,|$)"} )
		{
			set beresp.http.X-Surrogate-Control-Content = regsub(
				beresp.http.Surrogate-Control,
				{"^(?:.*,)?\s*content="([^"]+)"(?:;(?:[^,]+;)?varnish(?:;[^,]+)?)?\s*(?:,.*)?$"},
				"\1"
			);
			if ( beresp.http.X-Surrogate-Control-Content ~ "(^|\s)ESI/1.0(\s|$)" )
			{
				// Backend wants us to do ESI processing.
				set beresp.do_esi = true;
			}
			unset beresp.http.X-Surrogate-Control-Content;
		}
		
		// If the max-age is given, set the response object's TTL to that.
		if ( beresp.http.Surrogate-Control ~ "(^|,)\s*max-age=[0-9]+(\+[0-9]+)?(;([^,]+;)?varnish(;[^,]+)?)?\s*(,|$)" )
		{
			// VCL doesn't have a function to go from string to duration, which
			// we need to set beresp.ttl, so we drop out into C to do it there,
			// after extracting the values we want to use in VCL.
			set beresp.http.X-Surrogate-Control-Max-Age = regsub(
				beresp.http.Surrogate-Control,
				"^(?:.*,)?\s*max-age=([0-9]+)(?:\+([0-9]+))?(?:;(?:[^,]+;)?varnish(?:;[^,]+)?)?\s*(?:,.*)?$",
				"\1"
			);
			// We here equate Varnish's concept of Grace with EAS/1.0's concept
			// of a freshness extension on the max age. If you want to set
			// grace yourself, either do it after this has run, or disable it.
			set beresp.http.X-Surrogate-Control-Grace = regsub(
				beresp.http.Surrogate-Control,
				"^(?:.*,)?\s*max-age=([0-9]+)(?:\+([0-9]+))?(?:;(?:[^,]+;)?varnish(?:;[^,]+)?)?\s*(?:,.*)?$",
				"\2"
			);
			// If the freshness extension is not set, it should be set to 0.
			if ( beresp.http.X-Surrogate-Control-Grace == "" )
			{
				set beresp.http.X-Surrogate-Control-Grace = "0";
			}
C{
			{
				char *x_end = 0;
				const char *x_hdr_val = VRT_GetHdr(sp, HDR_BERESP, "\034X-Surrogate-Control-Max-Age:");
				if (x_hdr_val)
				{
					long x_max_age = strtol(x_hdr_val, &x_end, 0);
					if (ERANGE != errno && x_end != x_hdr_val && x_max_age >= 0 && x_max_age < INT_MAX)
					{
						VRT_l_beresp_ttl(sp, (x_max_age * 1));
					}
				}
				x_hdr_val = VRT_GetHdr(sp, HDR_BERESP, "\032X-Surrogate-Control-Grace:");
				if (x_hdr_val)
				{
					long x_grace = strtol(x_hdr_val, &x_end, 0);
					if (ERANGE != errno && x_end != x_hdr_val && x_grace >= 0 && x_grace < INT_MAX)
					{
						VRT_l_beresp_grace(sp, (x_grace * 1));
					}
				}
			}
}C
			unset beresp.http.X-Surrogate-Control-Max-Age;
			unset beresp.http.X-Surrogate-Control-Grace;
		}
		
		// If we are told not to store this response in the cache, then don't.
		if ( beresp.http.Surrogate-Control ~ "(^|,)\s*no-store(;([^,]+;)?varnish(;[^,]+)?)?\s*(,|$)" )
		{
			// Surrogates should not cache this response, so don't.
			set beresp.ttl = 0s;
			set beresp.do_stream = true;
		}
		
		/* If we are a remote surrogate, comment out this line to enable these:
		if ( beresp.http.Surrogate-Control ~ "(^|,)\s*no-store-remote(;([^,]+;)?varnish(;[^,]+)?)?\s*(,|$)" )
		{
			// Remote surrogates should not cache this response, so don't.
			set beresp.ttl = 0s;
			set beresp.do_stream = true;
		}
		// */
	}
}

// ================================ //

// We have to disable If-Modified-Since due to a several years old bug in
// Varnish (that they consider a feature request), namely that Varnish returns
// 304 Not Modified as long as the root page hasn't expired, without first
// checking if the page has any includes that have expired.
//
// However, we only want to disable it for pages that are actually using ESI
// processing, so the browser doesn't have to keep re-downloading other things
// that haven't changed, such as images or pages not using ESI.
//
// Since the do_esi property is not available in vcl_hit, we reuse the X-Do-ESI
// header to know when to unset If-Modified-Since, as that name makes sense and
// the header is unset by vcl_deliver above anyway.
sub vcl_hit
{
	if ( obj.http.X-Do-ESI == "true" )
	{
		unset req.http.If-Modified-Since;
	}
}
sub vcl_fetch
{
	if ( beresp.do_esi )
	{
		set beresp.http.X-Do-ESI = "true";
	}
}
