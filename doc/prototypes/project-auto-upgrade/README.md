# Projet de création d’auto-upgrade d’une instance pour la solution DoliCloud

Suite à la demande de création d’une fonction d’auto-migration d’instances pour la solution DoliCloud. J’ai modélisé cette fonctionnalité à l’aide d’un logiciel de wireframe. Celui-ci m’a permis d’exporter une version HTML du modèle qui peut être utilisée comme maquette.
___
Nous allons donc suivre le modèle pour essayer de voir comment celle-ci fonctionne :

## Main page :

Cette page correspond à la page template support. Il suffit de cliquer sur la combobox pour y mettre Migration.

## Main page auto-upgrade :

Cette page est la même que la précédente mais avec une fonction Javascript qui vérifie le code de ticket dans la combobox Category, si le code de ticket est celui pour l'upgrade alors la fonction Javascript affiche comme présenté sur la page Mainpage auto-upgrade.

Deux choix sont alors possibles :
* Le bouton de gauche permet de continue vers l’auto-upgrade de l’instance

* Celui de droite permet de revenir à l’affichage normal de la template support avec des informations pour le support.

## Auto-upgrade page 1 :

Cette page contient des informations nécessaires à l'auto-upgrade

* Step 1 : Dans cette étape l'utilisateur doit choisir entre toutes ces instances pour être sur de l'instance sur laquelle pratiquer l'auto-upgrade

* Step 2 : Dans cette étape on trouve un tableau similaire à la page migration de Dolibarr mais adapté à l'auto-upgrade des instances.

Pour lancer l'auto-upgrade vers la version voulue, il suffit de cliquer sur le bouton de la version voulu.

## Auto-upgrade page 2 verification succes & erreur :

### Succès

Lorsque la vérification se passe sans erreur alors 2 boutons apparaitront sur la page:
* Le premier pour procéder à l'auto-migration
* Le second pour annuler l'auto-upgrade et revenir au formulaire d'envoi de tickets préremplis.

### Erreur

Lorsque la verification se solde par un echec un encars s'affiche avec la liste des erreurs. En plus un bouton pour revenir au support s'affiche.

## Upgrade success/error

### Succès

Lorsque la vérification se passe sans erreur alors un lien vers l'instance de l'utilisateur est disponible pour qu'il puisse se connecter.

### Erreur

Lorsqu'une erreur est levée, un bouton s'affiche pour renvoyer vers la page de support et remplit l'encart de texte du formulaire.

