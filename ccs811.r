#!/usr/bin/Rscript

# Install package
require(zoo)

## CO@ and tVOC PLOT

png(filename="ccs811_co2.png", width = 1024, height = 768, unit = "px")

# 2021-01-01 00:00:06
# 2025-02-28 23:55:02
setAs("character","myDate", function(from) as.POSIXlt(from, format="%Y-%m-%d %H:%M:%S") )
rawdata <- read.csv("ccs811.csv", col.names=c('Date.Time','CO2','tVOC', 'Temp', 'V', 'rTemp', 'rHumi', 'Relay'), colClasses=c('myDate','double','double','double','character','double','double','character'), header=TRUE)
rawdata$Relay <- as.numeric(rawdata$Relay)
summary(rawdata)

# filter out any duff readings
co2data <- subset(rawdata, CO2<=10000 & tVOC<=10000)
attach(co2data)

## add extra space to right margin of plot within frame
par(mar=c(5, 5, 5, 5) + 0.1)

# axis top end range
#max_y = max(max(CO2,na.rm=TRUE), max(tVOC,na.rm=TRUE))
max_y = 10000

plot(Date.Time,tVOC,pch=1,main="CO2 and tVOC over Time",col="gray",xlab="Date/Time",ylab="",ylim=c(0,max_y), axes="FALSE")
r <- as.POSIXct(round(range(Date.Time), "hours"))
axis.POSIXct(1, at=seq(r[1], r[2], by = "24 hours"), format = "%d")

axis(2, ylim=c(0,max_y), col="gray",col.axis="gray",las=1)
mtext("tVOC (ppb)",side=2,col="gray",line=-1.5)
box()

par(new=TRUE)
plot(Date.Time,CO2,pch=20,col=rgb(0, 0, 0, 0.15),xlab="",ylab="",ylim=c(0,max_y),axes=FALSE)
axis(4, ylim=c(0,max_y), col="black",col.axis="black",las=1)
mtext("CO2 (ppm)",side=4,col="black",line=-1.5)
detach(co2data)

## TEMPERATURE PLOT
tempdata <- subset(rawdata, Temp>0)
attach(tempdata)

png(filename="ccs811_temp.png", width = 1024, height = 768, unit = "px")

## add extra space to right margin of plot within frame
par(mar=c(5, 5, 5, 5) + 0.1)

plot(Date.Time,rHumi,pch=1,main="Temperature (internal + external) and Humidity over Time",col="gray",xlab="Date/Time",ylab="",ylim=c(0,100), axes="FALSE")
r <- as.POSIXct(round(range(Date.Time), "hours"))
axis.POSIXct(1, at=seq(r[1], r[2], by = "24 hours"), format = "%d")

axis(2, ylim=c(0,100), col="gray",col.axis="gray",las=1)
mtext("% Humidity",side=2,col="gray",line=-1.5)
box()

par(new=TRUE)
plot(Date.Time,rTemp,pch=20,col=rgb(0, 0, 0, 0.15),xlab="",ylab="",ylim=c(0,50),axes=FALSE)
axis(4, ylim=c(0,50), col="black",col.axis="black",las=1)
mtext("Degrees Celcius",side=4,col="black",line=-1.5)

par(new=TRUE)
plot(Date.Time,Temp,pch=20,col="red",xlab="",ylab="",ylim=c(0,50),axes=FALSE)
detach(tempdata)

## RELAY STATUS PLOT
attach(rawdata)
png(filename="ccs811_rs.png", width = 1024, height = 768, unit = "px")

## add extra space to right margin of plot within frame
par(mar=c(5, 5, 5, 5) + 0.1)

colors = c("green", "red")
plot(Date.Time,Relay,type="h",main="Relay Status (Heater Belt on/off) over Time",col=colors[Relay+1],xlab="Date/Time",ylab="",ylim=c(0,1), axes="FALSE")
r <- as.POSIXct(round(range(Date.Time), "hours"))
axis.POSIXct(1, at=seq(r[1], r[2], by = "24 hours"), format = "%d")

axis(2, ylim=c(0,1), col="black",col.axis="black",las=1,at=c(0,1))
mtext("On / Off",side=2,col="black",line=-1.5)
box()
detach(rawdata)
