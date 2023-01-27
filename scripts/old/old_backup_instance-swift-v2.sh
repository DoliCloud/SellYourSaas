#!/bin/bash

NODE="$(hostname --fqdn)"

FullBackupDay="Sun"

BackupPath="/home/jail/backup/"
mkdir "${BackupPath}logs"

excludeinstances="testldr1.with.dolicloud.com testldr2.with.dolicloud.com testldr3.with.dolicloud.com"

DATE=$(date +%Y%m%d)
DATEFULL=$(date -u +%Y%m%d-%H%M-%Z)

SENDMAIL_BIN='/usr/sbin/sendmail'
FROM_MAIL_ADDRESS="robot@dolicloud.com"
FROM_MAIL_DISLAY="Backups DoliCloud $(hostname | awk -F. '{print $1}')"
RECIPIENT_ADDRESSES='supervision@dolicloud.com'

HOMEDIRS="/home/jail/home"
VHOSTSDIR="/etc/apache2/sellyoursaas-online/"


LOCKFILE="${BackupPath}SellYourSaasCustomerInstancesBackup.lock"
LOGFILE="${BackupPath}logs/${DATE}-SellYourSaasCustomerInstancesBackup.log"
REPORTFILE="${BackupPath}logs/${DATE}-Report.txt"

MaxAllowedSize="10737418240" # bytes

StorageBackend="aws" # aws / swift "
##### swift #####
Endpoint="http://sws.dolicloud.com:5000/v2.0"
Container="mycontainer"
Tenant="mytenant"
MaxSingleFileSize="5368709122" # bytes
SegmentSize="1073741824" # bytes
#### aws s3 #####
export AWS_CONFIG_FILE=${HOME}/.aws-config
BUCKET="s3://dolicloud/backup/"

User="$1"
Password="$2"

echo Search sellyoursaas credential
passsellyoursaas=`grep 'databasepass=' /etc/sellyoursaas.conf | cut -d '=' -f 2`
if [[ "x$passsellyoursaas" == "x" ]]; then
	echo Search sellyoursaas credential 2
	passsellyoursaas=`cat /tmp/sellyoursaas`
	if [[ "x$passsellyoursaas" == "x" ]]; then
		echo Failed to get password for mysql user sellyoursaas 
		exit 1
	fi
fi 

MysqlRootUser="sellyoursaa"
MysqlRootPass="$passsellyoursaas"


# This is a sample script no used. Exit is to avoid to execute it.
exit


########################################
########################################

incPath="${BackupPath}inc-files/"
[ -d ${incPath} ] || mkdir -p ${incPath}
[ -d ${BackupPath}logs/ ] || mkdir -p ${BackupPath}logs/
cd ${BackupPath}

#Deleting the lockfile if it is there for last two days.
find $LOCKFILE -type f -mtime +2 -delete

echo -e "\n\n[ $(date) ]: Incremental Backup is about to start." >> $LOGFILE
COUNTER=0
SLEEP=30m
while [ -e "${LOCKFILE}" ]
do
        echo -n "[ $(date) ]: Another backup process running (check: $COUNTER)..." >> $LOGFILE
        COUNTER="$(expr $COUNTER + 1)"
        if [ $COUNTER -gt 10 ]
        then
                echo "[ $(date) ]: Timeout reached ... " >> $LOGFILE
		echo "[ $(date) ]: Warning: Backup will not continue!!! Timedout and exiting." >> $LOGFILE
                MAIL_CMD="$SENDMAIL_BIN -f $FROM_MAIL_ADDRESS -F \"$FROM_MAIL_DISLAY\" $RECIPIENT_ADDRESSES"
		(echo "Subject: Timeout reached ...";
		echo -e "MIME-Version: 1.0\nContent-Type: text/html;\n" &&
		echo '<pre>' &&
		echo "Logs :" &&
		echo "" &&
		sort $LOGFILE ;
		echo "" &&
		echo '</pre>') | eval $MAIL_CMD
                exit 1
        else
                echo "[ $(date) ]: Sleeping for $SLEEP" >> $LOGFILE
                sleep $SLEEP
                continue
        fi
done

if [[ $(date +%a) = "${FullBackupDay}" ]]; then
        rm -f ${incPath}*
fi

touch "${LOCKFILE}"

TotalStartTime="$(date +%s)"

echo "[ $(date) ]: Backup started" >> $LOGFILE

HOMEDIRLIST="$(ls -1 $HOMEDIRS/*/*/htdocs/index.php)"
TOTALHOMEDIRS="$(echo ${HOMEDIRLIST} | tr ' ' '\n' | wc -l)"
echo "[ $(date) ]: I'll backup $TOTALHOMEDIRS homedirs" >> $LOGFILE

failedinstances=0
shootmail=0
expired=0
excluded=0
undefined=0
dbtimetotal=0
tartimetotal=0
dbsizetotal=0
sourcesizetotal=0
filecounter=0

