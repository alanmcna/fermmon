#!/usr/bin/python3
import glob, os

# function to read ds18b20 probe

def Read_DS18B20(SensorID):
    try:
        fichier = open( "/sys/bus/w1/devices/" + SensorID + "/w1_slave")
        texte = fichier.read()
        fichier.close()
        ligne1 = texte.split("\n")[0]
        crc    = ligne1.split("crc=")[1]
        if crc.find("YES")<0:
            return None
    except:
        return None
    ligne2 = texte.split("\n")[1]
    texte_temp = ligne2.split(" ")[9]
    return (float(texte_temp[2:])/1000.0)


# function to read cpu Temp

def Read_CPU_Temp():
   fichier = open("/sys/class/thermal/thermal_zone0/temp","r")
   texte =  fichier.readline()
   fichier.close()
   return  (float(texte)/1000.0)

sensors = []

for sensor in glob.glob("/sys/bus/w1/devices/28-*"):
    sensors.append(sensor.split('/')[5])

# ok print temperature
#print("CPU: {:0.1f} 'C".format(Read_CPU_Temp()))
for sensor in sensors:
    print(sensor,":",Read_DS18B20(sensor),"'C")
