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
 * \file    elbsolr/lib/elbsolr.lib.php
 * \ingroup elbsolr
 * \brief   Library files with common functions for ElbSolr
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function elbsolrAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("elbsolr@elbsolr");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/elbsolr/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;
	$head[$h][0] = dol_buildpath("/elbsolr/admin/status.php", 1);
	$head[$h][1] = $langs->trans("Status");
	$head[$h][2] = 'status';
	$h++;
	$head[$h][0] = dol_buildpath("/elbsolr/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@elbsolr:/elbsolr/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@elbsolr:/elbsolr/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, null, $head, $h, 'elbsolr');

	return $head;
}

/**
 * Retrives document modulepart from file relative path
 *
 * @param string $filepath filepath from ecm_files
 * @return string modulepart
 */
function elbsolr_get_file_modulepart($filepath)
{
	$arr = explode("/", $filepath);
	$modulepart = $arr[0];
	return $modulepart;
}

/**
 * Builds file download link from file elements
 *
 * @param string $modulepart    modulepart for document file
 * @param string $filepath      relative path to file
 * @param integer $entity       entity for multicompany
 * @param int $forcedownload    Force to open dialog box "Save As" when clicking on file.
 * @return string               Full link to download file
 */
function elbsolr_build_file_link($modulepart, $filepath, $entity = null, $forcedownload = 0)
{

	global $hookmanager;
	$parameters = array();
	$parameters['modulepart'] = $modulepart;
	$parameters['filepath'] = $filepath;
	$parameters['entity'] = $entity;
	$parameters['forcedownload'] = $forcedownload;
	$reshook = $hookmanager->executeHooks('getObjectDownloadLink', $parameters, $object, $action);
	if ($reshook > 0) {
		$link = $hookmanager->resArray['link'];
	} else {
		if($modulepart == 'expedition') {
			$filepath = str_replace("sending/", "", $filepath);
		}
		$link = DOL_URL_ROOT . '/document.php?modulepart=' . $modulepart;
		if ($forcedownload) {
			$link .= '&attachment=1';
		}
		if (!empty($entity)) {
			$link .= '&entity=' . $entity;
		}
		$link .= '&file=' . urlencode($filepath);
	}

	return $link;
}

/**
 * Function build object link for various modules in Dolibarr
 *
 * @param string $modulepart    modulepart of document
 * @param string $relativefile  relative path to file
 * @return bool|string full url to object or false
 */