for files in $HOMEDIRLIST; do

	(( filecounter += 1 ))

	echo '##################################################################' >> $LOGFILE
	echo "-------------   ${filecounter}.  -------------" >> $LOGFILE
	echo '##################################################################' >> $LOGFILE
	echo "${files}" >> $LOGFILE
	echo '##################################################################' >> $LOGFILE
	echo "" >> $LOGFILE
	echo "Top 4 Processes $(date +%T)" >> $LOGFILE
	echo "$(ps -eo pcpu,pid -o comm= | sort -k1 -n -r | head -4)" >> $LOGFILE
	echo "Cpu load $(date +%T): $(ps -eo pcpu,pid -o comm= | sort -k1 -n -r | head -1 | awk '{ print $1 } ')" >> $LOGFILE
	echo "" >> $LOGFILE

	startTime="$(date +%s)"
	oops=0
	dbfail=0
	fails=0
	exclude=0
	vhostfail=0

	mbtDir="$(dirname $files)"

	mbtConf="${mbtDir}/conf/conf.php"
        cfgParams="$(grep dolibarr_main $mbtConf | egrep 'dolibarr_main_db_name|dolibarr_main_db_user|dolibarr_main_db_pass' | sed 's/\s//g')"
        mysqldbName=$(echo $cfgParams | tr ' ' '\n' | grep dolibarr_main_db_name | cut -f2 -d "'")
        dbUser=$(echo $cfgParams | tr ' ' '\n' | grep dolibarr_main_db_user | cut -f2 -d "'")
        dbPass=$(echo $cfgParams | tr ' ' '\n' | grep dolibarr_main_db_pass | cut -f2 -d "'")

	homeDir="$(dirname $(dirname $mbtDir))"
	homeName="$(basename $homeDir)"
	vhostfile=$(find ${VHOSTSDIR} -maxdepth 2 -type f ! -name "*.php" ! -name "*.txt*" ! -name ".*" ! -name "*.htm" ! -name "*.html" -exec grep ${homeDir} {} \; -exec ls {} \; | tail -1)

	if [[ -z ${vhostfile} ]]; then
		if [[ -d ${homeDir} ]]; then
			vhostfile=$(find ${homeDir} -maxdepth 2 -type f ! -name "*.sh" ! -name "*.sql" ! -name "*.db" ! -name "*.php" ! -name "*.txt*" ! -name ".*" ! -name "*.htm" ! -name "*.html" -exec grep ${homeDir} {} \; -exec ls {} \; | tail -1)
			if [[ -z ${vhostfile} ]]; then
				echo -e "\e[05;31m[ $(date) ] WARNING: There is no VHOST file for instance $homeName !!!\e[00m" >> $LOGFILE

	                        echo -e "\e[05;31m[ $(date) ] WARNING: Moving instance $homeName Source to ${BackupPath}Trash/${DATE}/${homeName}/Source \e[00m" >> $LOGFILE
        	                mkdir -p ${BackupPath}Trash/${DATE}/${homeName}/Source
                	        mkdir -p ${BackupPath}Trash/${DATE}/${homeName}/TXT
                        	mv ${homeDir} ${BackupPath}Trash/${DATE}/${homeName}/Source/

	                        echo -e "\e[05;31m[ $(date) ] WARNING: Removing instance $homeName System User \e[00m" >> $LOGFILE
        	                cat /etc/passwd | grep ${homeName} > ${BackupPath}Trash/${DATE}/${homeName}/TXT/passwd.txt
                	        cat /etc/group | grep ${homeName} > ${BackupPath}Trash/${DATE}/${homeName}/TXT/group.txt
                        	cat /etc/shadow | grep ${homeName} > ${BackupPath}Trash/${DATE}/${homeName}/TXT/shadow.txt
				sed -i "/${homeName}/d" /etc/passwd
				sed -i "/${homeName}/d" /etc/group
				sed -i "/${homeName}/d" /etc/shadow

                        	if [[ ! -z ${mysqldbName} && ! -z ${dbUser} && ! -z ${dbPass} ]]; then
                                	echo -e "\e[05;31m[ $(date) ] WARNING: Dumping instance $homeName DB to ${BackupPath}Trash/${DATE}/${homeName}/DB \e[00m" >> $LOGFILE
	                                mkdir -p ${BackupPath}Trash/${DATE}/${homeName}/DB
        	                        nice -n19 ionice -c3 mysqldump --opt $mysqldbName -u$dbUser -p$dbPass > ${BackupPath}Trash/${DATE}/${homeName}/DB/${DATEFULL}.db

                	                echo -e "\e[05;31m[ $(date) ] WARNING: Droping instance $homeName DB and DBUser \e[00m" >> $LOGFILE
                        	        mysql -u ${MysqlRootUser} -p"${MysqlRootPass}" -e "DROP DATABASE $mysqldbName;"
                                	mysql -u ${MysqlRootUser} -p"${MysqlRootPass}" -e "DROP USER $dbUser;"
	                                mysql -u ${MysqlRootUser} -p"${MysqlRootPass}" -e "FLUSH PRIVILEGES;"
				else
					echo -e "\e[05;31m[ $(date) ] WARNING: Could not get DB credentials for $homeName. Skipping DB drop !  \e[00m" >> $LOGFILE
        	                fi

                	        echo "dbName= $mysqldbName" >> ${BackupPath}Trash/${DATE}/${homeName}/TXT/database_creds.txt
                        	echo "dbUser= $dbUser" >> ${BackupPath}Trash/${DATE}/${homeName}/TXT/database_creds.txt
	                        echo "dbPass= $dbPass" >> ${BackupPath}Trash/${DATE}/${homeName}/TXT/database_creds.txt
        	                echo "" >> $LOGFILE

				((undefined += 1))
				vhostfail=1
				oops=1
			else
				state="Expired"
				((expired += 1))
			fi
		else
			echo -e "\e[05;31m[ $(date) ] Instance $homeName has already been undeployed !\e[00m" >> $LOGFILE
			echo "" >> $LOGFILE
			exclude=1
			((TOTALHOMEDIRS -= 1))
		fi
	else
		state="Active"
	fi

	if [[ ! -z ${vhostfile} ]]; then
		siteUrl="$(cat ${vhostfile} | grep ServerName | tail -1 | awk '{print $NF}')"
		instancepath="${DATE}/${NODE}/${siteUrl}/${homeName}/"
		echo -e "[ $(date) ]: Instance $homeName URL: ${siteUrl} is in ${state} state !!!" >> $LOGFILE
		if [[ -z ${mysqldbName} || -z ${dbUser} || -z ${dbPass} ]]; then
		        echo -e "\e[05;31m[ $(date) ]: Could not get DB details for the ${state} instance $homeName URL: ${siteUrl} !!!\e[00m" >> $LOGFILE
			oops=1
			if [[ "$(cat Failed.Backups | grep ${DATE}-${homeName})" = "" ]]; then
				echo "${DATE}-${homeName}" >> Failed.Backups
			fi
		fi
	fi

	if [[ $(echo "${excludeinstances}" | grep -w "${siteUrl}") != "" ]];then
                echo -e "\e[05;31m[ $(date) ]: Excluding $homeName URL: ${siteUrl} !!!\e[00m" >> $LOGFILE
                exclude=1
		((excluded += 1))
        fi

	if [[ ${oops} -eq 0 && ${exclude} -eq 0 ]]; then

		mkdir -p ${instancepath}

		if [[ -f ${incPath}${homeName}.inc ]]; then
			BACKUP_TYPE="INCREMENTAL"
		else
			BACKUP_TYPE="TOTAL"
		fi

		# Reset Filenames
		dbfile=""
		dbtar=""
		hometar=""
		combinedArchiveName=""

		for i in dbback dbcomp homecomp comb; do
			procstat=0
			while [[ $procstat -lt 3 ]]; do

				if [[ "${i}" = "dbback" ]]; then
					echo -e "[ $(date) ]: Starting SqlDump for instance $homeName URL: ${siteUrl}" >> $LOGFILE
					[ ! -z ${dbfile} ] && rm -f ${instancepath}${dbfile}.db
					dbfile="${homeName}_DB_TOTAL_$(date -u +%Y%m%d-%H%M-%Z)"
