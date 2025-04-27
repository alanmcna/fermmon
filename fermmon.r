#!/usr/bin/Rscript

# Note - doesn't filter by version as yet - TODO

# Install package
library(zoo)
library(scales)

## CO@ and tVOC PLOT

png(filename="fermmon_co2.png", width = 1024, height = 768, unit = "px")

# 2021-01-01 00:00:06
# 2025-02-28 23:55:02
setAs("character","myDate", function(from) as.POSIXlt(from, format="%Y-%m-%d %H:%M:%S") )
rawdata <- read.csv("fermmon.csv", col.names=c('Date.Time','CO2','tVOC', 'Temp', 'V', 'rTemp', 'rHumi', 'Relay'), colClasses=c('myDate','double','double','double','character','double','double','character'), header=TRUE)
rawdata$Relay <- as.numeric(rawdata$Relay)
summary(rawdata)

# filter out any duff readings
co2data <- subset(rawdata, CO2<=25000 & tVOC<=25000)
attach(co2data)

## add extra space to right margin of plot within frame
par(mar=c(5, 5, 5, 5) + 0.1)

# axis top end range
#max_y = max(max(CO2,na.rm=TRUE), max(tVOC,na.rm=TRUE))
max_y = 25000

plot(Date.Time,tVOC,pch=1,main="CO2 and tVOC over Time",col=alpha("darkorange",0.25),xlab="Date/Time",ylab="",ylim=c(0,max_y), axes="FALSE")
r <- as.POSIXct(round(range(Date.Time), "hours"))
axis.POSIXct(1, at=seq(r[1], r[2], by = "24 hours"), format = "%d")
grid(nx=NA,ny=NULL)

axis(2, ylim=c(0,max_y), col="darkorange",col.axis="darkorange",las=1)
mtext("tVOC (ppb)",side=2,col="darkorange",line=-1.5)
box()

par(new=TRUE)
plot(Date.Time,CO2,pch=20,col=alpha("steelblue",0.25),xlab="",ylab="",ylim=c(0,max_y),axes=FALSE)
axis(4, ylim=c(0,max_y), col="steelblue",col.axis="steelblue",las=1)
mtext("CO2 (ppm)",side=4,col="steelblue",line=-1.5)
detach(co2data)

## TEMPERATURE PLOT
tempdata <- subset(rawdata, Temp>0)
attach(tempdata)

png(filename="fermmon_temp.png", width = 1024, height = 768, unit = "px")

## add extra space to right margin of plot within frame
par(mar=c(5, 5, 5, 5) + 0.1)

plot(Date.Time,rHumi,pch=1,main="Temperature (internal + external) and Humidity over Time",col=alpha("skyblue",0.05),xlab="Date/Time",ylab="",ylim=c(0,100), axes="FALSE")
grid(nx=NA,ny=NULL)
r <- as.POSIXct(round(range(Date.Time), "hours"))
axis.POSIXct(1, at=seq(r[1], r[2], by = "24 hours"), format = "%d")

axis(2, ylim=c(0,100), col="skyblue",col.axis="skyblue",las=1)
mtext("% Humidity",side=2,col="skyblue",line=-1.5)
box()

par(new=TRUE)
## RELAY STATUS PLOT - TRY adding to temp graph
plot(Date.Time,Relay,pch=20,type="h",col=alpha("olivedrab",0.05),xlab="",ylab="",ylim=c(0,1), axes="FALSE")
grid(nx=NA,ny=NULL)

par(new=TRUE)
plot(Date.Time,rHumi,pch=1,col=alpha("skyblue",0.05),xlab="",ylab="",ylim=c(0,100), axes="FALSE")

par(new=TRUE)
plot(Date.Time,rTemp,pch=20,col=alpha("orangered4",0.05),xlab="",ylab="",ylim=c(0,50),axes=FALSE)
axis(4, ylim=c(0,50), col="orangered4",col.axis="orangered4",las=1)
mtext("Degrees Celcius",side=4,col="orangered4",line=-1.5)

par(new=TRUE)
plot(Date.Time,Temp,pch=20,col=alpha("orangered",0.05),xlab="",ylab="",ylim=c(0,50),axes=FALSE)
detach(tempdata)

