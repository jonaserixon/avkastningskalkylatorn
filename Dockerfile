FROM php:8.2-cli
COPY . /usr/src/avkastningskalkylatorn
WORKDIR /usr/src/avkastningskalkylatorn
CMD [ "php", "./App/index.php" ]