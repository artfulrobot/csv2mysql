#!/bin/bash
php -dphar.readonly=0 `which box` build
mv bin/csv2mysql.phar bin/csv2mysql
