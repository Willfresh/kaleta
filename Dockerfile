# Utiliser l'image Debian officielle (Évite le bug "Operation not permitted" de Render)
FROM webdevops/php-nginx:8.3

# Définir le dossier de travail
WORKDIR /app

# Copier tous les fichiers du projet (y compris public/build compilé localement)
COPY . .

# Autoriser Composer en mode super-utilisateur
ENV COMPOSER_ALLOW_SUPERUSER=1

# Configurer le dossier racine de Nginx pour pointer sur le dossier /public de Laravel
ENV WEB_DOCUMENT_ROOT=/app/public

# Installer les dépendances PHP pendant la phase de BUILD (garantit la présence de vendor/autoload.php)
RUN composer install --no-dev --optimize-autoloader

# Configurer les permissions d'écriture pour Laravel
RUN chown -R application:application /app/storage /app/bootstrap/cache
