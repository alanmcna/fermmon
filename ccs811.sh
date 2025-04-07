#/bin/bash

journalctl -u ccs811.service -g "'R'" -o cat | awk '{ print $1" "substr($2,0,length($2)-1)","$4$6 }' | sed -E "s/[\']//g" | sed -r "s/,$//g" | grep -v 65535 > ccs811.csv
#Rscript ccs811.r
