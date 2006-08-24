<?php

require_once 'PEAR/PackageFileManager.php';

$version = 'YYY';
$notes = <<<EOT
- flip positions property array in prepared statement objects to make it
  possible to optionally use the same named placeholder in multiple places
  inside a single prepared statement

note:
- please use the latest ext/oci8 version from pecl.php.net/oci8
 (binaries are available from snaps.php.net and pecl4win.php.net)
- by default this driver emulates the database concept other RDBMS have by this
  using the "database" instead of "username" in the DSN as the username name.
  behaviour can be disabled by setting the "emulate_database" option to false.
- the multi_query test failes because this is not supported by ext/oci8
- the null LOB test failes because this is not supported by Oracle

open todo items:
- enable use of read() for LOBs to read a LOB in chunks
EOT;

$package = new PEAR_PackageFileManager();

$result = $package->setOptions(
    array(
        'packagefile'       => 'package_oci8.xml',
        'package'           => 'MDB2_Driver_oci8',
        'summary'           => 'oci8 MDB2 driver',
        'description'       => 'This is the Oracle OCI8 MDB2 driver.',
        'version'           => $version,
        'state'             => 'stable',
        'license'           => 'BSD License',
        'filelistgenerator' => 'cvs',
        'include'           => array('*oci8*'),
        'ignore'            => array('package_oci8.php'),
        'notes'             => $notes,
        'changelogoldtonew' => false,
        'simpleoutput'      => true,
        'baseinstalldir'    => '/',
        'packagedirectory'  => './',
        'dir_roles'         => array(
            'docs' => 'doc',
             'examples' => 'doc',
             'tests' => 'test',
             'tests/templates' => 'test',
        ),
    )
);

if (PEAR::isError($result)) {
    echo $result->getMessage();
    die();
}

$package->addMaintainer('lsmith', 'lead', 'Lukas Kahwe Smith', 'smith@pooteeweet.org');
$package->addMaintainer('justinpatrin', 'developer', 'Justin Patrin', 'justinpatrin@php.net');

$package->addDependency('php', '4.3.0', 'ge', 'php', false);
$package->addDependency('PEAR', '1.0b1', 'ge', 'pkg', false);
$package->addDependency('MDB2', '2.2.1', 'ge', 'pkg', false);
$package->addDependency('oci8', null, 'has', 'ext', false);

$package->addglobalreplacement('package-info', '@package_version@', 'version');

if (array_key_exists('make', $_GET) || (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'make')) {
    $result = $package->writePackageFile();
} else {
    $result = $package->debugPackageFile();
}

if (PEAR::isError($result)) {
    echo $result->getMessage();
    die();
}