#					echo -e "[ $(date) ]: mysqldump --opt $mysqldbName -u$dbUser -p$dbPass > ${instancepath}${dbfile}.db" >> $LOGFILE
					dbtimestart="$(date +%s)"
					nice -n19 ionice -c3 mysqldump --opt $mysqldbName -u$dbUser -p$dbPass > ${instancepath}${dbfile}.db 2>> $LOGFILE
#					dbtimeend="$(date +%s)"
#					dbtime=$(expr ${dbtimeend} - ${dbtimestart})
#					echo -e "[ $(date) ]: Time taken to dump $mysqldbName of ${siteUrl} = ${dbtime} seconds" >> $LOGFILE
#					dbtimetotal=$(expr ${dbtime} + ${dbtimetotal})
					if [ $? -ne 0 ]; then
						((procstat += 1))
						if [[ $procstat -eq 3 ]]; then
							echo -e "\e[05;31m[ $(date) ]: mysqldump failed for ${state} instance $homeName URL: ${siteUrl} after ${procstat} tries\e[00m" >> $LOGFILE
							[ -f ${instancepath}${dbfile}.db ] && rm -f ${instancepath}${dbfile}.db
							dbfail=1
						else
							echo "[ $(date) ]: Problems with locking ... fixing..." >> $LOGFILE
#							echo "[ $(date) ]: grant LOCK TABLES on \`$mysqldbName\`.* to '$dbUser'@'%';" >> $LOGFILE
							echo "grant LOCK TABLES on \`$mysqldbName\`.* to '$dbUser'@'%';" | mysql -u${MysqlRootUser} -p${MysqlRootPass}
							echo "[ $(date) ]: mysqldump failed for ${siteUrl} . Retry ${procstat}" >> $LOGFILE
						fi
					else
