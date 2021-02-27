# FullerUSBService

FullerUSBService is a suite of tools packaged together to provide a wifi enabled USB drive, designed for the embroidery industry.

## How to Deploy

Starting from a fresh install of [Raspbian Lite](https://www.raspberrypi.org/software/operating-systems/#raspberry-pi-os-32-bit) open a SSH connection to the device and login as the default user `pi`. *(default password is raspberry)*

We need to create the setup file manually, since our repo is *private*. If we open it up we will be able to have one command to do this all. Copy the following code into the terminal.

```
touch setup.sh
chmod +x setup.sh
nano setup.sh
```

Once nano opens the file paste in the source from this [link](https://raw.githubusercontent.com/jefffuller/fullerusbservice/master/setup.sh?token=AALFMLUEQTNKKNNFMJ7H3OLAHIWJG). Save the file and **execute it as sudo**.

```
sudo ./setup.sh
```

The installer will take some time. Halfway through it may ask abount windns, just press enter at that point.

If all goes well you should be able to move to configureing your device. 
**Make sure you read the last few lines from the setup script.**

## How to Configure

On initial deployment the hostname will be `raspberrypi`. By using your internet browser and navigating to the hostname *eg: http://raspberrypi/* you will have access to the config editor. Once you make changes and save it, reboot the pi and it will **automatically apply the changes on boot**.