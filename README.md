DDNSadmin
=========

This is a web interface for DNS zone management using TSIG keys (RFC2845). To 
use it you must have a valid TSIG key and configure your DNS zone master 
name server to allow AXFR and DDNS requests signed by your key.

DNS management interface is split in two parts - frontend written in JavaScript 
and backend written in PHP.

Backend is completely stateless and is used only to convert HTTP requests to DNS
queries. Single backend can be safely used by multiple users managing different 
DNS zones.

To try it out, drop *index.html*, *ddnsadmin.js*, *dnsproxy.php* and *Net.phar* 
to your PHP enabled web server and navigate your browser to *index.html*.

To try it out on local machine without a full blown web server you can use PHP 
built-in web server. Start it from this project directory, with:

	php -S 127.0.0.1:8080

And point your browser to http://127.0.0.1/.

Frontend settings
-----------------

Backend does not require any initial setup and can be used as it is. On the 
frontend user have following settings:

* **DNS zone** - Domain name of zone that is being managed, example: 
*example.net*
* **Key name** - Name of key that is used to sign DNS requests, must be the 
identical to the key name configured on a DNS server, example: 
*key.example.net.*
* **Key type** - Algorithm that is used to generate signature, must be the same 
as configured on a DNS server, example: *sha512*
* **Key** - Secret key used to sign requests, must be base64 encoded, example: 
*UNhY4JhezH9gQYqvDMWrWH9CwlcKiECVqejMrND2VFw=*

Advanced settings:

* **DNS Server** - IP address of zone master name server. DNS requests are 
being sent to this address. This field is filled usually automatically after 
**DNS zone** is entered. It can be entered manually if system fails to detect 
it automatically.
* **Proxy URL** - Backend URL (relative or absolute). Default is search for 
backend on the same web server, same directory. It should be changed if backend 
and frontend are on different web servers.
* **Filter RRs** - List of resource record types (comma separated) to filter 
out before displaying zone records.

System architecture
-------------------

Frontend files:

* index.html
* ddnsadmin.js

Backend files:

* dnsproxy.php
* Net.phar (file) or Net (directory) for Net\_DNS2 library

	+--------------+         +--------------+      +------------+
	|              | HTTP/S  |              | DNS  |            |
	| Web browser  |-------->| PHP backend  |----->| Master     |
	|              |         |              |      | Nameserver |
	| index.html   |<--------| dnsproxy.php |<-----|            |
	| ddnsadmin.js |    ^    | Net.phar     |   ^  |            |
	+--------------+    |    +--------------+   |  +------------+
	                    |                       |
	   JSON request over HTTP (key is send      |
	   in plaintext here, except for HTTPS)     |
	                                            |
	                           Signed DNS request (AXFR or DNS update)
	                       (key is not sent here, only request signature)

In each request frontend passes your zone key to the backend. It is important 
to use HTTPS or start backend on your local machine using PHP built-in web 
server to avoid eavesdropping.

Net\_DNS2
---------

Backend uses [Net\_DNS2 library](http://pear.php.net/package/Net\_DNS2) for DNS 
packet crafting. This repository includes Net.phar which is an archive of 
Net\_DNS2 library files.

Backed checks for *Net.phar* archive or *Net* directory for library sources.

If you do not trust bundled *Net.phar* archive you can easily download library 
code from upstream and use sources directly or pack your own *Net.phar* archive.

There is Makefile with library download and packing code. To delete provided 
*Net.phar* archive and download library sources following commands:

	make clean      // Remove *Net.phar* and library sources
	make Net        // Download and extract library sources

To build your own *Net.phar* use commands:

	make            // Create *Net.phar*
	make distclean  // Delete library sources

DNS server configuration example (Bind 9)
-----------------------------------------

Generate and base64 encoded new (256 bit) key:

	$ dd if=/dev/urandom bs=32 count=1 2>/dev/null | base64
	UNhY4JhezH9gQYqvDMWrWH9CwlcKiECVqejMrND2VFw=

Add generated key to bind configuration file:

	key my-key.example.net. {
		algorithm hmac-sha512;
		secret "UNhY4JhezH9gQYqvDMWrWH9CwlcKiECVqejMrND2VFw=";
	};
	zone "example.net" {
		type master;
		file "/etc/bind/db.example.net";
		allow-transfer { key my-key.example.net.; };
		allow-update { key my-key.example.net.; };
	};

To test your name server configuration you can perform AXFR queries using 
**dig** tool and DDNS updates using **nsupdate** tool. First create key file 
using same syntax as in bind configuration.

**example.key**:

	key my-key.example.net. {
		algorithm hmac-sha512;
		secret "UNhY4JhezH9gQYqvDMWrWH9CwlcKiECVqejMrND2VFw=";
	};

Perform AXFR:

	dig -k path/to/example.key example.net @127.0.0.1

Replace *127.0.0.1* with your name server IP address.

Perform DDNS update:

	nsupdate -k path/to/example.key
	> server 127.0.0.1
	> zone example.net
	> update add ddnstest.fln.lt  300 IN A 192.168.0.1
	> send
	> quit

If **nsupdate** do not print any error messages it means DDNS update was 
performed successfully.

