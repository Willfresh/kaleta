# Remplacer la ligne 1 par ce tag officiel sécurisé pour PHP 8.3 :
FROM richarvey/nginx-php-fpm:php83

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
