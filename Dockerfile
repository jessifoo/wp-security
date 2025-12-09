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

# Install PHP 8.2 and required extensions
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
    && rm -rf /var/lib/apt/lists/*

# Set PHP as default
RUN update-alternatives --set php /usr/bin/php8.2

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer && \
    chmod +x /usr/local/bin/composer

# Install WP-CLI
RUN curl -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x /usr/local/bin/wp && \
    wp --allow-root --version

# Set working directory
WORKDIR /workspace

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-interaction --no-progress --prefer-dist

# Copy project files
COPY . .

# Set PATH to include PHP
ENV PATH="/usr/bin:$PATH"

# Default command
CMD ["bash"]
