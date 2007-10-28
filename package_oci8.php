<?php

require_once 'PEAR/PackageFileManager2.php';
PEAR::setErrorHandling(PEAR_ERROR_DIE);

$version = '1.5.0a1';
$state = 'alpha';
$notes = <<<EOT
- initial support for FOREIGN KEY and CHECK constraints in the Reverse and Manager modules
- fixed bug #10969: execute() does not bind reference variables (patch by Charles Woodcock)
- request #11297: added support for "owner.table" notation in the Manager and Reverse modules
- fixed bug #11428: propagate quote() errors with invalid data types
- use prepared queries in the list*() methods of the Manager module and in the
  Reverse module (thanks to Hugh Dixon)
- add support for "owner" parameter in listViews(), listFunctions(), listTables(),
  listSequences() in the Manager module
- added listTableTriggers() in the Manager module
- do not list constraints in listTableIndexes() in the Manager module
- fixed bug #11790: avoid array_diff() because it has a memory leak in PHP 5.1.x
- fixed bug #11933: avoid duplicate queries in the Reverse module and free results
  and prepared statement handles (thanks Jan Reitz)
- fixed some E_STRICT errors with PHP5
- fixed bug #12083: createTable() in the Manager module now returns MDB2_OK on success,
  as documented

note:
- please use the latest ext/oci8 version from pecl.php.net/oci8
 (binaries are available from snaps.php.net and pecl4win.php.net)
- by default this driver emulates the database concept other RDBMS have by 
  using the "database" option instead of "username" in the DSN as the username name.
  This behaviour can be disabled by setting the "emulate_database" option to false.
- the multi_query test failes because this is not supported by ext/oci8
- the null LOB test failes because this is not supported by Oracle

open todo items:
- enable use of read() for LOBs to read a LOB in chunks
- buffer LOB's when doing buffered queries
EOT;

$description = 'This is the Oracle OCI8 MDB2 driver.';
$packagefile = './package_oci8.xml';

$options = array(
    'filelistgenerator' => 'cvs',
    'changelogoldtonew' => false,
    'simpleoutput'      => true,
    'baseinstalldir'    => '/',
    'packagedirectory'  => './',
    'packagefile'       => $packagefile,
    'clearcontents'     => false,
    'include'           => array('*oci8*'),
    'ignore'            => array('package_oci8.php'),
);

$package = &PEAR_PackageFileManager2::importOptions($packagefile, $options);
$package->setPackageType('php');

$package->clearDeps();
$package->setPhpDep('4.3.0');
$package->setPearInstallerDep('1.4.0b1');
$package->addPackageDepWithChannel('required', 'MDB2', 'pear.php.net', '2.5.0a1');
$package->addExtensionDep('required', 'oci8');

$package->addRelease();
$package->generateContents();
$package->setReleaseVersion($version);
$package->setAPIVersion($version);
$package->setReleaseStability($state);
$package->setAPIStability($state);
$package->setNotes($notes);
$package->setDescription($description);
$package->addGlobalReplacement('package-info', '@package_version@', 'version');

if (isset($_GET['make']) || (isset($_SERVER['argv']) && @$_SERVER['argv'][1] == 'make')) {
    $package->writePackageFile();
} else {
    $package->debugPackageFile();
}