# Compose for Laravel

![compose-for-laravel](https://ethanbrace.com/storage/01J43A3V79DYZYJJ3FDMBR56ZN.png)


### This package is in development and not yet ready for production use.

## Introduction

Compose for Laravel is a package that simplifies running and deploying Laravel applications using Docker Compose. 
It supports macOS, Linux, and Windows (WSL2). 
This package leverages Docker Compose for container orchestration and Traefik for routing HTTP requests.

## Installation

To install Compose for Laravel, include it in your Laravel application's `composer.json` file:

```bash
composer require --dev braceyourself/compose
```

Upon installation, the `compose` script will be available in `vendor/bin`. 
Run the install command to set up node_modules and composer dependencies.

```bash
./vendor/bin/compose install
```

## Usage

### Available Commands

```bash
compose COMMAND [options] [arguments]
```

- **compose install**: Build the Docker containers
- **compose build**: Build the Docker containers
- **compose start**: Start the application
- **compose deploy**: Deploy the application to a remote server

#### All other commands are passed to docker compose or artisan

## You can always use docker compose directly after running compose the first time.

After running compose for the first time, the docker-compose file will be published to your project root.

## Environment Variables

Ensure you have a `.env` file in your project root. The script will source this file to set up the necessary environment variables.

When running the `deploy` command, the remote `.env` file will be created based on your `.env.example` file

## Troubleshooting

If you encounter issues, ensure your system meets the requirements and that Docker and Docker Compose are properly installed and configured.

Please report any issues.