#						if [[ $(expr ${MaxAllowedSize} - $(du -bs ${instancepath}${dbfile}.db | awk '{print $1}')) -lt 0 ]]; then
#							size=$(echo "scale=3;$(du -bs ${instancepath}${dbfile}.db | awk '{print $1}')/1024/1024/1024"|bc)
#							echo -e "\e[05;31m[ $(date) ]: Database size of ${state} instance $homeName URL: ${siteUrl} is ${size} GB\e[00m" >> $LOGFILE
#							shootmail=1
#						fi

						dbtimeend="$(date +%s)"
						dbtime=$(expr ${dbtimeend} - ${dbtimestart})
						echo -e "[ $(date) Report ]:[ ${dbtime} seconds ] - Time taken to dump $mysqldbName of ${siteUrl} " >> $LOGFILE
						dbtimetotal=$(expr ${dbtime} + ${dbtimetotal})

						dbsize=$(nice -n19 ionice -c3 mysql -u$dbUser -p$dbPass -e "SELECT table_schema $mysqldbName, SUM( data_length + index_length) 'Data Base Size in Bytes' FROM information_schema.TABLES GROUP BY table_schema;" | tail -1 | awk '{print $NF}')
						dbsizeMB=$(echo "scale=3;${dbsize}/1024/1024"|bc)
						if [[ $(expr ${MaxAllowedSize} - ${dbsize}) -lt 0 ]]; then
							echo -e "\e[05;31m[ $(date) Report ]:[ ${dbsizeMB} MB ] - Database size of ${state} instance $homeName URL: ${siteUrl}\e[00m" >> $LOGFILE
							shootmail=1
						else
							echo -e "[ $(date) Report ]:[ ${dbsizeMB} MB ] - Database size of ${state} instance $homeName URL: ${siteUrl}" >> $LOGFILE
						fi
						dbsizetotal=$(expr ${dbsize} + ${dbsizetotal})
						break
					fi
				elif [[ "${i}" = "dbcomp" ]]; then
					echo -e "[ $(date) ]: Compressing SqlDump for instance $homeName URL: ${siteUrl}" >> $LOGFILE
					[ ! -z $dbtar ] && rm -f ${instancepath}$dbtar
					dbtar="${dbfile}.tar.gz"
#					echo -e "[ $(date) ]: GZIP=-1 nice -n19 ionice -c3 tar -cpzf ${instancepath}${dbtar} --directory=${instancepath} ${dbfile}.db" >> $LOGFILE
					tartimestart="$(date +%s)"
					GZIP=-1 nice -n19 ionice -c3 tar -cpzf ${instancepath}${dbtar} --directory=${instancepath} ${dbfile}.db &
				elif [[ "${i}" = "homecomp" ]]; then
					echo -e "[ $(date) ]: Compressing Home files for instance $homeName URL: ${siteUrl}" >> $LOGFILE
					[ ! -z $hometar ] && rm -f ${instancepath}$hometar
					hometar="${homeName}_SOURCE_${BACKUP_TYPE}_$(date -u +%Y%m%d-%H%M-%Z).tar.gz"
					sourcesize=$(nice -n19 ionice -c3 du -bs $homeDir | awk '{print $1}')
					sourcesizeMB=$(echo "scale=3;${sourcesize}/1024/1024"|bc)
#					if [[ ${procstat} -eq 0 && $(expr ${MaxAllowedSize} - $(du -bs $homeDir | awk '{print $1}')) -lt 0 ]]; then
					if [[ ${procstat} -eq 0 && $(expr ${MaxAllowedSize} - ${sourcesize}) -lt 0 ]]; then
#						size=$(echo "scale=3;$(du -bs $homeDir | awk '{print $1}')/1024/1024/1024"|bc)
						echo -e "\e[05;31m[ $(date) Report ]:[ ${sourcesizeMB} MB ] - Source size of ${state} instance $homeName URL: ${siteUrl}\e[00m" >> $LOGFILE
						sourcesizetotal=$(expr ${sourcesize} + ${sourcesizetotal})
						shootmail=1
					elif [[ ${procstat} -eq 0 ]]; then
						echo -e "[ $(date) Report ]:[ ${sourcesizeMB} MB ] - Source size of ${state} instance $homeName URL: ${siteUrl}" >> $LOGFILE
						sourcesizetotal=$(expr ${sourcesize} + ${sourcesizetotal})
					fi
#					echo -e "[ $(date) ]: GZIP=-1 nice -n19 ionice -c3 tar --listed-incremental ${incPath}$homeName.inc -cpzf ${instancepath}$hometar $homeDir" >> $LOGFILE
					tartimestart="$(date +%s)"
					GZIP=-1 nice -n19 ionice -c3 tar --listed-incremental ${incPath}$homeName.inc -cpzf ${instancepath}$hometar $homeDir &
#					GZIP=-1 nice -n19 ionice -c3 tar --listed-incremental ${incPath}$homeName.inc -cpzf ${instancepath}$hometar ${mbtDir}/config.inc.php ${mbtDir}/parent_tabdata.php ${mbtDir}/tabdata.php ${mbtDir}/storage ${mbtDir}/user_privileges ${mbtDir}/test &
				elif [[ "${i}" = "comb" ]]; then
					echo -e "[ $(date) ]: Combining Home and SQL files for instance $homeName URL: ${siteUrl}" >> $LOGFILE
					[ ! -z $combinedArchiveName ] && rm -f ${instancepath}$combinedArchiveName
					combinedArchiveName="${homeName}_COMBINED_${BACKUP_TYPE}_$(date -u +%Y%m%d-%H%M-%Z).tar"
