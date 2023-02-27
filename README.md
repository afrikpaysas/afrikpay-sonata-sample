Installation des dépendacnes
Composer update 

Create the database 
php bin/console doctrine:database:create
ou modifier les paramètres dans le fichier .env.local

Mise à jour de la base de données pour création de la table 
de l'utilisateur
php bin/console doctrine:schema:update -f

Création du superAdmin
php bin/console sonata:user:create --super-admin admin admin@admin.fr admin

