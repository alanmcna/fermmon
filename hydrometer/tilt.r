#!/usr/bin/Rscript

# Note - doesn't filter by version as yet - TODO

# Install package
library(zoo)
library(scales)

## CO@ and tVOC PLOT

png(filename="tilt.png", width = 1024, height = 768, unit = "px")

# 2021-01-01 00:00:06
# 2025-02-28 23:55:02
setAs("character","myDate", function(from) as.POSIXlt(from, format="%Y-%m-%d %H:%M:%S") )
rawdata <- read.csv("tilt.csv", col.names=c('Date.Time','Tilt','Roll', 'Temp', 'Bv'), colClasses=c('myDate','double','double','double','double'), header=FALSE)

attach(rawdata)

## add extra space to right margin of plot within frame
par(mar=c(5, 5, 5, 5) + 0.1)

plot(Date.Time,Tilt,type="b",lwd=5,pch=20,main="Tilt and Roll over Time",col=alpha("darkorange",0.25),xlab="Date/Time",ylab="",ylim=c(-90,90), axes="FALSE")
r <- as.POSIXct(round(range(Date.Time), "hours"))
axis.POSIXct(1, at=seq(r[1], r[2], by = "1 hours"), format = "%H:%M %a %d")
grid(nx=NA,ny=NULL)

axis(2, ylim=c(-90,90), col="darkorange",col.axis="darkorange",las=1)
mtext("Tilt",side=2,col="darkorange",line=-1.5)
box()

par(new=TRUE)
plot(Date.Time,Roll,type="b",lwd=5,pch=20,col=alpha("steelblue",0.25),xlab="",ylab="",ylim=c(-90,90),axes=FALSE)

par(new=TRUE)
plot(Date.Time,Temp,type="b",lwd=5,pch=20,col=alpha("red",0.25),xlab="",ylab="",ylim=c(-90,90),axes=FALSE)

par(new=TRUE)
plot(Date.Time,Bv,type="b",lwd=5,pch=20,col=alpha("black",0.25),xlab="",ylab="",ylim=c(0,6),axes=FALSE)
axis(4, ylim=c(0,6), col="black",col.axis="black",las=1)
mtext("Battery Voltage",side=4,col="black",line=-1.5)

detach(rawdata)
