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

- The csv filename becomes the table name.

- No MySQL connection is used; just the SQL is output. Column types are
  auto detected.

- Nb. the entire CSV file is loaded into memory.

## Example use

Using the sample CSV file in `testCsv/a.csv`:

```sh
% bin/csv2mysql convert -i testCsv/a.csv
CREATE TABLE IF NOT EXISTS `a` (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `Name` VARCHAR(28) NOT NULL DEFAULT "",
  `houses` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `dob` DATE,
  `height` DECIMAL(12,4) NOT NULL DEFAULT 0,
  `Bank_Balance` INT(10) SIGNED NOT NULL DEFAULT 0
);

INSERT INTO `a` (`Name`, `houses`, `dob`, `height`, `Bank_Balance`) VALUES 
("Wilma",0,"1995-01-02",23.4,-200),("Sir Montague the Oppressor",200,"1995-01-02",23.4,-200);
```

- An ID column was added as a primary key, as there was no ID column in
  the CSV. If there had been a column called ID, it would be the primary
  key.
- `Name` is 28 characters long. This is 10% more than the max characters
  found in the CSV file.
- `houses` was identified as a TINYINT and because all the values were +ve
  it's UNSIGNED
- `dob` was identified as a date
- `height` as a non-integer, stored as a DECIMAL
- `Bank_Balance` was identified as a signed integer.


## Developers: to build:

Install box:

```
curl -LSs https://box-project.github.io/box2/installer.php | php
```

Run `composer install` and then run `bin/build.sh`

