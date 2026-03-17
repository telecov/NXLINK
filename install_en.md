# 📡 NXLink Dashboard Installation

### Web Dashboard for NXDN Reflector

🌐 [Ver documentación en Español](install.md)  
🌐 [View documentation in English](install_en.md)

---

## 🖥️ Requirements

⚠️ **IMPORTANT:**
You must have **DVReflector by NØSTAR** installed and running beforehand.

👉 https://github.com/nostar/DVReflectors

If your reflector is already working, you can skip directly to the dashboard installation.

If you are starting from scratch, you can follow a step-by-step video guide.

---

## ⚠️ Recommendation

If you already have a web dashboard running:

* Make a **full backup**
* Or install NXLink in a different path (example: `/var/www/html/nxdn/`)

This prevents losing your current setup.

---

## 💻 Recommended Hardware

### Minimum requirements

* CPU: Dual Core 1.2 GHz or higher (Intel Atom / Celeron)
* RAM: 1 GB minimum (2 GB recommended)
* Storage: 8 GB (SD card or HDD)
* Network: Ethernet 100 Mbps or WiFi b/g/n
* Operating System:

  * Debian 12 / 13
  * Ubuntu Server
  * Raspberry Pi OS
  * Armbian (Bookworm)

---

## 🧪 Tested Platforms

NXLink has been tested on:

* Raspberry Pi 3 / 4
* Debian 12+
* Raspberry Pi OS
* Ubuntu Server
* Banana Pi / Armbian

---

## 📦 Required Software

* Apache2
* PHP 8.2 or higher
* Git
* cURL

---

## 🛠 Recommended Tools

* **IP Scanner** → identify device IP
* **PuTTY** → SSH access
* **Raspberry Pi Imager** → OS installation

---

# 📡 NXDN Reflector Installation (DVReflector)

## 1️⃣ Update repositories

```bash id="en1"
sudo apt update
```

---

## 2️⃣ Download DVReflector

```bash id="en2"
cd /opt
sudo git clone https://github.com/nostar/DVReflectors.git
sudo chmod -R 777 DVReflectors
```

---

## 3️⃣ Compile

```bash id="en3"
cd /opt/DVReflectors/NXDNReflector
make clean
make -j4
```

---

## 4️⃣ Copy configuration

```bash id="en4"
sudo mkdir -p /etc/NXDNReflector
sudo cp NXDNReflector.ini /etc/NXDNReflector/
```

---

## 5️⃣ Create logs directory

```bash id="en5"
sudo mkdir -p /var/log/nxdnreflector
sudo chmod 777 /var/log/nxdnreflector
```

---

## 6️⃣ Configure reflector

```bash id="en6"
sudo nano /etc/NXDNReflector/NXDNReflector.ini
```

```ini id="en7"
[General]
TG=9999
Daemon=0

[Id Lookup]
Name=nxdn.csv
Time=24

[Log]
DisplayLevel=1
FileLevel=1
FilePath=var/log/nxdnreflector
FileRoot=NXDNReflector
FileRotate=1

[Network]
Port=41400
Debug=0
```

---

## 7️⃣ Create systemd service

⚠️ Replace `User=teleco` with your system user

```bash id="en8"
sudo nano /etc/systemd/system/nxdnreflector.service
```

```ini id="en9"
[Unit]
Description=NXDN Reflector
After=network.target

[Service]
User=teleco
ExecStart=/opt/DVReflectors/NXDNReflector/NXDNReflector /etc/NXDNReflector/NXDNReflector.ini
Restart=always

[Install]
WantedBy=multi-user.target
```

---

## 8️⃣ Sudo permissions (VISUDO)

```bash id="en10"
sudo visudo -f /etc/sudoers.d/nxlink
```

```bash id="en11"
www-data ALL=(ALL) NOPASSWD:/usr/bin/systemctl start nxdnreflector
www-data ALL=(ALL) NOPASSWD:/usr/bin/systemctl stop nxdnreflector
www-data ALL=(ALL) NOPASSWD:/usr/bin/systemctl restart nxdnreflector
www-data ALL=(ALL) NOPASSWD:/usr/bin/systemctl status nxdnreflector
www-data ALL=(ALL) NOPASSWD:/usr/sbin/reboot
www-data ALL=(ALL) NOPASSWD:/usr/bin/nmcli
```

---

## 9️⃣ INI file permissions

```bash id="en12"
sudo chown root:www-data /etc/NXDNReflector/NXDNReflector.ini
sudo chmod 664 /etc/NXDNReflector/NXDNReflector.ini
```

---

## 🔟 Enable service

```bash id="en13"
sudo systemctl daemon-reload
sudo systemctl enable nxdnreflector
sudo systemctl start nxdnreflector
sudo systemctl status nxdnreflector
```

---

# 📦 NXLink Dashboard Installation

## 1️⃣ Install dependencies

```bash id="en14"
sudo apt update
sudo apt install apache2 php libapache2-mod-php php-curl unzip git network-manager -y
sudo systemctl restart apache2
```

---

## 2️⃣ Clone dashboard

```bash id="en15"
cd /var/www/
sudo rm -rf html
sudo git clone https://github.com/telecov/NXLINK.git html
```

---

## 3️⃣ Permissions

```bash id="en16"
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html
```

---

## 4️⃣ Telegram realtime service

```bash id="en17"
sudo nano /etc/systemd/system/nxdn-telegram-realtime.service
```

```ini id="en18"
[Unit]
Description=NXDN Telegram Realtime Notifier
After=network-online.target

[Service]
ExecStart=/usr/bin/php /var/www/html/scripts/nxdn_telegram_realtime.php
Restart=always
User=www-data

[Install]
WantedBy=multi-user.target
```

```bash id="en19"
sudo systemctl daemon-reload
sudo systemctl enable --now nxdn-telegram-realtime
```

---

## 5️⃣ Cron for notifications

```bash id="en20"
sudo crontab -u www-data -e
```

```bash id="en21"
*/10 * * * * /usr/bin/php /var/www/html/NXDN/includes/telegram_notify.php
```

---

## 🌐 Web Access

Open your browser:

```text id="en22"
http://YOUR_SERVER_IP/
```

---

# ⚙️ Initial Configuration

## Web panel

```text id="en23"
http://YOUR_SERVER_IP/personalizar.php
http://YOUR_SERVER_IP/configuracion.php
```

---

## 🔐 Default password

```text id="en24"
nxlink2025
```

⚠️ It is recommended to change it immediately.

---

## ⚙️ Available configuration

### 🛰️ Reflector

* System name
* IP / domain
* Port
* Status and statistics

---

### 💬 Telegram (optional)

1. Create a bot via **@BotFather**
2. Get API token
3. Create a group or channel
4. Add bot as administrator
5. Get group/channel ID

👉 https://api.telegram.org/bot<TOKEN>/getUpdates

---

## ✅ Done

Your NXLink system is now up and running and accessible via web.
