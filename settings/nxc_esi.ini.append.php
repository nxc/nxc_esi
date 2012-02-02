<?php /* #?ini charset="utf-8"?

[ESIType]
# Which ESI type handler should be used.
# This is the class name of the handler class to use; default implementations
# found in this extension are nxcESITypeNone, nxcESITypePHP and nxcESITypeESI.
ESITypeHandler=nxcESITypeESI

[ESITypeESI]
# Should we send the X-Do-ESI: true header when something has been included?
# This header is, as far as I know, not used by anything by default, but e.g.
# Varnish can be configured to only do ESI processing when this header is
# present in the response.
SendDoESIHeader=false

# Should we include the onerror="continue" part of the esi:include tag?
# The standard says that if this part is not present, and an include fails to
# load, then the ESI processor should return an HTTP status code greater than
# 400 with an error message, while if it this is present, it instead deletes
# the include element silently.
ContinueOnError=false

[Permissions]
# This is a list of which templates can be retrieved through the module view.
AllowedTemplates[]

# This is a list of which methods can be called through the module view.
# Each entry can be either a class name, in which case all methods on that
# class are allowed, or on the format ClassName::methodName which only allows
# access to that particular method of that class.
AllowedMethods[]

*/ ?>
