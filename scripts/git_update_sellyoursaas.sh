#!/bin/bash
#---------------------------------------------------------
# Script to update programs for Dolibarr and sell-your-saas
#
# To include into cron
# /pathto/git_update_sellyoursaas.sh /home/admin/wwwroot > /pathto/git_update_sellyoursaas.log 2>&
#---------------------------------------------------------

source /etc/lsb-release

if [ "x$1" == "x" ]; then
   echo "Usage:   $0  dir_of_git_repositories_of_app"
   echo "Example: $0  /home/admin/wwwroot"
   exit 1
fi

if [ "$(id -u)" == "0" ]; then
   echo "This script must be run as admin, not as root" 1>&2
   exit 1
fi

error=0

echo "Update git dirs found into $1."

for dir in `ls -d $1/dolibarr* | grep -v documents`
do
	# If a subdir is given, discard if not subdir
	#if [ "x$2" != "x" ]; then
	#	if [ "x$1/$2" != "x$dir" ]; then
	#		continue;
	#	fi
	#fi

    echo -- Process dir $dir
    cd $dir
	if [ $? -eq 0 ]; then
		export gitdir=`basename $dir`
		
	    if [ -d ".git" ]; then
	    	echo chmod -R u+w $dir
	    	chmod -R u+w $dir
	    	git pull
	    	if [ $? -ne 0 ]; then
	    		# If git pull fail, we force a git reset before and try again.
	        	echo Execute a git reset --hard HEAD
	        	git reset --hard HEAD
	        	# Do not use git pull --depth=1 here, this will make merge errors.
	        	git pull
	        	if [ $? -ne 0 ]; then
	        		export error=1
	        	fi
	        fi
	        echo Result of git pull = $?

	    	git rev-parse HEAD > gitcommit.txt
	    else
	        echo "Not a git dir. Nothing done."
	    fi
		
	    cd -
	fi
done

echo "Finished (exit=$error)."
exit $error