function elbsolr_get_object_link($modulepart, $relativefile)
{
	global $db, $hookmanager,$langs;

	//From \FormFile::list_of_autoecmfiles

	if($modulepart == "produit") $modulepart="product";
	if($modulepart == "propale") $modulepart="propal";
	if($modulepart == "commande") $modulepart="order";
	if($modulepart == "projet") $modulepart="project";
	if($modulepart == "adherent") $modulepart="member";
	if($modulepart == "agenda") $modulepart="actions";
	if($modulepart == "facture") $modulepart="invoice";
	if($modulepart == "ficheinter") $modulepart="fichinter";
	if($modulepart == "produitlot") $modulepart="product_batch";
	if($modulepart == "societe") $modulepart="company";

	$object_fetched = false;
	$link_option = 'document';

	if ($modulepart == 'company') {
		include_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
		$object_instance = new Societe($db);
	} else if ($modulepart == 'invoice') {
		include_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
		$object_instance = new Facture($db);
	} else if ($modulepart == 'invoice_supplier') {
		include_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
		$object_instance = new FactureFournisseur($db);
	} else if ($modulepart == 'propal') {
		include_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
		$object_instance = new Propal($db);
		$langs->load('propal');
	} else if ($modulepart == 'supplier_proposal') {
		include_once DOL_DOCUMENT_ROOT . '/supplier_proposal/class/supplier_proposal.class.php';
		$object_instance = new SupplierProposal($db);
	} else if ($modulepart == 'order') {
		include_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
		$object_instance = new Commande($db);
	} else if ($modulepart == 'order_supplier') {
		include_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.commande.class.php';
		$object_instance = new CommandeFournisseur($db);
	} else if ($modulepart == 'contract') {
		include_once DOL_DOCUMENT_ROOT . '/contrat/class/contrat.class.php';
		$object_instance = new Contrat($db);
	} else if ($modulepart == 'product') {
		include_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
		$object_instance = new Product($db);
	} else if ($modulepart == 'tax') {
		include_once DOL_DOCUMENT_ROOT . '/compta/sociales/class/chargesociales.class.php';
		$object_instance = new ChargeSociales($db);
		$langs->load('bills');
	} else if ($modulepart == 'project') {
		include_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
		$object_instance = new Project($db);
	} else if ($modulepart == 'fichinter') {
		include_once DOL_DOCUMENT_ROOT . '/fichinter/class/fichinter.class.php';
		$object_instance = new Fichinter($db);
	} else if ($modulepart == 'user') {
		include_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
		$object_instance = new User($db);
	} else if ($modulepart == 'expensereport') {
		include_once DOL_DOCUMENT_ROOT . '/expensereport/class/expensereport.class.php';
		$object_instance = new ExpenseReport($db);
		$langs->load('trips');
	} else if ($modulepart == 'holiday') {
		include_once DOL_DOCUMENT_ROOT . '/holiday/class/holiday.class.php';
		$object_instance = new Holiday($db);
	} else if ($modulepart == 'member') {
		include_once DOL_DOCUMENT_ROOT . '/adherents/class/adherent.class.php';
		$object_instance = new Adherent($db);
	} else if ($modulepart == 'actions') {
		include_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
		$object_instance = new ActionComm($db);
	} else if ($modulepart == 'bank') {
		include_once DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php';
		$object_instance = new Account($db);
	} else if ($modulepart == 'banque') {
		include_once DOL_DOCUMENT_ROOT . '/compta/bank/class/paymentvarious.class.php';
		$object_instance = new PaymentVarious($db);
	} else if ($modulepart == 'salaries') {
		include_once DOL_DOCUMENT_ROOT . '/compta/salaries/class/paymentsalary.class.php';
		$object_instance = new PaymentSalary($db);
		$langs->load('salaries');
	} else if ($modulepart == 'societe') {
		$arr = explode("/", $relativefile);
		$submodule = $arr[0];
		if($submodule == 'contact') {
			include_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
			$object_instance = new Contact($db);
			$id = $arr[1];
		}
	} else if ($modulepart == 'fournisseur') {
		$arr = explode("/", $relativefile);
		$submodule = $arr[0];
		if($submodule == 'commande') {
			include_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.commande.class.php';
			$object_instance = new CommandeFournisseur($db);
			$ref = $arr[1];
		} else if ($submodule == 'facture') {
			include_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
			$object_instance = new FactureFournisseur($db);
			$ref = $arr[3];
		}
		$langs->load('orders');
	} else if ($modulepart == 'don') {
		include_once DOL_DOCUMENT_ROOT . '/don/class/don.class.php';
		$object_instance = new Don($db);
		$arr = explode("/", $relativefile);
		$id = $arr[1];
		$langs->load('donations');
	} else if ($modulepart == 'users') {
		$object_instance = new User($db);
		$arr = explode("/", $relativefile);
		$id = $arr[0];
	} else if ($modulepart == 'expedition') {
		include_once DOL_DOCUMENT_ROOT . '/expedition/class/expedition.class.php';
		$object_instance = new Expedition($db);
		$arr = explode("/", $relativefile);
		$ref = $arr[1];
		$langs->load('sendings');
	} else if ($modulepart == 'resource') {
		include_once DOL_DOCUMENT_ROOT . '/resource/class/dolresource.class.php';
		$object_instance = new DolResource($db);
		$arr = explode("/", $relativefile);
		$ref = $arr[0];
		$link_option = '';
	} else if ($modulepart == 'loan') {
		include_once DOL_DOCUMENT_ROOT . '/loan/class/loan.class.php';
		$object_instance = new Loan($db);
		$arr = explode("/", $relativefile);
		$id = $arr[0];
		$langs->load('loan');
	} else if ($modulepart == 'product_batch') {
		include_once DOL_DOCUMENT_ROOT . '/product/stock/class/productlot.class.php';
		$object_instance = new Productlot($db);
		$arr = explode("/", $relativefile);
		$ref = $arr[0];
		$object_instance->fetch(0,0,$ref);
		$object_fetched = true;
	}

	if ($modulepart == 'company') {
		preg_match('/(\d+)\/[^\/]+$/', $relativefile, $reg);
		$id = (isset($reg[1]) ? $reg[1] : '');
	}
	if ($modulepart == 'invoice') {
		preg_match('/(.*)\/[^\/]+$/', $relativefile, $reg);
		$ref = (isset($reg[1]) ? $reg[1] : '');
	}
	if ($modulepart == 'invoice_supplier') {
		preg_match('/([^\/]+)\/[^\/]+$/', $relativefile, $reg);
		$ref = (isset($reg[1]) ? $reg[1] : '');
		if (is_numeric($ref)) {
			$id = $ref;
			$ref = '';
		}
	}    // $ref may be also id with old supplier invoices
	if ($modulepart == 'propal') {
		preg_match('/(.*)\/[^\/]+$/', $relativefile, $reg);
		$ref = (isset($reg[1]) ? $reg[1] : '');
	}
	if ($modulepart == 'supplier_proposal') {
		preg_match('/(.*)\/[^\/]+$/', $relativefile, $reg);
		$ref = (isset($reg[1]) ? $reg[1] : '');
	}
	if ($modulepart == 'order') {
		preg_match('/(.*)\/[^\/]+$/', $relativefile, $reg);
		$ref = (isset($reg[1]) ? $reg[1] : '');
	}
	if ($modulepart == 'order_supplier') {
		preg_match('/(.*)\/[^\/]+$/', $relativefile, $reg);
		$ref = (isset($reg[1]) ? $reg[1] : '');
	}
	if ($modulepart == 'contract') {
		preg_match('/(.*)\/[^\/]+$/', $relativefile, $reg);
		$ref = (isset($reg[1]) ? $reg[1] : '');
	}
	if ($modulepart == 'product') {
		preg_match('/(.*)\/[^\/]+$/', $relativefile, $reg);
		$ref = (isset($reg[1]) ? $reg[1] : '');
	}
	if ($modulepart == 'tax') {
		preg_match('/(\d+)\/[^\/]+$/', $relativefile, $reg);
		$id = (isset($reg[1]) ? $reg[1] : '');
	}
	if ($modulepart == 'project') {
		preg_match('/(.*)\/[^\/]+$/', $relativefile, $reg);
		$ref = (isset($reg[1]) ? $reg[1] : '');
	}
	if ($modulepart == 'fichinter') {
		preg_match('/(.*)\/[^\/]+$/', $relativefile, $reg);
		$ref = (isset($reg[1]) ? $reg[1] : '');
	}
	if ($modulepart == 'user') {
		preg_match('/(.*)\/[^\/]+$/', $relativefile, $reg);
		$id = (isset($reg[1]) ? $reg[1] : '');
	}
	if ($modulepart == 'expensereport') {
		preg_match('/(.*)\/[^\/]+$/', $relativefile, $reg);
		$ref = (isset($reg[1]) ? $reg[1] : '');
	}
	if ($modulepart == 'holiday') {
		preg_match('/(.*)\/[^\/]+$/', $relativefile, $reg);
		$id = (isset($reg[1]) ? $reg[1] : '');
	}
	if ($modulepart == 'member') {
		preg_match('/(.*)\/[^\/]+$/', $relativefile, $reg);
		$id = (isset($reg[1]) ? $reg[1] : '');
	}
	if ($modulepart == 'actions') {
		preg_match('/(.*)\/[^\/]+$/', $relativefile, $reg);
		$id = (isset($reg[1]) ? $reg[1] : '');
	}
	if ($modulepart == 'bank') {
		preg_match('/(.*)\/[^\/]+$/', $relativefile, $reg);
		$ref = (isset($reg[1]) ? $reg[1] : '');
	}
	if ($modulepart == 'banque') {
		preg_match('/(.*)\/[^\/]+$/', $relativefile, $reg);
		$id = (isset($reg[1]) ? $reg[1] : '');
	}
	if ($modulepart == 'salaries') {
		preg_match('/(.*)\/[^\/]+$/', $relativefile, $reg);
		$id = (isset($reg[1]) ? $reg[1] : '');
	}

	if (!$id && !$ref) {
		//For unknown modulepart will call hook method
		$parameters = array (
			'modulepart' => $modulepart,
			'relativefile' => $relativefile,
			'id' => $id,
		);
		$object=null;
		$action=null;
		$reshook = $hookmanager->executeHooks('getObjectLink', $parameters, $object, $action);
		if ($reshook > 0) {
			$object_instance = $hookmanager->object_instance;
			$id = $hookmanager->object_id;
			$ref = $hookmanager->object_ref;
		}
	}


	if (!$id && !$ref) {
		return false;
	}

	static $cache_objects;

	$found = 0;
	if (!empty($cache_objects[$modulepart . '_' . $id . '_' . $ref])) {
		$found = 1;
	} else {
		if(!$object_fetched) {
			if ($id) {
				$result = $object_instance->fetch($id);
			} else {
				//fetchOneLike looks for objects with wildcards in its reference.
				//It is useful for those masks who get underscores instead of their actual symbols
				//fetchOneLike requires some info in the object. If it doesn't have it, then 0 is returned
				//that's why we look only look fetchOneLike when fetch returns 0
				if (!$result = $object_instance->fetch('', $ref)) {
					$result = $object_instance->fetchOneLike($ref);
				}
			}

			if ($result > 0) {  // Save object into a cache
				$found = 1;
				$cache_objects[$modulepart . '_' . $id . '_' . $ref] = clone $object_instance;
			}
			if ($result == 0) {
				$found = 1;
				$cache_objects[$modulepart . '_' . $id . '_' . $ref] = 'notfound';
			}
		} else {
			$cache_objects[$modulepart . '_' . $id . '_' . $ref] = clone $object_instance;
		}
	}

	if (!$found > 0 || !is_object($cache_objects[$modulepart . '_' . $id . '_' . $ref])) {
		return false;
	};

	if ($found > 0 && is_object($cache_objects[$modulepart . '_' . $id . '_' . $ref])) {
		return $cache_objects[$modulepart . '_' . $id . '_' . $ref]->getNomUrl(1, $link_option);
	} else {
		return false;
	}

}
