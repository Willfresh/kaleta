# Remplacer la ligne 1 par ce tag officiel qui contient PHP 8.3 stable :
FROM richarvey/nginx-php-fpm:latest

COPY . .

# Configuration du serveur
ENV SKIP_COMPOSER 1
ENV WEBROOT /var/www/html/public
ENV PHP_ERRORS_STDERR 1
ENV RUN_SCRIPTS 1
ENV REAL_IP_HEADER 1

# Configuration Laravel en production
ENV APP_ENV production
ENV APP_DEBUG false
ENV LOG_CHANNEL stderr

# Autoriser Composer à tourner en administrateur
ENV COMPOSER_ALLOW_SUPERUSER 1

CMD ["/start.sh"]
