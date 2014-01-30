How to run
----------

This project depends on [Composer](http://getcomposer.org/), and dependencies must be fetched:

```bash
# Clone the repository
git clone https://github.com/schwing/Rackspace-LBaaSMonitor

# Enter the directory
cd Rackspace-LBaaSMonitor

# Install Composer to the local directory
curl -sS https://getcomposer.org/installer | php

# Fetch all dependencies into the local directory
php composer.phar install
```

All configuration options are located at the top of lbaasmonitor.php. Adjust accordingly.

Running the script requires no flags:
```bash
php lbaasmonitor.php
```
