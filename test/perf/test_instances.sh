#!/bin/bash


for i in {1..800}
do
	wget -nv http://test_$i -O - >/dev/null
	wget --no-check-certificate -nv https://test_$i -O - >/dev/null
	wget -nv http://test_$i/dolibarrtest/ -O - >/dev/null
done

echo "Finished."