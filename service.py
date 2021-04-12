import ConfigParser
import os, sys
import socket
import time

configPush = ConfigParser.ConfigParser()
configConf = ConfigParser.ConfigParser()
settingsPush = '/var/www/html/settings.push' # Settings to Apply
settingsConf = '/opt/fullerusbservice/settings.conf' # Currently Loaded Settings
configPush.read(settingsPush)
configConf.read(settingsConf)




requireReboot = False
firstRun = False
mountPath = '/mnt/usb_share'
role = {'conf': 'unset','push': 'unset','smb': 'unset'}
container = {'path': '/opt/fullerusbservice/usbimage.bin', 'size': '512'}
smbConfig = {
	'confpath': '/etc/samba/smb.conf',
	'name': 'FullerUSB-Master',
	'comment': 'Fuller USB Share',
	'path': mountPath}

# This will add text to EOF if it doesn't contain the string
def appendUniqueStr(path,string):
	if not os.path.isfile(path):
		os.system('touch ' + path)
	with open(path, "r+") as f:
			for line in f:
				if string in line:
					break
			else:
				f.write(string)

def requireReboot():
	if requireReboot != True:
		requireReboot == True

def cancelReboot():
	# This it only her so I can colapse this in Sublime
	requireReboot = False
def getConfRole():
	if not firstRun:
		if configConf.has_option('GENERAL','Role'):
			role['conf'] = str(configConf.get('GENERAL','Role'))
			return role['conf']
		else:
			print('[WARN]  We were unable to retrieve a role from the config. It maybe corrupt.')
			print('        Please check "' + settingsConf + '" for a Role setting under GENERAL')
			return False

def getPushRole():
	if configPush.has_option('GENERAL','Role'):
		role['push'] = str(configPush.get('GENERAL','Role'))
		return role['push']
	else:
		print('[ERROR] We were unable to retrieve a role from the config. It maybe corrupt.')
		print('        Please check "' + settingsPush + '" for a Role setting under GENERAL')
		exit()

def getPushSize():
	if configPush.has_option('GENERAL','Size'):
		container['size'] = str(configPush.get('GENERAL','Size'))
		return container['size']

def getSmbRole():
	if os.path.isfile(smbConfig['confpath']):
		with open(smbConfig['confpath'], 'r') as f:
			if smbConfig['name'] in f.read():
				role['smb'] = 'Master'
	else:
		print('[ERROR] We were unable to find a Samba Config.')
		print('        Perhaps Samba is not installed?')			
	return role['smb']


def checkHostname():
	if socket.gethostname() != configPush['GENERAL']['Hostname']:
		if setHostname() == True:
			requireReboot()
			print('[INFO]  Hostname set to: ' + configPush['GENERAL']['Hostname'])
		else:
			print('[ERROR] We encountered an error changing the Hostname.')

def setHostname(newhostname=configPush['GENERAL']['Hostname']):
    with open('/etc/hosts', 'r') as file:
	    data = file.readlines()
	    data[1] = '127.0.0.1       ' + newhostname
	    with open('temp.txt', 'w') as file:
	        file.writelines( data )
	    os.system('sudo mv temp.txt /etc/hosts')
	    with open('/etc/hostname', 'r') as file:
	        data = file.readlines()
	    data[0] = newhostname
	    with open('temp.txt', 'w') as file:
	        file.writelines( data )
	    os.system('sudo mv temp.txt /etc/hostname')
	    os.system('sudo dos2unix /etc/hostname')
    return True

def setRole():
	if role['push'] != role['conf']:
		if role['push'] == 'Master':
			if setMaster():
				return True
		else:
			if setSlave():
				return True
	return False

def setMaster():
	share = "\n\n[" + smbConfig['name'] + "]\n\
    comment = " + smbConfig['comment'] + "\n\
    path = " + smbConfig['path'] + "\n\
    browseable = yes\n\
    read only = no\n\
    public = yes\n\
    guest account = nobody\n\
    guest ok = yes\n\
    create mask = 777\n"

    # Prevent duplicate entry
	c = ConfigParser.ConfigParser()
	c.read(smbConfig['confpath'])
	if c.has_section(smbConfig['name']):
		return True

    # We copy the config to the temp folder so we can actually write to it, them move it back
	try:
		os.system('sudo cp ' + smbConfig['confpath'] + ' /tmp/smb.conf')
		with open('/tmp/smb.conf', 'a') as file:
			file.write(share)
			file.close()
		os.system('sudo mv /tmp/smb.conf ' + smbConfig['confpath'])
		try:
			os.system("systemctl restart smbd.service")
		except:
			print('[ERROR] We could not restart Samba. A reboot of the device is REQUIRED!')
		return True
	except:
		return False

def setSlave():
	print('Set as Slave')


def buildConfig():
	if not os.path.isfile(settingsConf):
		f = open(settingsConf, "a")
		f.write('')
		f.close()
		print('Created settings file at: ' + settingsConf)
		firstRun = True
		return True

def saveConfig():
	config = ConfigParser.ConfigParser()
	config['GENERAL'] = {
		'Role': role['push']
	}

	with open(settingsConf, 'w') as cf:
		config.write(cf)

def buildImage():
	if not os.path.isfile(container['path']):
		getPushSize()
		os.system('sudo dd bs=1M if=/dev/zero of=' + container['path'] + ' count=' + container['size'])
		os.system('sudo /sbin/mkdosfs ' + container['path'] + ' -F 16 -I')
	return True
	
def checkMountPath():
	if not os.path.exists(smbConfig['path']):
		os.system('sudo mkdir -p ' + smbConfig['path'])
		os.system('sudo chmod -R 775 ' + smbConfig['path'])
		os.system('sudo chown -R nobody: ' + smbConfig['path'])
	if not os.path.ismount(mountPath):
		appendUniqueStr('/etc/fstab', container['path'] + ' ' + mountPath + ' vfat users,umask=000 0 2')
	return True

# This script will setup all the prerequisits for this to work.
def initPI():
	print('[INFO]  Running FirstRun Initialization. This will take a while!')
	time.sleep(3)
	appendUniqueStr('/boot/config.txt', 'dtoverlay=dwc2')
	appendUniqueStr('/etc/modules', 'dwc2')
	
	print('[INFO]  Image build starting.')
	try:
		buildImage()
		print('[INFO]  Image build complete.')
	except:
		print('[ERROR] Image Build Failed!')

	print('[INFO]  Checking Mount Path.')
	try: 
		checkMountPath()
		print('[INFO]  Mount point passed.')
		os.system('sudo mount -a')
	except:
		print('[ERROR] Mount Point Failed')


	print('[INFO]  Setting up USB Settings.')
	os.system('sudo modprobe g_mass_storage file=' + container['path'] + ' nofua=1 luns=1 ro=0 stall=0 removable=1 cdrom=0 idVendor=0x0781 idProduct=0x556e bcdDevice=0x0103 iManufacturer="SanDisk" iProduct="Cruzer Edge" iSerialNumber="990431108215FFF05368"')
	print('[INFO]  USB Settings complete.')

def main():
	if len(sys.argv) >= 2:
		if sys.argv[1] == 'init':
			initPI()
			print('Initialization Complete. Rerun the script without the init flag')

	buildConfig()
	getConfRole()
	getPushRole()
	getSmbRole()

	if setRole():
		print('[INFO]  Role set to: ' + role['push'])

	checkHostname()
	saveConfig()

if __name__ == '__main__':
    main()