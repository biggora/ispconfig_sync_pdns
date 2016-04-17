## ISPConfig to PowerDNS
This script helps you synchronize ISPConfig and the PowerDNS nameserver (with MariaDB backend). 
PowerDNS is a high-performance, authoritative-only nameserver - in the setup described here it will read 
the DNS records from a MariaDB database.

### Installation

#### Ubuntu 14

Install system dependencies
```
  $ sudo apt-get update -q
  $ sudo apt-get install -y git php5 php5-cli php5-mysql python-software-properties
```
Install the MariaDB 10.x
```
  $ sudo echo "deb http://mariadb.kisiek.net//repo/10.0/ubuntu trusty main" > /etc/apt/sources.list.d/mariadb.list
  $ sudo apt-key adv --recv-keys --keyserver hkp://keyserver.ubuntu.com:80 0xcbcb082a1bb943db
  $ sudo apt-get update -q
  $ sudo apt-get install -y mariadb-server
```
Now check that networking is enabled. Run
```
  $ netstat -antp | grep LISTEN
```
to restart MariaDB service
```
  $ sudo service mysql restart
```

Install the PowerDNS
```
  $ sudo apt-get install pdns-server pdns-backend-mysql
```
Clone script
```
  $ cd /opt
  $ git clone https://github.com/biggora/ispconfig_sync_pdns.git
  $ cd ispconfig_sync_pdns
```
Configure MariaDB
```
  $ sudo mysql -e "CREATE USER 'powerdns'@'localhost' IDENTIFIED BY 'PDNSPASSWORD';" -uroot
  $ sudo mysql -e "CREATE DATABASE IF NOT EXISTS powerdns;" -uroot
  $ sudo mysql -e "FLUSH PRIVILEGES;" -uroot
  $ sudo mysql -h localhost -u powerdns --password=PDNSPASSWORD powerdns < /opt/ispconfig_sync_pdns/powerdns.sql
```
Start synchronization
```
  $ php -f resync.php
```

To test synchronization result:
```
  $ dig SOA @src_dns_server_ip your_domain
  $ dig SOA @dst_dns_server_ip your_domain
```