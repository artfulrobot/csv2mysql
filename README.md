# csv2mysql

Script to take a CSV file and generate MySQL SQL commands to create it as
a table.

## Install

You can download the whole repo and run as php (`php csv2mysql.php ...`)
or just download the binary from `bin/csv2mysql`, make it executable and
run that.


## Usage

This from `csv2mysql convert --help`

```
Description:
  Convert CSV file to MySQL SQL

Usage:
  convert [options]

Options:
  -i, --input=INPUT                            input CSV filename
  -d, --drop                                   Include DROP TABLE? (default no, and use CREATE TABLE IF NOT EXISTS)
  -s, --schema=SCHEMA                          Just the schema, no INSERTs
  -b, --csv-read-buffer=CSV-READ-BUFFER        Buffer size in kB. Each line of CSV must be shorter than this. Default 10 [default: 10]
  -m, --max-command-length=MAX-COMMAND-LENGTH  Max length of INSERT SQL command in kB. Default 10. [default: 10]
  -h, --help                                   Display help for the given command. When no command is given display help for the list command
  -q, --quiet                                  Do not output any message
  -V, --version                                Display this application version
      --ansi                                   Force ANSI output
      --no-ansi                                Disable ANSI output
  -n, --no-interaction                         Do not ask any interactive question
  -v|vv|vvv, --verbose                         Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

```

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
