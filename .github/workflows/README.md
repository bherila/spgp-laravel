# GitHub Actions Deployment

This directory contains the GitHub Actions workflow for deploying the application.

## Setup

This workflow uses a GitHub Environment named `prod`. You will need to create this environment in your repository settings (`Settings > Environments > New environment`).

Once the `prod` environment is created, you need to configure the following secrets as **Environment secrets** within that environment:

1.  `SSH_HOST`: The hostname or IP address of the deployment server.
    -   Example: `your.server.com`

2.  `SSH_USERNAME`: The username for SSH login.
    -   Example: `your_user`

3.  `SSH_PRIVATE_KEY`: A dedicated private SSH key for the deployment user (preferably Ed25519).

4.  `SSH_KNOWN_HOSTS`: The pinned host key entry for the deployment host (same format as `~/.ssh/known_hosts`).
    -   Example generation from a trusted machine: `ssh-keyscan -H your.server.com`

## Deployment Target

The workflow deploys the application to the `~/bwh-php/` directory on the remote server. Make sure this directory exists and the specified SSH user has write permissions to it.
