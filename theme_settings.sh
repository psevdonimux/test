#!/bin/bash
pcmanfm-qt --set-wallpaper /usr/share/lxqt/wallpapers/triangles.png && cp -r /cdrom/settings/lxqt.conf ~/.config/lxqt/ && pkill -f "lxqt-panel" && lxqt-panel
