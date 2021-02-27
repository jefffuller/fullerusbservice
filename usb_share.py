#!/usr/bin/python3
import time
import os
from watchdog.observers import Observer
from watchdog.events import *

CMD_MOUNT = 'modprobe g_mass_storage file=/opt/fullerusbservice/usbimage.bin nofua=1 luns=1 ro=0 stall=0 removable=1 cdrom=0 idVendor=0x0781 idProduct=0x556e bcdDevice=0x0103 iManufacturer="SanDisk" iProduct="Cruzer Edge" iSerialNumber="990431108215FFF05368"'
CMD_UNMOUNT = "modprobe -r g_mass_storage"
CMD_SYNC = "sync"

WATCH_PATH = "/mnt/usb_share"
ACT_EVENTS = [DirDeletedEvent, DirMovedEvent, FileDeletedEvent, FileModifiedEvent, FileMovedEvent]
ACT_TIME_OUT = 30

class DirtyHandler(FileSystemEventHandler):
    def __init__(self):
        self.reset()

    def on_any_event(self, event):
        if type(event) in ACT_EVENTS:
            self._dirty = True
            self._dirty_time = time.time()

    @property
    def dirty(self):
        return self._dirty

    @property
    def dirty_time(self):
        return self._dirty_time

    def reset(self):
        self._dirty = False
        self._dirty_time = 0
        self._path = None


os.system(CMD_MOUNT)

evh = DirtyHandler()
observer = Observer()
observer.schedule(evh, path=WATCH_PATH, recursive=True)
observer.start()

try:
    while True:
        while evh.dirty:
            time_out = time.time() - evh.dirty_time

            if time_out >= ACT_TIME_OUT:
                os.system(CMD_UNMOUNT)
                time.sleep(5)
                os.system(CMD_SYNC)
                time.sleep(5)
                os.system(CMD_MOUNT)
                evh.reset()

            time.sleep(1)

        time.sleep(1)

except KeyboardInterrupt:
    observer.stop()

observer.join()
