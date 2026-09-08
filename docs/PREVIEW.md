# Preview

Preview is not implemented in v0.1.0.

## Planned behavior

WordPress editors should be able to preview content in the correct decoupled frontend. The target frontend URL must be configurable per installation/environment.

The Core must not hardcode a consumer hostname.

## Security considerations

Draft/private preview data must not become publicly readable merely because the public frontend is decoupled. The final design must define authenticated preview requests, short-lived or otherwise protected preview state, capability expectations and safe redirect handling before implementation.
