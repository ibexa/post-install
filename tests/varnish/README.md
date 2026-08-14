Varnish VCL tests
=================

`varnishtest` cases for `resources/platformsh/common/4.6/.platform/varnish.vcl`, the VCL shipped to
Platform.sh / Ibexa Cloud installations.

They load the real VCL file into a real Varnish and assert on the request the **backend** receives,
which is where the reverse proxy header filtering in `vcl_recv` can be observed.

Cases
-----

| Case | Scenario | Asserts |
|---|---|---|
| `no-cdn.vtc` | request straight through the Ibexa Cloud router | the client supplied part of `X-Forwarded-For` is dropped and the header is rebuilt from `X-Client-IP`; `X-Forwarded-Host` / `-Prefix` / `Forwarded` are stripped; `X-Forwarded-Proto` is kept |
| `via-cdn.vtc` | request arriving through a supported CDN | `X-Forwarded-For`, `X-Client-IP` and `Client-Cdn` are kept as the router left them, so Fastly detection still works |
| `no-client-ip.vtc` | no `X-Client-IP`, so the request did not come through the router | `X-Forwarded-For` is dropped entirely rather than trusted |

The header behaviour being relied on is documented at
<https://fixed.docs.upsun.com/development/headers.html>.

Running them
------------

```bash
docker build -t ibexa-varnishtest:6.0 tests/varnish
IMAGE=ibexa-varnishtest:6.0 tests/varnish/run.sh
```

Notes
-----

- The image has to be **Varnish 6.0LTS specifically**. This VCL returns `miss` from `vcl_hit`, which
  6.5 and later reject, and the xkey vmod it imports is not packaged for 6.0LTS — hence the source
  build in `Dockerfile`, mirroring `doc/docker/Dockerfile-varnish` in `ibexa/docker`.
- The VCL cannot be loaded as it stands: Platform.sh supplies the VCL version declaration, the `std`
  import and the `app.backend()` director. `run.sh` prepends the first two and swaps the director for
  a stub, so the shipped file itself is what gets tested.
- The backend listens on a fixed port (9081) so the stub can point at it.
- Each case uses its own URL and its own Varnish instance: `X-Forwarded-*` is not part of the cache
  key, so cases sharing a cache would be served each other's responses.
