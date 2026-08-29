# Packaging

The commercial package contains only the WHMCS runtime `modules/` tree. Development dependencies, tests, local environment files, Composer vendor files and repository metadata are not shipped.

Build locally with:

```bash
./scripts/package.sh 1.0.0
```

Before any Marketplace submission, install the resulting ZIP into a clean licensed WHMCS test instance rather than testing only from a Git checkout.
