<?php

namespace App\Command;

use ArtfulRobot\CsvParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class ConvertCommand extends Command
{
  protected function configure() {
    $this->setName('convert')
         ->setDescription('Convert CSV file to MySQL SQL')
         ->setHelp('')
         ->addOption('input', 'i', InputOption::VALUE_REQUIRED, 'input CSV filename')
         // ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'output SQL filename')
         ->addOption('drop', 'd', InputOption::VALUE_NONE, 'Include DROP TABLE? (default no, and use CREATE TABLE IF NOT EXISTS)')
         ->addOption('schema', 's', InputOption::VALUE_NONE, 'Just the schema, no INSERTs')
         ->addOption('csv-read-buffer', 'b', InputOption::VALUE_REQUIRED, 'Buffer size in kB. Each line of CSV must be shorter than this. Default 10', 10)
         ->addOption('max-command-length', 'm', InputOption::VALUE_REQUIRED, 'Max length of INSERT SQL command in kB. Default 10.', 10)
       ;
  }

  protected function execute(InputInterface $input, OutputInterface $output) {

    $csvFilename = $input->getOption('input');

    if (!file_exists($csvFilename)) {
       $output->writeln("<error>Error: input '$csvFilename' not found</error>");
       return 1;
    }
    if (!is_readable($csvFilename)) {
       $output->writeln("<error>-- Error: input '$csvFilename' not readable</error>");
       return 1;
    }
    try {
      $parser = CsvParser::createFromFile($csvFilename, $input->getOption('csv-read-buffer') * 1024);
    }
    catch (\Exception $e) {
      $output->writeln("<error>-- Error: input '$csvFilename': " . $e->getMessage() . "</error>");
      return 1;
    }
    if ($parser->count() == 0) {
      $output->writeln("<comment>-- Ignored file with no row data: $csvFilename</comment>");
      return 1;
    }

    $isBigFile = ($parser->count() > 100);
    ProgressBar::setFormatDefinition('custom', '  <info>%message%</info> %percent% %bar% Est %remaining%');

    $tableName = preg_replace( '/[^a-zA-Z0-9_]+/', '_',
      preg_replace('@^(?:.*/)?([^/]+)\.csv$@', '$1', $csvFilename));

    // Determine column types.
    $schemaCols = $cols = [];
    $foundID = FALSE;
    foreach ($parser->getHeaders() as $colname) {
      $cols[$colname] = [
        'name' => trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $colname)),
        // A column type is true until a non-valid value is encountered.
        'date' => TRUE,
        'datetime' => TRUE,
        'unsigned_int' => TRUE,
        'signed_int' => TRUE,
        'float' => TRUE,

        // Nulls are allowed as soon as an empty cell is found.
        'empty' => FALSE,
        'maxlength' => 0,
        'maxmblength' => 0,
        'maxint' => 0,
      ];
    }

    // 2x because we scan and then we generate the INSERTS
    if ($isBigFile) {
      $progressBar = new ProgressBar($output, count($cols));
      $progressBar->setFormat('custom');
      $progressBar->setMessage("Scanning schema.");
      $progressBar->start();
    }

    foreach (array_keys($cols) as $colname) {
      $t = &$cols[$colname];

      foreach ($parser as $row) {
        $val = $row->$colname;
        // Rule out certain types if the value is not valid for them.
        $t['unsigned_int'] &= preg_match('/^\d+$/', (string) $val);
        $t['signed_int'] &= preg_match('/^-\d+$/', $val);
        $t['float'] &= preg_match('/^-?\d\d*(\.\d+)$/', $val);
        $t['date'] &= preg_match('/^\d\d\d\d-\d\d-\d\d$/', $val);
        $t['datetime'] &= preg_match('/^\d\d\d\d-\d\d-\d\d[T ]\d\d:\d\d:\d\d$/', $val);

        // Are there empty values? (Note here, empty means zero-length string.)
        $t['empty'] |= $val === '';

        // @todo timezones
        $t['maxmblength'] = max(mb_strlen($val), $t['maxlength']);
        $t['maxlength'] = max(strlen($val), $t['maxlength']);
        $t['maxint'] = max(abs((int)($val)), $t['maxint']);
      }
      $t['unique'] = [];
      foreach (['date', 'datetime', 'unsigned_int', 'signed_int', 'float'] as $_) {
        if ($t[$_]) {
          $t['unique'][] = $_;
        }
      }
      $c = count($t['unique']);

      // Default data type
      $type = 'text';
      if ($c === 1) {
        // only one match, easy.
        $type = $t['unique'][0];
      }
      elseif ($c > 1) {
        throw new \RuntimeException("Row " . $parser->key() . " col '" . $colname . "' could be "
          . implode(' or ', $t['unique']));
      }

      // Great.
      if ($type === 'text') {
        if ($t['maxlength'] > 65535) {
          $t['def'] = 'MEDIUMTEXT';
        }
        elseif ($t['maxlength'] > 255) {
          $t['def'] = 'TEXT';
        }
        else {
          // allow 10% more than the max chars so far.
          $t['def'] = 'VARCHAR(' . ((int) (1.10 * $t['maxmblength'])) . ')';
        }
        // We don't know that we need to differentiate between zero length string and NULL;
        // the INSERTS will be zls, so call this column NOT NULL.
        $t['def'] .= ' NOT NULL DEFAULT ""';
      }
      elseif ($type === 'unsigned_int') {
        if ($t['maxint'] <256) {
          $t['def'] = 'TINYINT UNSIGNED';
        }
        else {
          $t['def'] = 'INT(10) UNSIGNED';
        }
        if (!$t['empty']) {
          $t['def'] .= ' NOT NULL DEFAULT 0';
        }
      }
      elseif ($type === 'signed_int') {
        if ($t['maxint'] < 127) {
          $t['def'] = 'TINYINT SIGNED';
        }
        else {
          $t['def'] = 'INT(10) SIGNED';
        }
        if (!$t['empty']) {
          $t['def'] .= ' NOT NULL DEFAULT 0';
        }
      }
      elseif ($type === 'float') {
        $t['def'] = 'DECIMAL(12,4)';
        if (!$t['empty']) {
          $t['def'] .= ' NOT NULL DEFAULT 0';
        }
      }
      elseif ($type === 'date') {
        $t['def'] = 'DATE';
        // All date columns are created with NULL as default
        // since setting a default requires a specific date.
        // if (!$t['empty']) {
        //   $t['def'] .= ' NOT NULL DEFAULT 0';
        // }
      }
      elseif ($type === 'datetime') {
        $t['def'] = 'TIMESTAMP';
        if (!$t['empty']) {
          $t['def'] .= ' NOT NULL DEFAULT CURRENT_TIMESTAMP';
        }
      }
      else {
        throw new \RuntimeException("Row " . $parser->key() . " col '" . $colname . "' unexpected type '$type'");
      }
      $cols[$colname]['type'] = $type;

      $schemaCols[$t['name']] = $t['def'];
      if (strtolower($t['name']) === 'id') {
        $schemaCols[$t['name']] .= ' PRIMARY KEY';
        $foundID = $t['name'];
      }
      if (isset($progressBar)) {
        $progressBar->advance();
      }
    }

    $schemaSQL = '';
    if ($input->getOption('drop')) {
      $schemaSQL = "DROP TABLE IF EXISTS `$tableName`;\n";
    }
    $schemaSQL .= "CREATE TABLE IF NOT EXISTS `$tableName` (\n";
    // Add an ID if there is none.
    $prefix = '';
    if (!$foundID) {
      $schemaSQL .= "  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY";
      $prefix = ",\n  ";
    }

    foreach ($schemaCols as $colName => $def) {
      $schemaSQL .= $prefix . "`$colName` $def";
      $prefix = ",\n  ";
    }
    $schemaSQL .= "\n);\n";

    $output->writeln("<info>$schemaSQL</info>");

    if (isset($progressBar)) {
      $progressBar->finish();
      $progressBar->clear();
    }

    if ($input->getOption('schema')) {
      return 0;
    }

    // Output the data.
    $insert = "INSERT INTO `$tableName` (`"
      . implode('`, `', array_keys($schemaCols))
      . "`) VALUES \n";

    // 10k per SQL command.
    $maxCommandLength = 1024*$input->getOption('max-command-length');
    $maxValuesLength = $maxCommandLength - strlen($insert);
    $command = $insert;
    $sep = '';
    if (isset($progressBar)) {
      $progressBar = new ProgressBar($output, $parser->count());
      $progressBar->setFormat('custom');
      $progressBar->setMessage("Writing INSERTS");
      $progressBar->start();
    }
    foreach ($parser as $row) {
      $data = [];
      foreach ($cols as $header=>$col) {
        $val = $row->$header;
        if (in_array($col['type'], ['unsigned_int', 'signed_int', 'float'])) {
          // We can trust this value to be safe because of the regex above.
        }
        else {
          // Text for everything else.
          $val = '"' . str_replace('"', '\\"', $val) . '"';
        }
        $data[] = $val;
      }
      $data = '(' . implode(',', $data) . ')';
      if (strlen($data) > $maxValuesLength) {
        throw new \RuntimeException("Row " . $row->key() . " exceeds max SQL command length");
      }
      if (strlen($data) + strlen($command) > $maxCommandLength) {
        // complete last command.
        $command .= ";\n";
        $sep = '';
        $output->writeln($command);
        $command = $insert;
      }
      $command .= $sep . $data;
      $sep = ',';

      if (isset($progressBar)) {
        $progressBar->advance();
      }
    }
    $command .= ";\n";
    $output->writeln($command);
    $progressBar->finish();
    $progressBar->clear();

    return 0;
  }
}