#					echo -e "[ $(date) ]: tar -cpf $combinedArchiveName $tarName $dbNameFull" >> $LOGFILE
					nice -n19 ionice -c3 tar -cpf ${instancepath}$combinedArchiveName --directory=${instancepath} $hometar $dbtar &
				fi

				if [[ ${dbfail} -eq 1 ]]; then
					break
				fi

				pid=$!
				t=240 # ${t}x15sec = 60 min to complete

				if [[ "${i}" != "dbback" ]]; then
					while [[ $t -gt 0 ]]; do
						echo "Top 4 Processes $(date +%T)" >> $LOGFILE
						echo "$(ps -eo pcpu,pid -o comm= | sort -k1 -n -r | head -4)" >> $LOGFILE
						echo "Cpu load $(date +%T): $(ps -eo pcpu,pid -o comm= | sort -k1 -n -r | head -1 | awk '{ print $1 } ')" >> $LOGFILE
						echo "Tar process info :" >> $LOGFILE
						echo "$(ps aux | grep ${pid} | grep -v grep)" >> $LOGFILE
						echo "" >> $LOGFILE
						sleep 3
						if [[ $(kill -0 $pid; echo $?) -ne 0 ]]; then
							procstat=3
							fails=0
							if [[ "${i}" = "dbcomp" ]]; then
								rm -f ${instancepath}${dbfile}.db
							fi
							break
						fi
						((t -= 1))
						sleep 12
					done
					if [[ $t -le 0 ]]; then
						kill -9 $pid
						((procstat += 1))
						echo -e "\e[05;31m[ $(date) ]: Compression TIMED OUT !!! Retry:${procstat} !!!\e[00m" >> $LOGFILE
						fails=1
						if [[ $procstat -eq 3 ]]; then
							echo -e "\e[05;31m[ $(date) ]: Backup failed for ${state} instance $homeName URL: ${siteUrl}\e[00m" >> $LOGFILE
							echo "${DATE}-${homeName}" >> Failed.Backups
						fi
					else
						tartimeend="$(date +%s)"
						tartime=$(expr ${tartimeend} - ${tartimestart})
						if [[ "${i}" = "dbcomp" ]]; then
							echo -e "[ $(date) Report ]:[ ${tartime} seconds ] - Time taken to tar dumped DB: $mysqldbName of ${siteUrl}" >> $LOGFILE
						elif [[ "${i}" = "homecomp" ]]; then
							echo -e "[ $(date) Report ]:[ ${tartime} seconds ] - Time taken to tar Source files of ${siteUrl}" >> $LOGFILE
						fi
						tartimetotal=$(expr ${tartime} + ${tartimetotal})
					fi
					if [[ "${i}" = "comb" && ${fails} -eq 0 ]]; then
						nice -n19 ionice -c3 tar -tf ${instancepath}$combinedArchiveName >/dev/null
						if [[ $? -ne 0 ]];then
							((procstat += 1))
							echo -e "\e[05;31m[ $(date) ]: Archive failed list test !!! Retry:${procstat} !!!\e[00m" >> $LOGFILE
							fails=1
							if [[ $procstat -eq 3 ]]; then
								echo -e "\e[05;31m[ $(date) ]: Backup failed for ${state} instance $homeName URL: ${siteUrl}\e[00m" >> $LOGFILE
								echo "${DATE}-${homeName}" >> Failed.Backups
							fi
						else
							fileup="${instancepath}${combinedArchiveName}"
							if [[ $(expr ${MaxSingleFileSize} - $(du -bs ${fileup} | awk '{print $1}')) -lt 0 ]]; then
								listSeg="${fileup} ${listSeg}"
							else
								list="${fileup} ${list}"
							fi
							echo "[ $(date) ]: Backup of ${siteUrl} finished successfully and file added to upload list" >> $LOGFILE
							rm -f ${instancepath}$hometar ${instancepath}$dbtar
							endTime="$(date +%s)"
							elapsedsec="$(expr $endTime - $startTime)"
							elapsed=$(printf ""%.2dh:%.2dm:%.2ds"\n" $((${elapsedsec}/3600)) $((${elapsedsec}%3600/60)) $((${elapsedsec}%60)))
							echo -e "[ $(date) Report ]: Time to backup ${siteUrl} Home:${homeName} was $elapsed \n\n" >> $LOGFILE
						fi
					fi
				fi
			done
			if [[ ${dbfail} -ne 0 || ${fails} -ne 0 ]]; then
				echo "${DATE}-${homeName}" >> Failed.Backups
				echo -e "\e[05;31m[ $(date) ]: Backup failed for ${state} instance URL:${siteUrl} Home:${homeName}\e[00m" >> $LOGFILE
				shootmail=1
				((failedinstances += 1))
				break
			fi
		done
	elif [[ ${oops} -ne 0 ]]; then
		shootmail=1
		if [[ ${vhostfail} -eq 0 ]]; then
			((failedinstances += 1))
		fi
	fi
done

rm "${LOCKFILE}"

