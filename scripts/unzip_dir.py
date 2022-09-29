#!/usr/bin/env python2
import os
from os import path

# already created directories, walk works topdown, so a child dir
# never creates a directory if there is a parent dir with a file.
made_dirs = set()

for root, dir_names, file_names in os.walk('.'):
    for file_name in dir_names:
        if '\\' not in file_name:
            continue
        alt_file_name = file_name.replace('\\', '/')
        if alt_file_name.startswith('/'):
            alt_file_name = alt_file_name[1:]  # cut of starting dir separator
        alt_dir_name, alt_base_name = alt_file_name.rsplit('/', 1)
        print 'alt_dir', alt_dir_name
        full_dir_name = os.path.join(root, alt_dir_name)
        if full_dir_name not in made_dirs:
            print full_dir_name;
            if not path.exists(full_dir_name):
                os.makedirs(full_dir_name)  # only create if not done yet
            made_dirs.add(full_dir_name)
        print 'rename ', os.path.join(root, file_name), os.path.join(root, alt_file_name);
        os.rename(os.path.join(root, file_name), os.path.join(root, alt_file_name))
                  
