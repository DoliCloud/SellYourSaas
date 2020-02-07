#!/bin/bash
#---------------------------------------------------------
# Script to update sources found into a directory
#
# To include into cron
# /pathto/git_update_sources.sh documentdir/sellyoursaas/git > /pathto/git_update_sources.log 2>&
#---------------------------------------------------------

if [ "x$1" == "x" ]; then
   echo "Usage:   $0  dir_document_of_git_repositories"
   echo "Example: $0  /pathtodocuments/documents/sellyoursaas/git"
   exit 1
fi

echo "Update git dirs found into $1"

#for dir in `find $1 -type d`
for dir in `ls -d $1/* | grep -v tar.gz`
do
    echo -- Process dir $dir
    if [ -d "$dir/.git" ]; then
        cd $dir
        git reset --hard HEAD
        git pull -- depth=1
        echo Result of git pull = $?
   
    	echo "Clean some dirs to save disk spaces"
    	rm -fr documents/*
    	rm -fr dev/ test/ doc/ htdocs/includes/ckeditor/ckeditor/adapters htdocs/includes/ckeditor/ckeditor/samples
    	rm -fr htdocs/includes/sabre/sabre/*/tests htdocs/includes/stripe/tests
    	rm -fr htdocs/includes/tecnickcom/tcpdf/fonts/dejavu-fonts-ttf-* htdocs/includes/tecnickcom/tcpdf/fonts/freefont-* htdocs/includes/tecnickcom/tcpdf/fonts/ae_fonts_*
    	#rm -fr vendor/tecnickcom/tcpdf/fonts/dejavu-fonts-ttf-* vendor/tecnickcom/tcpdf/fonts/freefont-* vendor/tecnickcom/tcpdf/fonts/ae_fonts_*
    	rm -fr files/_cache/*
    	# We remove subdir of build. We need files.
    	find build/* -type d -exec rm -fr {} \;
    	
        if [ -s build/generate_filelist_xml.php ]; then
                echo "Found generate_filelist_xml.php"
                php build/generate_filelist_xml.php release=auto-dolicloud
        fi
    
    	# Create a deployment tar file
    	echo "Compress the repository into an archive $dir.tar.gz"
		export gitdir=`basename $dir`
    	tar cz --exclude-vcs -f $dir/../$gitdir.tgz .

        cd -
    else
        echo "Not a git dir. Nothing done."
    fi
done

echo "Finished."
