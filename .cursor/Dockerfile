# Dockerfile for WordPress Plugin Development Environment
FROM ubuntu:24.04

# Avoid interactive prompts during package installation
ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies
RUN apt-get update && \
    apt-get install -y \
    software-properties-common \
    curl \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Add PHP repository
RUN add-apt-repository -y ppa:ondrej/php && \
    apt-get update

# Install PHP 8.2 and required extensions (matching composer.json requirement)
RUN apt-get install -y \
    php8.2 \
    php8.2-cli \
    php8.2-common \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-curl \
    php8.2-zip \
    php8.2-gd \
    php8.2-mysql \
    php8.2-mysqli \
    php8.2-sqlite3 \
    mariadb-client \
    && rm -rf /var/lib/apt/lists/*

# Set PHP as default
RUN update-alternatives --set php /usr/bin/php8.2

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer && \
    chmod +x /usr/local/bin/composer

# Install WP-CLI (pinned version with checksum verification)
ARG WP_CLI_VERSION=2.10.0
RUN set -eux; \
    curl -fSL -o /usr/local/bin/wp "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar"; \
    curl -fSL -o /tmp/wp-cli.phar.sha512 "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar.sha512"; \
    sha512sum -c /tmp/wp-cli.phar.sha512; \
    chmod +x /usr/local/bin/wp; \
    /usr/bin/wp --allow-root --version

# Create non-root user for agent runtime and set working directory
RUN useradd -m ubuntu
USER ubuntu
WORKDIR /home/ubuntu

# Set PATH to include PHP (already installed)
ENV PATH="/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:$PATH"

# Default command
CMD ["bash"]
