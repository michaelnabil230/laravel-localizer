# Contributing

Thanks for considering a contribution. Bug reports, PRs and ideas are
all welcome via [GitHub issues](https://github.com/niels-numbers/laravel-localizer/issues).

## Running the test suite

The package ships with a Docker setup for consistent testing across
environments.

### Prerequisites
- Docker
- Docker Compose
- GNU Make (optional, but recommended)

### With Make

```bash
make build    # Build the Docker image
make install  # Install Composer dependencies inside the container
make test     # Run PHPUnit tests
```

### Without Make

```bash
docker compose build
UID=$(id -u) GID=$(id -g) docker compose run --rm test composer install
UID=$(id -u) GID=$(id -g) docker compose run --rm test vendor/bin/phpunit
```

Tests live in `/tests` and run on Orchestra Testbench against the full
Laravel matrix the package supports (PHP 8.2-8.4 x Laravel 9-13).

## Documentation

The documentation site at
[localizer.adam-nielsen.de](https://localizer.adam-nielsen.de) is built
with VitePress from `docs/`.

```bash
npm install
npm run docs:dev      # local preview at http://localhost:5173
npm run docs:build    # production build into docs/.vitepress/dist
```

Edit a page directly via the "Edit this page on GitHub" link at the
bottom of every doc page, or open a PR against `docs/*.md`.
