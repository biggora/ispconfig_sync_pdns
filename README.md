## ISPConfig to PowerDNS
This script helps you synchronize ISPConfig and the PowerDNS nameserver (with MariaDB backend). 
PowerDNS is a high-performance, authoritative-only nameserver - in the setup described here it will read 
the DNS records from a MariaDB database.

### Installation

#### Ubuntu 14

Install the PowerDNS
```
  $ sudo apt-get install pdns-server pdns-backend-mysql
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
  $ netstat -tap | grep mysql
```
to restart MariaDB service
```
  $ sudo service mysql restart
```
Configure MariaDB
```
  $ sudo mysql -e "CREATE USER 'powerdns'@'localhost' IDENTIFIED BY 'PDNSPASSWORD';" -uroot
  $ sudo mysql -e "CREATE DATABASE IF NOT EXISTS powerdns;" -uroot
  $ sudo mysql -e "FLUSH PRIVILEGES;" -uroot
  $ sudo mysql -h localhost -u powerdns --password=PDNSPASSWORD powerdns < powerdns.sql
```


To Test synchronization result:
```
  $ dig SOA @src_dns_server_ip your_domain
  $ dih SOA @dst_dns_server_ip your_domain
```