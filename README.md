# csv2mysql

Script to take a CSV file and generate MySQL SQL commands to create it as
a table.

The csv filename becomes the table name.

No MySQL connection is used; just the SQL is output. Column types are auto
detected.

Nb. the entire CSV file is loaded into memory.


To build:

Install box:

```
curl -LSs https://box-project.github.io/box2/installer.php | php
```

Composer install.

php -dphar.readonly=0 `which box` build
