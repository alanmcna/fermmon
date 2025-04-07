import requests
import json
import time

class Rowi:
    def __init__(self):
        self.ROWI_API='http://rowi.box:80/'
        
    def getTemperature(self):
        r = requests.get(self.ROWI_API + 'getTemperature').json()
        return float(int(r['temp'])/100), float(int(r['humi'])/100)

    def getRelayStatus(self):
        r = requests.get(self.ROWI_API + 'getRelayStatus').json()
        return r['rels']

    def setRelayStatus(self, status):
        data = "on" if (status) else "off"
        r = requests.post(self.ROWI_API + 'setRelayStatus', json={"data": data}).json()
        return r['rslt']

    def test(self):
        print("Rowi - current status is: " + self.getRelayStatus())
        print("Sleeping 10s")
        time.sleep(10)
        print("Rowi - turning on relay: " + self.setRelayStatus(True))
        print("Rowi - current status is: " + self.getRelayStatus())
        print("Sleeping 10s")
        time.sleep(10)
        print("Rowi - turning off relay: " + self.setRelayStatus(False))
        print("Rowi - current status is: " + self.getRelayStatus())
        print("Sleeping 10s")
        time.sleep(10)
        temp, humi = self.getRelayTemparture()
        print("Rowi - current temperature is: " + str(temp))
        print("Rowi - current humidity is: " + str(humi))
