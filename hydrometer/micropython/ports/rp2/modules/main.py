import machine
import utime
import picosleep


def get_vsys():
    conversion_factor = 3 * 3.3 / 65535

    try:
        # Make sure pin 25 is high.
        machine.Pin(25, mode=machine.Pin.OUT, pull=machine.Pin.PULL_DOWN).high()
        
        # Reconfigure pin 29 as an input.
        machine.Pin(29, machine.Pin.IN)
        
        vsys = machine.ADC(29)
        return vsys.read_u16() * conversion_factor

    finally:
        # Restore the pin state
        machine.Pin(29, machine.Pin.ALT, pull=machine.Pin.PULL_DOWN, alt=7)


def connect_to_wifi():
    import network
    import config

    try:
        wlan = network.WLAN(network.STA_IF)
        wlan.active(True)
        if not wlan.isconnected():
            wlan.connect(config.wifi_ssid,config.wifi_password)

            # don't try forever (we might be underwater ;)
            attempts = 5
            check = 0
            while not wlan.isconnected() and check < attempts:
                utime.sleep_ms(2000)
                check=check+1
        return wlan.isconnected()        
    except Exception as err:
        print(f"connect_to_wifi: unexpected {err=}, {type(err)=}")
        return False


def disable_wifi():
    import network

    try:
        wlan = network.WLAN(network.STA_IF)
        if wlan.isconnected(): #  maybe not necessary
            wlan.disconnect()

            attempts = 5
            check = 0
            while wlan.isconnected() and check < attempts:
                utime.sleep_ms(2000)
                check=check+1

        # do it anyway
        wlan.active(False)
        return not wlan.active()

    except Exception as err:
        print(f"disable_wifi: unexpected {err=}, {type(err)=}")
        return False

def get_mpu6050_data():
    from imu import MPU6050
    from math import atan2, sqrt, pi

    tilt = roll = temp = float("nan")

    try:
        # TODO consider re-testing with freq 200000 (400k is considered a max)
        i2c = machine.I2C(0, sda=machine.Pin(0), scl=machine.Pin(1), freq=400000)
        imu = MPU6050(i2c)

        # -1 * as we have the chip upside down :)
        tilt=-1 * atan2(imu.accel.x,sqrt((imu.accel.y*imu.accel.y)+(imu.accel.z*imu.accel.z))) * 180/pi 
        roll=atan2(imu.accel.y,sqrt((imu.accel.x*imu.accel.x)+(imu.accel.z*imu.accel.z))) * 180/pi
        temp=imu.temperature
    except Exception as err:
        print(f"get_mpu6050_data: unexpected {err=}, {type(err)=}")
        pass

    print(f"get_mpu6050_data: {tilt=}, {roll=}, {temp=}")
    return tilt, roll, temp

def post_sensor_data():
    import urequests
    import json
    import config

    time_to_sleep = config.time_to_sleep

    # Let's read battery voltage before we do anything.
    # TODO: do we need to handle exception here?
    bv = get_vsys()
    print(f"post_sensor_data: vsys {bv=}")

    LED = machine.Pin("LED", machine.Pin.OUT)
    LED.on()
    print("post_sensor_data: led on")

    try:
        if connect_to_wifi():
            print("post_sensor_data: connected to wifi")

            # TODO: maybe get 3 readings over X minutes and report the average?
            tilt, roll, temp = get_mpu6050_data()
            json_data =  { 'tilt': tilt, 'roll': roll, 'temp': temp, 'bv': bv }
        
            r = urequests.post(config.http_api, data=json.dumps(json_data), timeout=15)

            if r.status_code == 200:
                print(f"post_sensor_data: {r.status_code=}, {r.text=}")
                r_json = json.loads(r.text)
                if 'OK' in r_json:
                    # Let's be sensible - at least 1 minute but less than or equal to 1 day
                    if r_json['OK'] >= 60 and r_json['OK'] <= 86400: 
                        time_to_sleep = r_json['OK']
                        print(f"post_sensor_data: updating {time_to_sleep=} (seconds) from POST response")
                    # TODO - update POST url based on response JSON also
            else:
                print(f"post_sensor_data: {r.status_code=}")

        if disable_wifi():
            print("post_sensor_data: disconnected from wifi")
        else:
            print("post_sensor_data: unable to disconnect to wifi")
            
    except Exception as err:
        print(f"post_sensor_data: unexpected {err=}, {type(err)=}")
        pass

    LED.off()
    print("post_sensor_data: led off")

    return time_to_sleep

### MAIN ###

# Allow some seconds for USB serial just in case it's connected for debugging
utime.sleep_ms(10000)

while True:
    time_to_sleep = post_sensor_data()

    try:
        # put the device to sleep for 3600 seconds (1 hr)
        print(f"main: about to picosleep for {time_to_sleep=} seconds")
        utime.sleep_ms(2000) # give it time to print the logging

        picosleep.seconds(time_to_sleep)
    except Exception as err:
        print(f"main: unexpected {err=}, {type(err)=}")

        utime.sleep_ms(time_to_sleep * 1000)
