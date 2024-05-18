FROM php:8.2-cli

COPY . /usr/src/avkastningskalkylatorn

WORKDIR /usr/src/avkastningskalkylatorn

# Ensure the PHP script is executable
RUN chmod +x /usr/src/avkastningskalkylatorn/src/index.php

# Create a symbolic link
RUN ln -s /usr/src/avkastningskalkylatorn/src/index.php /usr/local/bin/avk

RUN chmod +x /usr/local/bin/avk

# CMD [ "php", "./src/index.php" ]
CMD ["tail", "-F", "null"]
