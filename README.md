# Compose for Laravel

### This package is in development and not yet ready for production use.

## Introduction

Compose for Laravel is a package that simplifies running and deploying Laravel applications using Docker Compose. It supports macOS, Linux, and Windows (WSL2). This package leverages Docker Compose for container orchestration and Traefik for routing HTTP requests.

## Installation

To install Compose for Laravel, include it in your Laravel application's `composer.json` file:

```bash
composer require --dev braceyourself/compose
```

Upon installation, the `compose` script will be available in `vendor/bin`.

## Usage

### Available Commands

```bash
compose COMMAND [options] [arguments]
```

- **compose restart**: Restart the application
- **compose migrate**: Run the application's database migrations

### All other commands are passed to Artisan

### Examples

- Restart the application:

    ```bash
    ./vendor/bin/compose restart
    ```

- Run database migrations:

    ```bash
    ./vendor/bin/compose migrate
    ```

## Setup Instructions

### Publish the Compose File [optional]

If you would like to work with docker compose directly, you can publish the Compose file to your project. This will allow you to make changes to the Compose file and run `docker-compose` commands directly.
To publish the Docker Compose file to your project, run:

```bash
./vendor/bin/compose publish \
    [--publish-path=] 
    
```

This command will publish the Docker Compose file to your project's `base_path()`
or the value of the `--publish-path` option.

### Traefik Setup


If Traefik is not set up, we will ask if you would like to install it.


1. Ensure the Traefik network exists:

    ```bash
    docker network create traefik
    ```

2. Ensure Traefik is running:

    ```bash
    docker-compose up -d traefik
    ```

## Running the Application locally

To start the application, use:

```bash
./vendor/bin/compose up
```

To stop the application, use:

```bash
./vendor/bin/compose down
```

## Deploy the application to a server

To deploy the application, use:

```bash
./vendor/bin/compose deploy
```

## Additional Commands

- **build**: Build the Docker containers
- **run**: Run a one-time command in a container
- **exec**: Execute a command in a running container
- **config**: Validate and view the Compose file
- **ps**: List containers
- **logs**: View output from containers

## Environment Variables

Ensure you have a `.env` file in your project root. The script will source this file to set up the necessary environment variables.

## Troubleshooting

If you encounter issues, ensure your system meets the requirements and that Docker and Docker Compose are properly installed and configured.

Currently, the script will attempt to install docker if it is not already installed.

Please report any issues.

## License

Compose for Laravel is open-source software licensed under the [MIT license](LICENSE).


## Support

For support and additional documentation, please visit [your-website](https://your-website.com).