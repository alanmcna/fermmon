import qwiic_ccs811
import time, datetime
import sys

mySensor = qwiic_ccs811.QwiicCcs811()

if mySensor.is_connected() == False:
    print("The Qwiic CCS811 device isn't connected to the system. Please check your connection", file=sys.stderr)
    sys.exit(0)

mySensor.begin()

time.sleep(5)

while True:
    if mySensor.data_available():
        mySensor.read_algorithm_results()
        print("'%s': 'CO2': '%.3f', 'tVOC': '%.3f', 'T': %.3f, 'R': %.3f" 
		% (datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S"), 
		mySensor.get_co2(), 
		mySensor.get_tvoc(),
		mySensor.get_temperature(),
		mySensor.get_resistance()))
    time.sleep(5)
