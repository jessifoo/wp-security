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

# Install PHP 8.4 and required extensions (matching composer.json requirement)
RUN apt-get install -y \
    php8.4 \
    php8.4-cli \
    php8.4-common \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-curl \
    php8.4-zip \
    php8.4-gd \
    php8.4-mysql \
    php8.4-mysqli \
    php8.4-sqlite3 \
    mariadb-client \
    && rm -rf /var/lib/apt/lists/*

# Set PHP as default
RUN update-alternatives --set php /usr/bin/php8.4

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer && \
    chmod +x /usr/local/bin/composer

# Install WP-CLI (pinned version with checksum verification)
ARG WP_CLI_VERSION=2.10.0
RUN set -eux; \
    WP_PHAR=wp-cli-${WP_CLI_VERSION}.phar; \
    # Download PHAR and its checksum into /tmp using the original filename the checksum references
    curl -fSL -o /tmp/${WP_PHAR} "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/${WP_PHAR}"; \
    curl -fSL -o /tmp/${WP_PHAR}.sha512 "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/${WP_PHAR}.sha512"; \
    # Verify checksum (GitHub releases provide raw hash only, so format it for sha512sum)
    echo "$(cat /tmp/${WP_PHAR}.sha512)  /tmp/${WP_PHAR}" | sha512sum -c -; \
    # Move into place and make executable
    mv /tmp/${WP_PHAR} /usr/local/bin/wp; \
    chmod +x /usr/local/bin/wp; \
    /usr/local/bin/wp --allow-root --version

# Ensure ubuntu user exists and create workspace directory
RUN if ! id -u ubuntu >/dev/null 2>&1; then useradd -m ubuntu; fi && \
    mkdir -p /workspace && \
    chown -R ubuntu:ubuntu /workspace

USER ubuntu
WORKDIR /workspace

# Set PATH to include PHP (already installed)
ENV PATH="/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:$PATH"

# Default command
CMD ["bash"]
