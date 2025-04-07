#/bin/bash

VERSION=`cat version`

echo "date,co2,tvoc,temp,version,rtemp,rhumi,relay" > ccs811.csv
journalctl -u ccs811.service -g "$VERSION" -o cat >> ccs811.csv
Rscript ccs811.r
