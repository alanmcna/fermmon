#!/usr/bin/Rscript

require(zoo)

png(filename="ccs811.png", width = 1024, height = 768, unit = "px")

# 2021-01-01 00:00:06
# 2025-02-28 23:55:02
setAs("character","myDate", function(from) as.POSIXlt(from, format="%Y-%m-%d %H:%M:%S") )
mydata <- read.csv("ccs811.csv", col.names=c('Date.Time','CO2','tVOC'), colClasses=c('myDate','double','double'), header=FALSE)
# summary(mydata)
attach(mydata)

# lets try to do a rolling average (or CO2)
CO2r <- rollapply(CO2, width=6, FUN=mean, by.column=TRUE,fill=NA,align="right")
tVOCr <- rollapply(tVOC, width=6, FUN=mean, by.column=TRUE,fill=NA,align="right")
#tVOCr_day <- rollapply(tVOC, width=2160, FUN=mean, by.column=TRUE,fill=NA,align="right")

## add extra space to right margin of plot within frame
par(mar=c(5, 5, 5, 5) + 0.1)

# axis top end range
max_y = max(max(CO2r,na.rm=TRUE), max(tVOCr,na.rm=TRUE))

plot(Date.Time,tVOCr,type="l",main="CO2 and tVOC over Time (30s rolling average)",col="gray",xlab="Date/Time",ylab="",ylim=c(0,max_y), axes="FALSE")
r <- as.POSIXct(round(range(Date.Time), "hours"))
axis.POSIXct(1, at=seq(r[1], r[2], by = "1 days"), format = "%d")

axis(2, ylim=c(0,max_y), col="gray",col.axis="gray",las=1)
mtext("tVOC (ppb)",side=2,col="gray",line=-1.5)
box()

par(new=TRUE)

plot(Date.Time,CO2r,pch="15",type="l",col="black",xlab="",ylab="",ylim=c(0,max_y),axes=FALSE)
axis(4, ylim=c(0,max_y), col="black",col.axis="black",las=1)
mtext("CO2 (ppm)",side=4,col="black",line=-1.5)