TotalEndTime="$(date +%s)"
TotalElapsedsec="$(expr $TotalEndTime - $TotalStartTime)"
TotalElapsedback=$(printf ""%.2dh:%.2dm:%.2ds"\n" $((${TotalElapsedsec}/3600)) $((${TotalElapsedsec}%3600/60)) $((${TotalElapsedsec}%60)))
TotalSize="$(nice -n19 ionice -c3 du -hs ${BackupPath}${DATE} | awk '{print $1}')"
TotalDumpTime=$(printf ""%.2dh:%.2dm:%.2ds"\n" $((${dbtimetotal}/3600)) $((${dbtimetotal}%3600/60)) $((${dbtimetotal}%60)))
TotalTarTime=$(printf ""%.2dh:%.2dm:%.2ds"\n" $((${tartimetotal}/3600)) $((${tartimetotal}%3600/60)) $((${tartimetotal}%60)))
SourcesizeReport=$(echo "scale=3;${sourcesizetotal}/1024/1024/1024"|bc)
DBsizeReport=$(echo "scale=3;${dbsizetotal}/1024/1024/1024"|bc)

echo "Expired Instances= ${expired}" >> $LOGFILE
echo "" >> $LOGFILE
echo "[ $(date) Report ]: DB dumps total size : ${DBsizeReport} GB" >> $LOGFILE
echo "[ $(date) Report ]: DB dump total time taken : ${TotalDumpTime}" >> $LOGFILE
echo "[ $(date) Report ]: Uncompressed Source's total size : ${SourcesizeReport} GB" >> $LOGFILE
echo "[ $(date) Report ]: DB and source compression total time taken : ${TotalTarTime}" >> $LOGFILE
echo "[ $(date) Report ]: Time to finish ${BACKUP_TYPE} backup for $filecount of $TOTALHOMEDIRS homeDirs was $TotalElapsedback" >> $LOGFILE
echo "" >> $LOGFILE

cd "${BackupPath}"

function uploadfiles (){
        status=0
        count=0
        while [[ $status -ne 1 && $count -lt 3 ]]; do
                swift -v -V 2.0 -A ${Endpoint} -U ${Tenant}:${User} -K ${Password} list --prefix ${i} ${Container} | grep ${i}
                if [[ $? -ne 0 ]]; then
                        if [[ `expr ${MaxSingleFileSize} - $(du -b ${i} | awk '{print $1}')` -lt 0 ]]; then
                                swift -v -V 2.0 -A ${Endpoint} -U ${Tenant}:${User} -K ${Password} upload ${Container} -S ${SegmentSize} ${i}
                        else
                                swift -v -V 2.0 -A ${Endpoint} -U ${Tenant}:${User} -K ${Password} upload ${Container} ${i}
                        fi
                        (( count +=1 ))
                else
                        status=1
                        if [[ -f Failed.Uploads && "$(cat Failed.Uploads | grep ${i})" != "" ]]; then
                                sed -i "/$(echo ${i} | sed 's#\/#\\\/#g')/d" Failed.Uploads
                        fi
                        echo "[ $(date) ]: File ${i} uploaded successfully !"  >> $LOGFILE
			rm ${i}
                fi
        done
       if [[ $status -ne 1 && $count -eq 3 ]]; then
               echo -e "\e[05;31m[ $(date) ]: Failed to upload file ${i} after 3 retries. Adding to Failed.Uploads file\e[00m" >> $LOGFILE
                if [[ $(cat Failed.Uploads | grep ${i}) = "" ]]; then
                        echo "${i}" >> Failed.Uploads
                fi
                shootmail=1
       fi
}

if [[ ! -z ${listSeg} && ! -z ${list} ]]; then
        filecount=$(expr $(echo ${listSeg} | tr ' ' '\n' | awk '!a[$0]++' | wc -l) + $(echo ${list} | tr ' ' '\n' | awk '!a[$0]++' | wc -l))
elif [[ ! -z ${listSeg} ]]; then
        filecount=$(echo ${listSeg} | tr ' ' '\n' | awk '!a[$0]++' | wc -l)
else
        filecount=$(echo ${list} | tr ' ' '\n' | awk '!a[$0]++' | wc -l)
fi

