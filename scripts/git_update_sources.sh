#!/bin/bash
#---------------------------------------------------------
# Script to update sources found into a directory
#
# To include into cron
# /pathto/git_update_sources.sh documentdir/sellyoursaas/git all > /pathto/git_update_sources.log 2>&
#---------------------------------------------------------

source /etc/lsb-release

if [ "x$1" == "x" ]; then
   echo "Usage:   $0  dir_document_of_git_repositories [subdir|all]"
   echo "Example: $0  /pathtodocuments/documents/sellyoursaas/git"
   exit 1
fi

export usecompressformatforarchive=`grep '^usecompressformatforarchive=' /etc/sellyoursaas.conf | cut -d '=' -f 2`

export currentpath=$(dirname "$0")

if [ "$(id -u)" == "0" ]; then
   echo "This script must be run as admin (on master server), not as root" 1>&2
   exit 1
fi

echo "Update git dirs found into $1 and generate the archive file (.zst or .tgz)"

for dir in $(find "$1" -mindepth 1 -maxdepth 1 -type d)
do
	# If a subdir is given, discard if not subdir
	if [ "x$2" != "x" -a "x$2" != "xall" ]; then
		if [ "x$1/$2" != "x$dir" ]; then
			continue;
		fi
	fi

    echo -- Process dir $dir
    cd $dir || continue
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
		has_install_lock=''
		if [[ -f documents/install.lock ]]; then has_install_lock='1'; fi
		rm -fr documents/*
		rm -fr dev/ test/ doc/ htdocs/includes/ckeditor/ckeditor/adapters htdocs/includes/ckeditor/ckeditor/samples
		rm -fr htdocs/public/test
		rm -fr htdocs/includes/sabre/sabre/*/tests htdocs/includes/stripe/tests htdocs/includes/stripe/stripe-php/tests
		rm -fr htdocs/includes/tecnickcom/tcpdf/fonts/dejavu-fonts-ttf-* htdocs/includes/tecnickcom/tcpdf/fonts/freefont-* htdocs/includes/tecnickcom/tcpdf/fonts/ae_fonts_*
		rm -fr htdocs/install/doctemplates/websites/website_template-restaurant*
		#rm -fr vendor/tecnickcom/tcpdf/fonts/dejavu-fonts-ttf-* vendor/tecnickcom/tcpdf/fonts/freefont-* vendor/tecnickcom/tcpdf/fonts/ae_fonts_*
		rm -fr files/_cache/*
		# We remove subdir of build. We need files into build root only.
		#find build/* -type d -delete
		find build/* -depth -type d -exec rm -fr {} \;
		echo "Clean some files to save disk spaces"
		find . -type f -name index.html ! -path ./htdocs/includes/restler/framework/Luracast/Restler/explorer/index.html -delete
		
	    if [ -s build/generate_filelist_xml.php ]; then
	        echo "Found generate_filelist_xml.php from ".`pwd`
	        php build/generate_filelist_xml.php release=auto-sellyoursaas buildzip=1
	    fi
	
		# Create a deployment tar file
		if [[ -x /usr/bin/zstd && "x$usecompressformatforarchive" == "xzstd" ]]; then
			echo "Compress the repository into an archive $dir/../$gitdir.tar.zst"
			tar c -I zstd --exclude-vcs --exclude-from=$currentpath/git_update_sources.exclude -f $dir/../$gitdir.tar.zst .
			# Delete archive in other format
			rm $dir/../$gitdir.tgz 2>/dev/null
		else
			echo "Compress the repository into an archive $dir/../$gitdir.tgz"
			tar c -I gzip --exclude-vcs --exclude-from=$currentpath/git_update_sources.exclude -f $dir/../$gitdir.tgz .
			# Delete archive in other format
			rm $dir/../$gitdir.tar.zst 2>/dev/null
		fi

		# restore previously deleted install.lock
		if [[ -n "$has_install_lock" ]]; then touch 'documents/install.lock'; fi

		cd -
	fi
done

echo "Finished."
