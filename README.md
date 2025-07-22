# packagist/publish-artifact-github-action

GitHub Action to publish artifacts as package versions to Private Packagist.

## Dependencies

[PHP](https://www.php.net) and [Composer](https://getcomposer.org) are required but will automatically be set up using the
[Setup PHP GitHub Action](https://github.com/shivammathur/setup-php) if not available.

Supported are PHP >= 7.2 and Composer >= 2.

## Usage

The GitHub Action can then be used as a step within a job e.g. on tag push. Create your artifact file before the publish artifact step.

```yaml
jobs:
    publish_artifact:
        name: Private Packagist Publish Artifact
        runs-on: "ubuntu-latest"

        steps:
            - uses: actions/checkout@v4

            # Create your artifact file here

            - name: "Publish artifact"
              uses: packagist/artifact-publish-github-action
              with:
                package_name: 'acme/package'
                organization_url_name: 'acme-org'
                artifact: '/full/path/to/artifact.zip'
              env: 
                PRIVATE_PACKAGIST_API_KEY: ${{ secrets.PRIVATE_PACKAGIST_API_KEY }}
                PRIVATE_PACKAGIST_API_SECRET: ${{ secrets.PRIVATE_PACKAGIST_API_SECRET }}
```

### Input Parameters

#### package_name (required)

The `package_name` input parameter allows you to configure the name of the package you would like to publish. Note: the name has to match the name that is set in the composer.json file in the artifact.

For example:

```yaml
- uses: packagist/artifact-publish-github-action
  with:
    package_name: "acme/package"
```

#### organization_url_name (required)

The `organization_url_name` input parameter allows you to configure the Private Packagist organization where you want to publish the package to. The parameter is currently only used when using the trusted publishing flow but must be set either way.

For example:

```yaml
- uses: packagist/artifact-publish-github-action
  with:
    organization_url_name: "acme/org"
```

#### artifact (required)

The `artifact` input parameter allows you to configure which artifact you would like to publish to Private Packagist. The value need to include the full path.

For example:

```yaml
- uses: packagist/artifact-publish-github-action
  with:
    artifact: '/full/path/to/artifact.zip'
```

#### private_packagist_url

The `private_packagist_url` input parameter allows you to configure the URL of your Private Packagist host. Useful if you are running Private Packagist Self-Hosted.

For example:

```yaml
- uses: packagist/artifact-publish-github-action
  with:
    private_packagist_url: 'https://private-packagist-self-hosted.example'
```

## Copyright and License

The  GitHub Action is licensed under the MIT License.
