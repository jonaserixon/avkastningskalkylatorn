FROM php:8.2-cli

COPY . /usr/src/avkastningskalkylatorn

WORKDIR /usr/src/avkastningskalkylatorn

RUN echo "memory_limit = 512M" >> $PHP_INI_DIR/php.ini

RUN echo "alias avk='php /usr/src/avkastningskalkylatorn/src/index.php'" >> /root/.bashrc
RUN ln -sf /usr/src/avkastningskalkylatorn/src/index.php /usr/local/bin/avk

# Add PHPStan
ADD https://github.com/phpstan/phpstan/releases/latest/download/phpstan.phar /usr/local/bin/phpstan
RUN chmod +x /usr/local/bin/phpstan

# Add Phan
ADD https://github.com/phan/phan/releases/latest/download/phan.phar /usr/local/bin/phan.phar
RUN chmod +x /usr/local/bin/phan.phar

# Install AST (For Phan)
RUN pecl install ast && docker-php-ext-enable ast

# BCMath
RUN docker-php-ext-install bcmath

# CMD [ "php", "./src/index.php" ]
CMD ["tail", "-F", "/dev/null"]
