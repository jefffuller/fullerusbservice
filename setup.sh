#!/bin/sh

# Modified WatchDog script based on http://rpf.io/usbzw
WDURL=https://pastebin.com/raw/nnXssLju
SYSDURL=https://pastebin.com/raw/LXbezMDJ
OPTDIR=/opt/fullerusbservice 


if [ ! -f ./.init ]; then
	echo 'Removing useless apps'
	sudo apt-get remove --purge libreoffice* -y
	sudo apt-get purge wolfram-engine -y
	sudo apt-get clean
	sudo apt-get autoremove -y
	
	echo 'Installing required apps'
	sudo apt-get update
	sudo apt-get install git samba winbind dos2unix -y
	sudo apt install python3-pip -y
	sudo pip3 install python3-utils
	sudo pip3 install watchdog
	sudo apt install apache2 -y
	sudo apt install php libapache2-mod-php -y


	echo 'Getting Systemd script'
	wget $SYSDURL -O /etc/systemd/system/fullerusbservice.service
	chmod +x /etc/systemd/system/fullerusbservice.service
	dos2unix /etc/systemd/system/fullerusbservice.service

	echo 'Building Crontab for service.py'
	crontab -l > /tmp/mycron


	echo 'Using git to get the latest build'
	cd /opt
	if cd /opt/fullerusbservice; then git pull; else cd ../ & git clone https://2ad08a82439ca81eeb2acaaa6383bdad4349ca25@github.com/jefffuller/fullerusbservice.git && cd /opt/fullerusbservice; fi
	 #git clone https://2ad08a82439ca81eeb2acaaa6383bdad4349ca25@github.com/jefffuller/fullerusbservice.git
	chmod -R 775 $OPTDIR/
	chown -R pi: $OPTDIR/
	chmod +x $OPTDIR/service.py


	echo 'Getting Watchdog script'
	#wget $WDURL -O /usr/local/share/usb_share.py
	cd $OPTDIR/
	mv usb_share.py /usr/local/share/usb_share.py
	chmod +x /usr/local/share/usb_share.py
	dos2unix /usr/local/share/usb_share.py
	echo '@reboot sudo python /opt/fullerusbservice/service.py' >> /tmp/mycron
 	crontab /tmp/mycron
 	rm /tmp/mycron


	echo 'Building web interface'
	cp $OPTDIR/var/www/html/*  /var/www/html/
	rm -rf $OPTDIR/var

	rm /var/www/html/index.html

	chmod -R 755 /var/www/html/
	chown -R www-data: /var/www/html/
	dos2unix /var/www/html/settings.push
	cp /usr/lib/python3.7/configparser.py /usr/lib/python3.7/ConfigParser.py

	#python3 $OPTDIR/service.py init

	sudo systemctl daemon-reload
	sudo systemctl enable fullerusbservice.service
	#sudo systemctl start fullerusbservice.service

	HOST=$(cat /proc/sys/kernel/hostname)
	echo ''
	echo '====================='
	echo '  All Done Captian   '
	echo '====================='
	echo 'Navigate to http://'$HOST'/ in your internet browser and finish setting the device up.'
	echo 'Remember that once you make changes and reboot, you will have to use your new hostname instead.'
	echo 'Please reboot, then instead of using http://'$HOST'/ you will now use http://FullerUSBMaster/'

fi
