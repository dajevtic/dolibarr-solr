<?php
/* Copyright (C) 2019 Elb Solutions
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *    \file       elbsolr/search.php
 *    \ingroup    elbsolr
 *    \brief      Page for searching indexed documents
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
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once 'lib/elbsolr.lib.php';
dol_include_once('/elbsolr/class/elbsolrutil.class.php', 'ElbSolrUtil');

$hookmanager->initHooks(array('formfile'));

$action = GETPOST('action', 'alpha');
$page = GETPOST("page", 'int');
$search_btn = GETPOST('button_search', 'alpha');
$search_remove_btn = GETPOST('button_removefilter', 'alpha');

if ($page == -1 || $page == null || !empty($search_btn) || !empty($search_remove_btn) || (empty($toselect) && $massaction === '0')) {
	$page = 0;
}
$sortfield = GETPOST("sortfield", 'alpha');
$sortorder = GETPOST("sortorder", 'alpha');
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$search_file = GETPOST('search_file', 'alpha');
$search_content = $_REQUEST['search_content'];

if(empty($sortfield)) {
    $sortfield = "date";
    $sortorder = "desc";
}

// Load translation files required by the page
$langs->loadLangs(array("elbsolr@elbsolr"));

$action = GETPOST('action', 'alpha');

$elbSolr = new ElbSolrUtil();

if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) // All tests are required to be compatible with all browsers
{
	header("location: ".$_SERVER['PHP_SELF']);
	exit;
}

/*
 * Actions
 */

// None


/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader("", $langs->trans("DocumentSearch"));

$files = array();

$res = $elbSolr->search($search_file, $search_content, $limit, $page, $sortfield, $sortorder);

$response = $elbSolr->response;
$highlighting = $elbSolr->highlighting;
$docs = $response['docs'];
$numFound = $response['numFound'];
$file_ids = array();
$indexed_files = array();
foreach ($docs as $doc) {
	$file_ids[] = $doc['elb_fileid'];
	$indexed_files[$doc['elb_fileid']] = $doc;
}

if (count($file_ids) > 0) {
	$list = implode(",", $file_ids);
	$sql = "SELECT f.*, u.rowid as u_rowid, u.login, u.firstname, u.lastname, u.email
    FROM " . MAIN_DB_PREFIX . "ecm_files f 
    left join " . MAIN_DB_PREFIX . "user u on (u.rowid = f.fk_user_c)
    where f.rowid IN ($list) 
    ORDER BY FIELD(f.rowid, $list)";
	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		$i = 0;
		while ($i < $num) {
			$row = $db->fetch_object($resql);
			if ($row) {
				$file = array();
				$file['id'] = $indexed_files[$row->rowid]['id'];
				$file['name'] = $indexed_files[$row->rowid]['elb_name'];
				$modulepart = elbsolr_get_file_modulepart($row->filepath);
				$relpath = substr($row->filepath, strlen($modulepart) + 1) . "/" . $row->filename;
				$file['filepath'] = $elbSolr->getFullFilePath($row);
				$file['object_type'] = $row->src_object_type;
				$file['object_id'] = $row->src_object_id;
				$file['description'] = $row->description;
				$file['modulepart'] = $modulepart;
				$file['relpath'] = $relpath;
				$file['link'] = elbsolr_build_file_link($modulepart, $relpath, $row->entity);
				$file['object_link'] = elbsolr_get_object_link($modulepart, $relpath);
				if (file_exists($file['filepath'])) {
					$size = dol_filesize($file['filepath']);
					$file['size'] = $size;
					$file['date'] = $row->date_c;
				}
				$file['index_data'] = $indexed_files[$row->rowid];
				$user_creator = new User($db);
				$user_creator->id = $row->fk_user_c;
				$user_creator->login = $row->login;
				$user_creator->firstname = $row->firstname;
				$user_creator->lastname = $row->lastname;
				$user_creator->email = $row->email;
				$file['user_link'] = $user_creator->getNomUrl(1);
				$files[] = $file;
			}
			$i++;
		}
		$db->free($resql);
	} else {
		dol_print_error($db);
	}
}

if ($limit > 0 && $limit != $conf->liste_limit) $param .= '&limit=' . $limit;
if ($search_file != '') $param .= '&search_file=' . $search_file;
if ($search_content != '') $param .= '&search_content=' . $search_content;

$parameters = array('param' => $param);
$object = null;
$action = null;
$reshook = $hookmanager->executeHooks('solrSearchUrlParams', $parameters, $object, $action);
if ($reshook > 0) {
	$param = $hookmanager->resArray['param'];
}

//Set timezone because Solr server timezone iz in UTC
date_default_timezone_set('UTC');

