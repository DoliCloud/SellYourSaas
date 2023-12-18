#!/bin/bash

# Chemin du fichier cible
target_file1="/etc/letsencrypt/live/with.novafirstcloud.com/cert.pem"
target_file2="/etc/letsencrypt/live/with.novafirstcloud.com/chain.pem"
target_file3="/etc/letsencrypt/live/with.novafirstcloud.com/privkey.pem"

# Vérifier si le fichier cible existe
if [ ! -f "$target_file1" ]; then
    echo "Le fichier cible $target_file1 n'existe pas."
    exit 1
fi
if [ ! -f "$target_file2" ]; then
    echo "Le fichier cible $target_file2 n'existe pas."
    exit 1
fi
if [ ! -f "$target_file3" ]; then
    echo "Le fichier cible $target_file3 n'existe pas."
    exit 1
fi

# Parcourir tous les liens symboliques se terminant par ".with.novafirstcloud.com.crt"
find /etc/apache2 -type l -name "*.with.novafirstcloud.com.crt" 2>/dev/null | while read -r symlink; do
    # Supprimer le lien symbolique existant
    echo "Efface ancien lien $symlink"
    rm "$symlink"

    # Créer un nouveau lien symbolique pointant vers le fichier cible
    echo "Lien symbolique recréé par: ln -s $target_file1 $symlink"
    ln -s "$target_file1" "$symlink"
done

# Parcourir tous les liens symboliques se terminant par ".with.novafirstcloud.com.crt"
find /etc/apache2 -type l -name "*.with.novafirstcloud.com-intermediate.crt" 2>/dev/null | while read -r symlink; do
    # Supprimer le lien symbolique existant
    echo "Efface ancien lien $symlink"
    rm "$symlink"

    # Créer un nouveau lien symbolique pointant vers le fichier cible
    echo "Lien symbolique recréé par: ln -s $target_file2 $symlink"
    ln -s "$target_file2" "$symlink"
done

# Parcourir tous les liens symboliques se terminant par ".with.novafirstcloud.com.crt"
find /etc/apache2 -type l -name "*.with.novafirstcloud.com.key" 2>/dev/null | while read -r symlink; do
    # Supprimer le lien symbolique existant
    echo "Efface ancien lien $symlink"
    rm "$symlink"

    # Créer un nouveau lien symbolique pointant vers le fichier cible
    echo "Lien symbolique recréé par: ln -s $target_file3 $symlink"
    ln -s "$target_file3" "$symlink"
done
