FROM php:8.1.10-apache

RUN apt-get update && apt-get install -y curl && \
    curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs && \
    rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . .

RUN a2enmod rewrite

RUN mkdir -p /var/www/html/uploads \
    /var/www/html/samples \
    /var/www/html/samplescsv \
  && touch /var/www/html/fake_db.json \
    /var/www/html/signed_docs.json \
    /var/www/html/firmas.json \
  && chmod -R 777 /var/www/html/uploads \
    /var/www/html/samples \
    /var/www/html/samplescsv \
  && chmod 666 /var/www/html/fake_db.json \
    /var/www/html/signed_docs.json \
    /var/www/html/firmas.json

EXPOSE 80