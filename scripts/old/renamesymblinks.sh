#!/bin/bash

# Chemin du fichier cible
target_file="/etc/letsencrypt/archive/with.novafirstcloud.com/cert.pem"

# Vérifier si le fichier cible existe
if [ ! -f "$target_file" ]; then
    echo "Le fichier cible $target_file n'existe pas."
    exit 1
fi

# Parcourir tous les liens symboliques se terminant par ".with.novafirstcloud.com.crt"
find /etc/apache2 -type l -name "*.with.novafirstcloud.com.crt" 2>/dev/null | while read -r symlink; do
    # Supprimer le lien symbolique existant
    echo "Efface ancien lien $symlink"
    rm "$symlink"

    # Créer un nouveau lien symbolique pointant vers le fichier cible
    echo "Lien symbolique recréé par: ln -s $target_file $symlink"
    ln -s "$target_file" "$symlink"
done
