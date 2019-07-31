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
 * \file    elbsolr/admin/status.php
 * \ingroup elbsolr
 * \brief   Status page for Solr service
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) $res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once '../lib/elbsolr.lib.php';
dol_include_once('/elbsolr/class/elbsolrutil.class.php', 'ElbSolrUtil');

$form = new Form($db);
$elbSolr = new ElbSolrUtil();

// Translations
$langs->loadLangs(array("errors", "admin", "elbsolr@elbsolr"));

// Access control
if (!$user->admin) accessforbidden();

// Parameters
$action = GETPOST('action', 'alpha');
$confirm = GETPOST('confirm');
$backtopage = GETPOST('backtopage', 'alpha');


$indexing_script_file = $elbSolr->getIndexingScriptFile();

/*
 * Actions
 */

if ($action == "confirm_clear_all" && $confirm == "yes") {
	$res = $elbSolr->clearAllIndexedDocuments();
	if ($res) {
		setEventMessage($langs->trans('SolrIndexDeletedSuccess'));
	} else {
		setEventMessage($langs->trans('SolrIndexDeletedError', $elbSolr->getErrorMessage()), 'errors');
	}
	header("location: " . $_SERVER['PHP_SELF']);
	exit;
} else if ($action == "confirm_index_all" && $confirm == "yes") {
	if (!file_exists($indexing_script_file)) {
		setEventMessage($langs->trans('MissingIndexingScriptFile'), 'errors');
	} else {
		$cmd = "php $indexing_script_file > /dev/null 2>&1 &";
		session_write_close();
		shell_exec($cmd);
	}
	header("location: " . $_SERVER['PHP_SELF']);
	exit;
} else if ($action == "confirm_stop_indexing" && $confirm == "yes") {
	$error = false;
	$indexing_status_file = $elbSolr->getIndexingStatusFile();
	if (!file_exists($indexing_status_file)) {
		$error = 'MissingIndexingFile';
	} else {
		$status = json_decode(file_get_contents($indexing_status_file), true);
		if (empty($status)) {
			$error = 'ErrorReadingIndexingStatus';
		} else {
			if (!isset($status['pid'])) {
				$error = 'UnknownIndexingPid';
			} else {
				$pid = $status['pid'];
				$cmd = "kill $pid";
				session_write_close();
				$res = shell_exec($cmd);
				if (!$res) {
					$error = 'ErrorStoppingProcess';
				}
			}
		}
	}
	if (!empty($error)) {
		setEventMessage($langs->trans('StoppingIndexingError', $langs->trans($error)), 'errors');
	} else {
		setEventMessage($langs->trans('StoppingIndexingSuccess'));
	}
	header("location: " . $_SERVER['PHP_SELF']);
	exit;
} else if ($action == "clear_status") {
	$indexing_status_file = $elbSolr->getIndexingStatusFile();
	unlink($indexing_status_file);
	header("location: " . $_SERVER['PHP_SELF']);
	exit;
}


/*
 * View
 */

if ($action == "clear_all") {
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('ClearAllIndexedDocuments'), $langs->trans('ConfirmClearAllIndexedDocuments'), 'confirm_clear_all', null, 'yes', 1);
} elseif ($action == "index_all") {
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('IndexAllDocuments'), $langs->trans('ConfirmIndexAllDocuments'), 'confirm_index_all', null, 'yes', 1);
} elseif ($action == "stop_indexing") {
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('StopIndexing'), $langs->trans('ConfirmStopIndexing'), 'confirm_stop_indexing', null, 'yes', 1);
}

$page_name = "SolrStatus";
llxHeader('', $langs->trans($page_name));

// Print form confirm
print $formconfirm;

// Subheader
$linkback = '<a href="' . ($backtopage ? $backtopage : DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans("BackToModuleList") . '</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'elbsolr@elbsolr');

// Configuration header
$head = elbsolrAdminPrepareHead();
dol_fiche_head($head, 'status', '', -1, 'elbsolr@elbsolr');

$solrServerUrl = $conf->global->ELBSOLR_SOLR_SERVER_URL;
$parts = explode("/", $solrServerUrl);
array_pop($parts);
$solrServerUrlInterface = implode("/", $parts);
$solrServerUrlInterface .= "/";

$status = $elbSolr->getStatus();
$docNum = $elbSolr->getNumberOfDocuments();
$indexingStatus = $elbSolr->getIndexingStatus();
?>

    <table class="noborder" width="100%">
        <tbody>
        <tr>
            <td><?php echo $langs->trans('ELBSOLR_SOLR_SERVER_URL') ?></td>
            <td colspan="2">
                <a href="<?php echo $solrServerUrlInterface ?>" target="_blank">
					<?php echo $solrServerUrlInterface ?>
                </a>
            </td>
        </tr>
        <tr>
            <td><?php echo $langs->trans('SolrServerStatus') ?></td>
            <td colspan="2">
				<?php echo $status['success'] ? "RUNNING" : "FAILED" ?>
            </td>
        </tr>
        <tr>
            <td>
				<?php echo $langs->trans('NumberOfIndexedDocuments') ?>
            </td>
            <td colspan="2">
				<?php echo $docNum ?>
            </td>
        </tr>
        <tr>
            <td valign="top">
				<?php echo $langs->trans('IndexingStatus') ?>
            </td>
            <td valign="top">
				<?php echo $indexingStatus ?>
				<?php
				$indexingErrors = $elbSolr->getIndexingErrors();
				if(!empty($indexingErrors)) {
				    echo '<hr/>';
				    echo $langs->trans('NotIndexedFiles').':';
				    echo '<ul>';
					foreach ($indexingErrors as $indexingError) {
						echo '<li>';
						echo $indexingError;
						echo '</li>';
					}
				    echo '</ul>';
				}
				?>
            </td>
            <td align="right" valign="top">
				<?php if ($elbSolr->isIndexingInProgress()) { ?>
                    <a class="butAction"
                       href="<?php echo $_SERVER["PHP_SELF"] ?>"><?php echo $langs->trans('Refresh') ?></a>
                    <a class="butAction"
                       href="<?php echo $_SERVER["PHP_SELF"] ?>?action=stop_indexing"><?php echo $langs->trans('StopIndexing') ?></a>
				<?php } ?>
				<?php if ($elbSolr->isIndexingFinished()) { ?>
                    <a class="butAction"
                       href="<?php echo $_SERVER["PHP_SELF"] ?>?action=clear_status"><?php echo $langs->trans('Clear') ?></a>
				<?php } ?>
            </td>
        </tr>
        </tbody>
    </table>

    <div class="tabsAction">
		<?php if (!$elbSolr->isIndexingInProgress()) { ?>
            <a class="butAction"
               href="<?php echo $_SERVER["PHP_SELF"] ?>?action=clear_all"><?php echo $langs->trans('ClearAllIndexedDocuments') ?></a>
            <a class="butAction"
               href="<?php echo $_SERVER["PHP_SELF"] ?>?action=index_all"><?php echo $langs->trans('IndexAllDocuments') ?></a>
		<?php } ?>
    </div>


<?php


// Page end
dol_fiche_end();
llxFooter();
$db->close();
