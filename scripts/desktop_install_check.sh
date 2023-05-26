#!/bin/bash
#----------------------------------------------------------------
# This script allows to launch an apt update / upgrade on remote servers
#----------------------------------------------------------------


#set -e

source /etc/lsb-release

if [ "x$1" == "x" ]; then
   echo "Usage:   $0  hostfile  [hostgrouporname]  (apache|php70|php72|php74|php80|php81|php82)"
   echo "         [hostgrouporname] can be 'master', 'deployment' or list separated with comma like 'master,deployment' (default)"
   echo "Example: $0  myhostfile  master,deployment"
   echo "Example: $0  myhostfile  withX.mysellyoursaasdomain.com"
   exit 1
fi

target=$2
if [ "x$target" == "x" ]; then
	target="master"
fi

if [ "x$3" == "xall" ]; then
	all=1
fi
if [ "x$3" == "xphp70" ]; then
	php70=1
fi
if [ "x$3" == "xphp72" ]; then
	php72=1
fi
if [ "x$3" == "xphp74" ]; then
	php74=1
fi
if [ "x$3" == "xphp80" ]; then
	php80=1
fi
if [ "x$3" == "xphp81" ]; then
	php81=1
fi
if [ "x$3" == "xphp82" ]; then
	php82=1
fi
if [ "x$3" == "xapache" ]; then
	apache=1
fi

if [ "x$target" == "xweb" ]; then
	echo "This script is designed for master and deployment servers only."
	exit 2
fi
if [ "x$target" == "xremotebackup" ]; then
	echo "This script is designed for master and deployment servers only."
	exit 2
fi

export currentpath=$(dirname "$0")

cd $currentpath/ansible

echo "Execute ansible for host group $1 and targets $target"
pwd


#command="ansible-playbook -K launch_install_check.yml -i hosts-$1 -e 'target="$target"' --limit=*.mydomain.com"

if [ "x$all" == "x1" ]; then
	command='ansible-playbook -K launch_install_check.yml -i hosts-'$1' -e "target='$target' apache='1' php74='1'"'
elif [ "x$php70" == "x1" ]; then
	command='ansible-playbook -K launch_install_check.yml -i hosts-'$1' -e "target='$target' php70='1'"'
elif [ "x$php72" == "x1" ]; then
	command='ansible-playbook -K launch_install_check.yml -i hosts-'$1' -e "target='$target' php72='1'"'
elif [ "x$php74" == "x1" ]; then
	command='ansible-playbook -K launch_install_check.yml -i hosts-'$1' -e "target='$target' php74='1'"'
elif [ "x$php80" == "x1" ]; then
	command='ansible-playbook -K launch_install_check.yml -i hosts-'$1' -e "target='$target' php80='1'"'
elif [ "x$php81" == "x1" ]; then
	command='ansible-playbook -K launch_install_check.yml -i hosts-'$1' -e "target='$target' php81='1'"'
elif [ "x$php82" == "x1" ]; then
	command='ansible-playbook -K launch_install_check.yml -i hosts-'$1' -e "target='$target' php82='1'"'
elif [ "x$apache" == "x1" ]; then
	command='ansible-playbook -K launch_install_check.yml -i hosts-'$1' -e "target='$target' apache='1'"'
else
	command='ansible-playbook -K launch_install_check.yml -i hosts-'$1' -e "target='$target'"'
fi

echo "$command"
eval $command

echo "Finished."