if [[ ${StorageBackend} = "swift" ]]; then
	swift -v -V 2.0 -A ${Endpoint} -U ${Tenant}:${User} -K ${Password} post ${Container}

	if [[ ${listSeg} != "" ]]; then
        	swift -v -V 2.0 -A ${Endpoint} -U ${Tenant}:${User} -K ${Password} upload ${Container} -S ${SegmentSize} ${listSeg}
	fi
	if [[ ${list} != "" ]]; then
        	swift -v -V 2.0 -A ${Endpoint} -U ${Tenant}:${User} -K ${Password} upload ${Container} ${list}
	fi
	stogarefilecount=$(swift -v -V 2.0 -A ${Endpoint} -U ${Tenant}:${User} -K ${Password} list --prefix ${DATE}/${NODE} ${Container} | wc -l)
	if [[ ${filecount} -ne ${stogarefilecount} ]]; then
		echo "[ $(date) ]: Uploaded successfully ${stogarefilecount}/${filecount} files !"  >> $LOGFILE
		echo "[ $(date) ]: Uploading the rest one by one !"  >> $LOGFILE
		if [[ ${listSeg} != "" ]]; then
			for i in $(echo ${listSeg} | tr ' ' '\n' | awk '!a[$0]++'); do
				uploadfiles
			done
		fi
		if [[ ${list} != "" ]]; then
			for i in $(echo ${list} | tr ' ' '\n' | awk '!a[$0]++'); do
				uploadfiles
			done
		fi
	else
		echo "[ $(date) ]: Uploaded successfully all ${stogarefilecount} files !"  >> $LOGFILE
		rm -rf ${BackupPath}${DATE}
	fi

	if [[ -f Failed.Uploads && "$(cat Failed.Uploads)" != "" ]]; then
		echo "[ $(date) ]: Attempting to upload files found in Failed.Uploads !"  >> $LOGFILE
		i=""
		for i in "$(sort Failed.Uploads | tr ' ' '\n' | awk '!a[$0]++')"; do
			if [[ "$(ps aux | grep -v grep | grep ${i})" = "" ]]; then
				uploadfiles
				if [[ $(cat Failed.Uploads | grep $(echo ${i} | awk -F\/ '{print $1}')) = "" ]]; then
					rm -rf "${BackupPath}"$(echo ${i} | awk -F\/ '{print $1}')
				fi
			fi
		done
	fi
	finalstogarefilecount="$(swift -v -V 2.0 -A ${Endpoint} -U ${Tenant}:${User} -K ${Password} list --prefix ${DATE} ${Container} | grep ${NODE} | wc -l)"
elif [[ ${StorageBackend} = "aws" ]]; then
	nice -n19 ionice -c3 /usr/local/bin/aws s3 sync ${DATE} ${BUCKET}${DATE}
	stogarefilecount=$(nice -n19 ionice -c3 /usr/local/bin/aws s3 ls ${BUCKET}${DATE}/${NODE}/ | wc -l)
	if [[ ${filecount} -ne ${stogarefilecount} ]]; then
                echo "[ $(date) ]: Uploaded successfully ${stogarefilecount}/${filecount} files !"  >> $LOGFILE
                echo "[ $(date) ]: Trying to resync !"  >> $LOGFILE
		nice -n19 ionice -c3 /usr/local/bin/aws s3 sync ${DATE} ${BUCKET}${DATE}
		stogarefilecount=$(nice -n19 ionice -c3 /usr/local/bin/aws s3 ls ${BUCKET}${DATE}/${NODE}/ | wc -l)
		if [[ ${filecount} -ne ${stogarefilecount} ]]; then
			echo -e "\e[05;31m[ $(date) ]: Sync failed for a second time. Preserving files on host and will try again tomorrow.\e[00m" >> $LOGFILE
			echo "${DATE}" >> Failed-aws.Uploads
			shootmail=1
		else
			echo "[ $(date) ]: Uploaded successfully all ${stogarefilecount} files !"  >> $LOGFILE
			rm -rf ${BackupPath}${DATE}
		fi
        else
                echo "[ $(date) ]: Uploaded successfully all ${stogarefilecount} files !"  >> $LOGFILE
                rm -rf ${BackupPath}${DATE}
        fi
	if [[ -f Failed-aws.Uploads && "$(cat Failed-aws.Uploads)" != "" ]]; then
                echo "[ $(date) ]: Attempting to upload files found in Failed-aws.Uploads !"  >> $LOGFILE
                i=""
                for i in $(sort Failed-aws.Uploads | tr ' ' '\n' | awk '!a[$0]++'); do
			if [[ ${i} != "${DATE}" ]]; then
				nice -n19 ionice -c3 /usr/local/bin/aws s3 sync ${i} ${BUCKET}${i}
				retrystogarefilecount=$(nice -n19 ionice -c3 /usr/local/bin/aws s3 ls ${BUCKET}${i}/${NODE}/ | wc -l)
				retryfilecount=$(find ${i} -type f | wc -l)
				if [[ ${retryfilecount} -ne ${retrystogarefilecount} ]]; then
					echo -e "\e[05;31m[ $(date) ]: Failed to sync dir ${i} after 2 tries\e[00m" >> $LOGFILE
	        		        if [[ $(cat Failed-aws.Uploads | grep ${i}) = "" ]]; then
        		        	        echo "${i}" >> Failed-aws.Uploads
	                		fi
			                shootmail=1
				else
					echo "[ $(date) ]: Uploaded successfully ${retrystogarefilecount}/${retryfilecount} files of previously failed ${i} !"  >> $LOGFILE
					rm -rf ${BackupPath}${i}
					if [[ -f Failed-aws.Uploads && "$(cat Failed-aws.Uploads | grep ${i})" != "" ]]; then
						sed -i "/${i}/d" Failed-aws.Uploads
					fi
				fi
			fi
		done
	fi
	finalstogarefilecount=$(nice -n19 ionice -c3 /usr/local/bin/aws s3 ls ${BUCKET}${DATE}/${NODE}/ | wc -l)
fi
find ${BackupPath} -depth -empty -type d ! -name "*Trash*" -delete

TotalEndTime2="$(date +%s)"

