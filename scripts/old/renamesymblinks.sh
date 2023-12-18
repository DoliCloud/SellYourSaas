#!/bin/bash

# Chemin du répertoire d'archive
archive_path="/etc/letsencrypt/archive"

# Chemin du répertoire live
live_path="/etc/letsencrypt/live"

# Parcourir les liens symboliques dans le répertoire d'archive
for link in $(find $archive_path -type l); do
    # Extraire le nom du lien symbolique
    link_name=$(basename $link)

    # Vérifier si le lien correspondant existe dans le répertoire live
    if [ -d "$live_path/$link_name" ]; then
        # Supprimer le lien symbolique existant
        echo "Delete link: $link"
#        rm $link

        # Créer un nouveau lien symbolique pointant vers le répertoire live
        echo "Reconstruit le lien avec: $ln -s $live_path/$link_name $link"
#        ln -s $live_path/$link_name $link
    else
        echo "Aucun répertoire correspondant trouvé pour: $link_name"
    fi
done
