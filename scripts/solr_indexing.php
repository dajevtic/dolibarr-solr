#!/usr/bin/env php
<?php
/* Copyright (C) 2019 Elb Solutions
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    elbsolr/scripts/solr_indexing.php
 * \ingroup elbsolr
 * \brief   Indexing script
 *
 * Indexing script in background process that is called from php-cli
 */

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path = dirname(__FILE__) . '/';

$sapi_type = php_sapi_name();
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: You are using PHP for CGI. To execute " . $script_file . " from command line, you must use PHP for CLI mode.\n";
	exit(-1);
}

$file_path = $path . "../../../master.inc.php";
if (file_exists($file_path)) {
	require $file_path;
}
$file_path = $path . "../../master.inc.php";
if (file_exists($file_path)) {
	require $file_path;
}

include_once DOL_DOCUMENT_ROOT . '/ecm/class/ecmfiles.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
dol_include_once('/elbsolr/lib/elbsolr.lib.php');
dol_include_once('/elbsolr/class/elbsolrutil.class.php', 'ElbSolrUtil');

$version = DOL_VERSION;
$error = 0;
$now = dol_now('tzserver');

$elbSolrUtil = new ElbSolrUtil();

@set_time_limit(0);
@ini_set('memory_limit', $elbSolrUtil->memory_limit);
$pid = dol_getmypid();
print "***** " . $script_file . " (" . $version . ") pid=" . $pid . " *****\n";
dol_syslog($script_file . " launched with arg " . join(',', $argv));

$status_folder = DOL_DATA_ROOT . "/elbsolr/temp";
if (!file_exists($status_folder)) {
	dol_mkdir($status_folder);
}
$indexing_status_file = $elbSolrUtil->getIndexingStatusFile();
$status_data = array(
	"pid" => $pid,
	"started" => $now,
	"errors" => 0,
	"memory" => memory_get_usage(),
	"processed" => 0,
	"count" => 0
);
$error_files = array();
file_put_contents($indexing_status_file, json_encode($status_data));

dol_syslog("ElbSolr: Start indexing");

$ecmfile = new EcmFiles($db);
$ecmfile->fetchAll();
$files = $ecmfile->lines;
$status_data['count'] = count($files);
$status_data['processed'] = 0;
$status_data['errors'] = 0;
$status_data['missing'] = 0;
$status_data['error_files'] = array();
foreach ($files as $file) {
    $status_data['processed']++;
    $status_data['elapsed'] = dol_now('tzserver') - $now;
	$file_path = $elbSolrUtil->getFullFilePath($file);
	if (!file_exists($file_path)) {
		$status_data['missing']++;
		continue;
	}
    $res = $elbSolrUtil->addToSearchIndex($file);
    if (!$res) {
        $status_data['errors']++;
        $error_msg = $elbSolrUtil->getErrorMessage();
        $error_files[] = $file->filepath . DIRECTORY_SEPARATOR . $file->filename . (!empty($error_msg) ? (": " . $error_msg) : "");
    }
    $status_data['memory'] = memory_get_usage();
    file_put_contents($indexing_status_file, json_encode($status_data));
    dol_syslog("ElbSolr: Process: $pid Indexing ".$status_data['processed']." of ".$status_data['count']." documents");
}


$status_data['elapsed'] = dol_now('tzserver') - $now;
$status_data['error_files'] = $error_files;
unset($status_data['pid']);

file_put_contents($indexing_status_file, json_encode($status_data));
exit;

