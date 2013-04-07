Testing Instructions
October 10, 2012

------------------------------------------------
1) Setting up the testing environment
   This framework uses the popular PHPUnit testing framework to test PHP based code.
   To install the PHPUnit framework, do the following:

1.1) Install the PHP command line tool:
     $ sudo apt-get install php5-cli

1.2) Install memcached caching server:
     $ sudo apt-get install memcached
     $ sudo /etc/init.d/memcached start

1.3) Install PHP Memcached client:
     $ sudo apt-get install php5-memcached
     $ sudo /etc/init.d/apache2 restart

1.4) Install PHP PEAR (PEAR is a package manager/tool for PHP):
     - Method 1:
     $ sudo apt-get install php-pear
     $ sudo apt-get install php-dev

     - Method 2 (If method 1 did not work):
     Create a temporary directory under your user folder:
     $ mkdir ~/Temp
     $ cd ~/Temp

     Download PEAR and install PEAR:
     $ wget http://pear.php.net/go-pear.phar
     $ php go-pear.phar

     Add PEAR to your user path environment:
     $ nano ~/.profile

     Add the following line to the bottom of the file (leave out the < and > brackets):
     PATH="$PATH:/home/<your-user-name>/pear/bin"

     Save the file (Ctrl+X -> Y -> Enter)

     Enter the following on the command line:
     $ PATH="$PATH:/home/<yourusername>/pear/bin"

     Make sure PEAR is also added to your client PHP ini file:
     $ sudo nano /etc/php5/cli/php.ini

     Search for UNIX: (Press Ctrl+W, Type UNIX:, Press Enter)

     The include_path setting should look like this:
     include_path = ".:/usr/share/php:/home/<your-user-name>/pear/share/pear"

     If you changed the include_path, save the file (Ctrl+X -> Y -> Enter), if not quit nano by pressing Ctrl+X

     Install the PHP dev module:
     $ sudo apt-get install php-dev

1.5) Install PHP APC caching:
     Note: The following will install version 3.1.3p1 of APC which is marked as UNSTABLED. So don't
     use this method of installing apc: $ sudo apt-get install php-apc

     Instead use this method:
     $ sudo apt-get install apache2-threaded-dev
     $ sudo pecl install apc

     $ sudo nano /etc/php5/cli/php.ini

     Add the following line to the bottom of the file:
     apc.enable_cli = On

     Save the file (Ctrl+X -> Y -> Enter)

     $ sudo nano /etc/php5/apache2/php.ini

     Add the following line to the bottom of the file:
     extension=apc.so

     Restart Apache
     $ sudo /etc/init.d/apache2 restart

1.6) Install PHPUnit using PEAR:
     $ pear config-set auto_discover 1
     $ pear install pear.phpunit.de/PHPUnit

     Install xdebug (user by PHPUnit):
     $ sudo pecl install xdebug

     Determine where xdebug was installed (this will take a while):
     $ find / -name 'xdebug.so' 2> /dev/null

     Take note of the directory returned. Now open the xdebug configuration and see if the configuration matches
     with the directory given above:
     $ sudo nano /etc/php5/conf.d/xdebug.ini

     The zend_extension setting should look something like this, if it does not correspond to the directory
     found above, then change it.
     zend_extension=/usr/lib/php5/20090626+lfs/xdebug.so

     If you changed zend_extension, save the file (Ctrl+X -> Y -> Enter), if not quit nano by pressing Ctrl+X

     Restart Apache:
     $ /etc/init.d/apache2 restart

1.7) Make sure all versions are correct:
     $ php -v        (Must be PHP version 5.3.2 or higher)
     $ pear version  (Must be version 1.9.0 or higher)
     $ phpunit --version (Must be version 3.6.12 or higher)

1.8) Create a writeable directory for the test results to be written too:
     $ mkdir /var/www/websentinel/write/tests
     $ sudo chmod 775 -R /var/www/websentinel/write

------------------------------------------------
2) Running tests
     $ cd /var/www/websentinel/tests
     $ phpunit --coverage-html /var/www/websentinel/write/tests UnitTest lib/CacheTest.php

3) Accessing test coverage results:
     Browse to http://localhost/websentinel/tests

