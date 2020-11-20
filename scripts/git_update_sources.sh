#!/bin/bash
#---------------------------------------------------------
# Script to update sources found into a directory
#
# To include into cron
# /pathto/git_update_sources.sh documentdir/sellyoursaas/git > /pathto/git_update_sources.log 2>&
#---------------------------------------------------------

if [ "x$1" == "x" ]; then
   echo "Usage:   $0  dir_document_of_git_repositories [subdir]"
   echo "Example: $0  /pathtodocuments/documents/sellyoursaas/git"
   exit 1
fi

export currentpath=$(dirname "$0")

echo "Update git dirs found into $1 and generate the tgz image."

for dir in `ls -d $1/* | grep -v tgz`
do
	# If a subdir is given, discard if not subdir
	if [ "x$2" != "x" ]; then
		if [ "x$1/$2" != "x$dir" ]; then
			continue;
		fi
	fi

    echo -- Process dir $dir
    cd $dir
	if [ $? -eq 0 ]; then
		export gitdir=`basename $dir`

		
	    if [ -d ".git" ]; then
	    	git pull
	    	if [ $? -ne 0 ]; then
	    		# If git pull fail, we force a git reset before and try again.
	    		echo Execut a git reset --hard HEAD
	        	git reset --hard HEAD
	        	# Do not use git pull --depth=1 here, this will make merge errors.
	        	git pull
	        fi
	        echo Result of git pull = $?

	    	git rev-parse HEAD > gitcommit.txt
	    else
	        echo "Not a git dir. Nothing done."
	    fi
	   
		echo "Clean some dirs to save disk spaces"
		rm -fr documents/*
		rm -fr dev/ test/ doc/ htdocs/includes/ckeditor/ckeditor/adapters htdocs/includes/ckeditor/ckeditor/samples
		rm -fr htdocs/includes/sabre/sabre/*/tests htdocs/includes/stripe/tests htdocs/includes/stripe/stripe-php/tests
		rm -fr htdocs/includes/tecnickcom/tcpdf/fonts/dejavu-fonts-ttf-* htdocs/includes/tecnickcom/tcpdf/fonts/freefont-* htdocs/includes/tecnickcom/tcpdf/fonts/ae_fonts_*
		#rm -fr vendor/tecnickcom/tcpdf/fonts/dejavu-fonts-ttf-* vendor/tecnickcom/tcpdf/fonts/freefont-* vendor/tecnickcom/tcpdf/fonts/ae_fonts_*
		rm -fr files/_cache/*
		# We remove subdir of build. We need files.
		find build/* -type d -exec rm -fr {} \;
		
	    if [ -s build/generate_filelist_xml.php ]; then
	        echo "Found generate_filelist_xml.php"
	        php build/generate_filelist_xml.php release=auto-sellyoursaas
	    fi
	
		# Create a deployment tar file
		echo "Compress the repository into an archive $dir.tar.gz"
		tar cz --exclude-vcs --exclude-from=$currentpath/git_update_sources.exclude -f $dir/../$gitdir.tgz .
	
	    cd -
	fi
done

echo "Finished."
