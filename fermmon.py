import qwiic_ccs811
import time, datetime
import glob, os
import sys
from rowi import Rowi

# Write to CSV - much faster than journalctrl
def writeResults(str):
    filename = 'fermmon.csv'

    # Append-adds at last
    fptr = open(filename, 'a')  # append mode
    print("info: writing results - %s" % (str))
    fptr.write("%s\n" % (str))
    fptr.close()

# Write latest line for HTTP server
def writeLatest(str):
    filename = 'latest.csv'

    # Append-adds at last
    fptr = open(filename, 'w')  # write/clobber mode - we just want one line
    print("info: writing latest - %s" % (str))
    fptr.write("%s\n" % (str))
    fptr.close()

# What brew 'version' are we - keep logs
def getVersion():
    # Append-adds at last
    fptr = open('version', 'r')  # read mode
    content = fptr.read()
    splited_data = content.splitlines()
    # Joining the lines
    version = ''.join(splited_data)
    fptr.close()
    print("info: version is - %s" % (version))
    return version

# function to read ds18b20 probe
def ds18B20(SensorID):
    try:
        f = open( "/sys/bus/w1/devices/" + SensorID + "/w1_slave")
        txt = f.read()
        f.close()
        line = txt.split("\n")[0]
        crc  = line.split("crc=")[1]
        if crc.find("YES")<0:
            return None
    except:
        return None
    line = txt.split("\n")[1]
    txt_temp = line.split(" ")[9]
    return (float(txt_temp[2:])/1000.0)

ccs811Sensor = qwiic_ccs811.QwiicCcs811()

if ccs811Sensor.is_connected() == False:
    print("Error: the qwiic CCS811 device isn't connected to the system - please check your connection", file=sys.stderr)
    sys.exit(0)

ccs811Sensor.begin()

tempSensor = ''

for ts in glob.glob("/sys/bus/w1/devices/28-*"):
    tempSensor = ts.split('/')[5]
    break # just grab the first one

delta = 5
duration = 20
incr = 0

co2 = tvoc = 0

r = Rowi()
version = getVersion()

while True:
    # get (external) temp and humidity from the rowi and set the ccs811 env data
    rtemp, rhumi = r.getTemperature()
    ccs811Sensor.set_environmental_data(rhumi,rtemp)

    if ccs811Sensor.data_available():
        ccs811Sensor.read_algorithm_results()

        co2 += ccs811Sensor.get_co2()
        tvoc += ccs811Sensor.get_tvoc()
        print("debug: co2:%.02f, tvoc:%.02f, incr:%d" % (co2, tvoc, incr));

        incr+=delta
    else:
        print("debug: ccs811 data unavailable (incr:%d, delta:%d)" % (incr, delta));

    if incr >= duration:
        co2=co2/(incr/delta)
        tvoc=tvoc/(incr/delta)

        temp = ds18B20(tempSensor) # internal temperature
        if temp is None:
            temp = 0 # just hack it
            
        if temp > 12 and temp < 20 and r.getRelayStatus() == "0":
            r.setRelayStatus(True)
            print("debug: turning on rowi - too cold")
        elif temp >= 20 and r.getRelayStatus() == "1":
            r.setRelayStatus(False)
            print("debug: turning off rowi - too hot")
        elif temp < 12 and r.getRelayStatus() == "1":
            r.setRelayStatus(False)
            print("debug: turning off rowi - bogus temp reading")

        relay = r.getRelayStatus() 

        results = "%s,%.3f,%.3f,%.3f,%s,%.3f,%.3f,%s" % (datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S"), 
			co2, tvoc, temp, version, rtemp, rhumi, relay)
        writeResults(results)
        writeLatest(results)

	# reset vars
        incr = co2 = tvoc = 0

    print("debug: about to sleep for %ds (incr:%d, duration:%d)" % (delta, incr, duration));
    time.sleep(delta)