TotalElapsedsec="$(expr $TotalEndTime2 - $TotalStartTime)"
TotalElapsed=$(printf ""%.2dh:%.2dm:%.2ds"\n" $((${TotalElapsedsec}/3600)) $((${TotalElapsedsec}%3600/60)) $((${TotalElapsedsec}%60)))

TotalElapsedsec2="$(expr $TotalEndTime2 - $TotalEndTime)"
TotalElapsedupload=$(printf ""%.2dh:%.2dm:%.2ds"\n" $((${TotalElapsedsec2}/3600)) $((${TotalElapsedsec2}%3600/60)) $((${TotalElapsedsec2}%60)))


echo "[ $(date) Report ]: Time to finish Uploading $stogarefilecount of $filecount files was $TotalElapsedupload" >> $LOGFILE

if [[ ${shootmail} -ne 0 ]]; then
	subject="${DATE} Ooops ! Review inside."
else
	subject="${DATE} All Cool !"
fi

cat $LOGFILE | grep 'Report' > $REPORTFILE


#finalstogarefilecount="$(swift -v -V 2.0 -A ${Endpoint} -U ${Tenant}:${User} -K ${Password} list --prefix ${DATE} ${Container} | grep ${NODE} | wc -l)"
BOUNDARY=$(uuidgen)
MAIL_CMD="$SENDMAIL_BIN -f $FROM_MAIL_ADDRESS -F \"$FROM_MAIL_DISLAY\" $RECIPIENT_ADDRESSES"
(echo "Subject: ${subject}";
echo -e "MIME-Version: 1.0\nContent-Type: multipart/mixed; boundary=\"$BOUNDARY\"\n--$BOUNDARY\n" ;
#echo -e "MIME-Version: 1.0\nContent-Type: text/html;\n" &&
#echo '<pre>' &&
#echo "Instance Count = ${TOTALHOMEDIRS}" &&
echo "Instance Count - (active/expired)= ${TOTALHOMEDIRS} - ($(expr ${TOTALHOMEDIRS} - ${expired})/${expired})" &&
echo "" &&
echo "Uploaded File Count (uploaded/local)= ${finalstogarefilecount}/${filecount}" &&
echo "Failed to Upload= $(expr ${filecount} - ${finalstogarefilecount})" &&
echo "Time taken for upload : ${TotalElapsedupload} " &&
echo "Total Compressed Size= ${TotalSize}" &&
echo "" &&
echo "Failed to backup= ${failedinstances}" &&
#echo "Suspended= ${expired}" &&
echo "Excluded= ${excluded}" &&
echo "Undefined= ${undefined}" &&
echo "" &&
echo "Time Running : $TotalElapsed  " &&
echo "Total time taken for dumping databases : ${TotalDumpTime} " &&
echo "Total size of databases : ${DBsizeReport} GB " &&
echo "Total time taken for compression : ${TotalTarTime} " &&
echo "Total size of uncompressed source files : ${SourcesizeReport} GB " &&
#echo "Time taken for upload : ${TotalElapsedupload} " &&
#echo "Total Compressed Size= ${TotalSize}" &&
echo "" &&
#echo "Nice level : 19 " && #\"this means that is the least favorable to consume CPU (values:-20/20 - defaults to 0)\"  " &&
#echo "If you have two processes running infinite loops on a single core system, no other application of the same priority will ever go over 33%." &&
#echo "" &&
#echo "IOnice scheduling class : 3 " && # \"this means that disk usage for the process will be allowed only when disk is idle\"" &&
#echo "Gzip compression level : 1 " && # \"this is the least possible compression level trading file size for speed and cpu cycles\" " &&
echo "" &&
echo "Size incidents :" &&
echo "" &&
sort $LOGFILE | grep '05;31m' | grep -w "Source size" | awk -F31m '{print $NF}' | awk -F'\\[0' '{print $1}' ;
echo "" &&
echo "Moved to Trash :" &&
echo "" &&
sort $LOGFILE | grep '05;31m' | grep -w 'WARNING:' | awk -F31m '{print $NF}' | awk -F'\\[0' '{print $1}' ;
echo "" &&
echo "Errors :" &&
echo "" &&
sort $LOGFILE | grep '05;31m' | grep -v 'WARNING:' | grep -v "undeployed" | grep -v "Source size" | awk -F31m '{print $NF}' | awk -F'\\[0' '{print $1}' ;
echo "" &&
echo "SQL stderr :" &&
echo "" &&
sort $LOGFILE | grep 'mysqldump: Got error:' ;
echo "" &&
echo "Undefined :" &&
echo "" &&
sort $LOGFILE | grep '05;31m' | grep -w "undeployed" | awk -F31m '{print $NF}' | awk -F'\\[0' '{print $1}' ;
echo "" &&
#echo '</pre>') | eval $MAIL_CMD
#uuencode /root/backup-debug /root/backup-debug.txt;
echo "--$BOUNDARY";
echo "Content-Type: text/plain";
echo "Content-Transfer-Encoding: uuencode";
echo 'Content-Disposition: attachment; filename="'$(basename $REPORTFILE)'"';
echo "";
uuencode $REPORTFILE $(basename $REPORTFILE);
echo "--$BOUNDARY--" ) | eval $MAIL_CMD

#rm ${tmpfile}
