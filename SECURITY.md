# Security policy

## Reporting a vulnerability

Please **don't open a public issue** for security problems. Instead:

- Email the maintainer at **teriffy@gmail.com** with details of the vulnerability, or
- Use GitHub's [private security advisory](https://github.com/Teriffy/open-graph-control/security/advisories/new).

Include:

1. Plugin version
2. WordPress version + PHP version
3. Steps to reproduce
4. Impact (what can an attacker do?)
5. Suggested fix if you have one

I aim to respond within **3 business days** and publish a fix within **30 days** of a confirmed valid report. Credit will be given in the release notes unless you prefer anonymity.

## Supported versions

| Version | Supported |
|---|---|
| 0.2.x | ✅ |
| 0.1.x | ❌ (pre-release, upgrade to 0.2.x) |
| 0.0.x | ❌ (dev snapshots) |

## Scope

In scope:

- Injection / XSS / CSRF in the plugin's admin UI or REST endpoints
- Capability or nonce bypasses
- Unsafe handling of untrusted input (`og:*` meta values, imported JSON)
- Unauthorised write to `ogc_settings` or `_ogc_meta`

Out of scope (generally):

- WordPress core vulnerabilities (report those to the core security team)
- Other plugins' OG output when Open Graph Control is not the active emitter
- Social exploits (spam, trademark abuse) in content you chose to publish

Thank you for helping keep Open Graph Control users safe.
