FROM php:8.2-cli

RUN docker-php-ext-install mysqli

WORKDIR /srv/www/htdocs/ProSpeaking

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "/srv/www/htdocs/ProSpeaking"]
