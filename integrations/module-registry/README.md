# Reviewed registry releases

The public catalog and its release workflows are maintained in the separate [koakademy-modules repository](https://github.com/yukazakiri/koakademy-modules). Changes to registry generation, validation, review, and publishing belong in that repository.

Release discovery should propose a generated pull request for each changed catalog. Review and signing are separate maintainer steps; the discovery job does not need the private signing key.

Before accepting a generated module release, inspect its immutable source revision, passing module CI, host integration evidence, compatibility, migrations, permissions, and rollback behavior.

Validate with both contracts:

    php scripts/validate-registry.php
    php /path/to/koakademy/scripts/validate-module-catalog.php registry.json

After review, sign the approved catalog using the existing protected signing workflow or maintainer procedure and verify it before merging. Keep signing credentials out of contributor PR jobs. Protect the registry's publication branch and signing environment using its repository settings.

Deploying the host application does not update registry automation, configure repository protection, publish a catalog, or rotate its signing key.
