# Makefile for Symfony Docker project

# Define the default shell
SHELL := /bin/bash

# Define the docker-compose command
DOCKER_COMPOSE := docker-compose

# Define the service name for php-fpm
PHP_FPM_SERVICE := php-fpm

.PHONY: bash

# Command to access bash in php-fpm container
bash:
	$(DOCKER_COMPOSE) exec $(PHP_FPM_SERVICE) bash