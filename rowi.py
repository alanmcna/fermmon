import requests
import json
import time


class RowiError(Exception):
    """Raised when the Rowi controller is unreachable or returns a bad response.

    Lets callers (the main loop) skip temp/humidity/relay handling for an
    iteration instead of aborting when rowi.box is offline."""
    pass


class Rowi:
    # Fail fast instead of blocking the main loop on connection retries when
    # rowi.box is offline.
    TIMEOUT = 5

    def __init__(self):
        self.ROWI_API='http://rowi.box:80/'

    def getTemperature(self):
        try:
            r = requests.get(self.ROWI_API + 'getTemperature', timeout=self.TIMEOUT).json()
            return float(int(r['temp'])/100), float(int(r['humi'])/100)
        except (requests.exceptions.RequestException, ValueError, KeyError) as e:
            raise RowiError("getTemperature failed: %s" % e)

    def getRelayStatus(self):
        try:
            r = requests.get(self.ROWI_API + 'getRelayStatus', timeout=self.TIMEOUT).json()
            return r['rels']
        except (requests.exceptions.RequestException, ValueError, KeyError) as e:
            raise RowiError("getRelayStatus failed: %s" % e)

    def setRelayStatus(self, status):
        data = "on" if (status) else "off"
        try:
            r = requests.post(self.ROWI_API + 'setRelayStatus', json={"data": data}, timeout=self.TIMEOUT).json()
            return r['rslt']
        except (requests.exceptions.RequestException, ValueError, KeyError) as e:
            raise RowiError("setRelayStatus failed: %s" % e)

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
        temp, humi = self.getTemperature()
        print("Rowi - current temperature is: " + str(temp))
        print("Rowi - current humidity is: " + str(humi))