?>

    <form method="get" action="<?php echo $_SERVER['PHP_SELF'] ?>">
        <input type="hidden" name="action" value="search"/>

		<?php
		$num = (count($files) == $numFound) ? count($files) : count($files) + 1;
		print_barre_liste($langs->trans("DocumentSearch"), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton = '', $num, $numFound, 'elbsolr.png@elbsolr', 0, '', '', $limit);
		?>

        <div class="liste_titre liste_titre_bydiv centpercent">
            <div class="divsearchfield">
				<?php echo $langs->trans('Content') ?>:
                <input class="flat" size="50" name="search_content" value="<?php echo htmlspecialchars($search_content) ?>"/>
            </div>
            <?php

            $parameters = array ();
            $object=null;
            $action=null;
            $reshook = $hookmanager->executeHooks('solrSearchAdditionalSearch', $parameters, $object, $action);
            if ($reshook > 0) {
	            print $hookmanager->resArray['solrSearchAdditionalSearch'];
            }

            ?>
        </div>
        <div class="div-table-responsive">
            <table class="tagtable liste listwithfilterbefore" width="100%">
                <thead>
                <tr class="liste_titre_filter">
                    <td>
                        <input class="flat" size="30" name="search_file" value="<?php echo $search_file ?>"/>
                        <?php echo $form->textwithpicto('',$langs->trans('FileSearchHelp')) ?>
                    </td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <?php
                    $parameters = array ();
                    $object=null;
                    $action=null;
                    $columns = array ();
                    $reshook = $hookmanager->executeHooks('solrSearchAdditionalColumnSearch', $parameters, $object, $action);
                    if ($reshook > 0) {
	                    $columns = $hookmanager->resArray['columns'];
	                    foreach($columns as $column) {
	                        print $column;
                        }
                    }
                    ?>
                    <td align="right">
						<?php echo $form->showFilterButtons(); ?>
                    </td>
                </tr>
                <tr class="liste_titre">
	                <?php print_liste_field_titre('File', $_SERVER["PHP_SELF"], "file", "", $param, "", $sortfield, $sortorder) ?>
                    <th class="liste_titre"><?php echo $langs->trans('Ref') ?></th>
					<?php print_liste_field_titre('Size', $_SERVER["PHP_SELF"], "size", "", $param, "", $sortfield, $sortorder) ?>
					<?php print_liste_field_titre('Date', $_SERVER["PHP_SELF"], "date", "", $param, "", $sortfield, $sortorder) ?>
                    <th class="liste_titre"><?php echo $langs->trans('User') ?></th>
	                <?php
	                $parameters = array ();
	                $object=null;
	                $action=null;
	                $reshook = $hookmanager->executeHooks('solrSearchAdditionalColumnHeader', $parameters, $object, $action);
	                if ($reshook > 0) {
		                $headers = $hookmanager->resArray['headers'];
		                foreach($headers as $header) {
			                print $header;
		                }
	                }
	                ?>
                    <th></th>
                </tr>
                </thead>
                <tbody>
				<?php if (count($files) > 0) { ?>
					<?php foreach ($files as $file) { ?>
                        <tr>
                            <td>
								<?php
								print '<a class="paddingright" href="' . $file['link'] . '">';
								print img_mime($file['name'], $file['name'] . ' (' . dol_print_size($file['size'], 0, 0) . ')', 'inline-block valignbottom paddingright');
								print dol_trunc($file['name'], 200);
								print '</a>';
								print $formfile->showPreview($file, $file['modulepart'], $file['relpath']);
								if (isset($highlighting[$file['id']]) && strlen($highlighting[$file['id']]['attr_content'][0]) > 0) {
									print '<p class="file-search-highlight">';
									print $highlighting[$file['id']]['attr_content'][0];
									print '</p>';
								}
								?>
                            </td>
                            <td nowrap="nowrap">
								<?php
								if (!empty($file['object_link'])) {
									print $file['object_link'];
								}
								?>
                            </td>
                            <td nowrap="nowrap">
								<?php
								$sizetoshow = dol_print_size($file['size'], 1, 1);
								$sizetoshowbytes = dol_print_size($file['size'], 0, 1);
								if ($sizetoshow == $sizetoshowbytes) {
									print $sizetoshow;
								} else {
									print $form->textwithpicto($sizetoshow, $sizetoshowbytes, -1);
								}
								?>
                            </td>
                            <td nowrap="nowrap">
								<?php print dol_print_date($file['date'], "dayhour", "tzserver") ?>
                            </td>
                            <td nowrap="nowrap">
								<?php print $file['user_link'] ?>
                            </td>
	                        <?php
	                        $parameters = array('file' => $file);
	                        $object = null;
	                        $action = null;
	                        $reshook = $hookmanager->executeHooks('solrSearchAdditionalColumnData', $parameters, $object, $action);
	                        if ($reshook > 0) {
		                        $data = $hookmanager->resArray['data'];
		                        foreach($data as $td) {
			                        print $td;
		                        }
	                        }
	                        ?>
                            <td></td>
                        </tr>
					<?php } ?>
				<?php } else { ?>
                    <tr>
                        <td class="opacitymedium" colspan="<?php echo 7 + count($columns) ?>">
							<?php echo $langs->trans('NoResults') ?>
                        </td>
                    </tr>
				<?php } ?>
                </tbody>
            </table>
        </div>
    </form>

<?php

$error = $elbSolr->getErrorMessage();
if (!empty($error)) {
	print '<div>' . $elbSolr->getErrorMessage() . '</div>';
}

// End of page
llxFooter();
$db->close();
