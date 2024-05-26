FROM php:8.2-cli

COPY . /usr/src/avkastningskalkylatorn

WORKDIR /usr/src/avkastningskalkylatorn

RUN chmod +x /usr/src/avkastningskalkylatorn/src/index.php
RUN ln -s /usr/src/avkastningskalkylatorn/src/index.php /usr/local/bin/avk
RUN chmod +x /usr/local/bin/avk

# Add PHPStan
ADD https://github.com/phpstan/phpstan/releases/latest/download/phpstan.phar /usr/local/bin/phpstan
RUN chmod +x /usr/local/bin/phpstan

# BCMath
RUN docker-php-ext-install bcmath

# CMD [ "php", "./src/index.php" ]
CMD ["tail", "-F", "/dev/null"]
