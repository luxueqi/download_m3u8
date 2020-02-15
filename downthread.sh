#!/bin/bash

threadCount=$1
url=$2
if [[ "$2" =~ ^https?://.+ ]]; then
    for (( i = 0; i < $threadCount; i++ )); do
	php t.php -s ${i} -c ${threadCount} -u ${url} &
	sleep 1
	done
else
	echo '链接错误！'
fi


