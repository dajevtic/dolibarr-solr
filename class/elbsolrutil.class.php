<?php

class ElbSolrUtil
{

	private $solr_server_url;
	private $solr_server_auth;
	private $error;
	public $response;
	public $responseHeader;
	public $highlighting;
	public $memory_limit = '128M';


	function __construct()
	{
		global $conf;
		$this->solr_server_url = $conf->global->ELBSOLR_SOLR_SERVER_URL;
		$this->solr_server_auth = $conf->global->ELBSOLR_SOLR_SERVER_AUTH;
	}

	function addToSearchIndex($ecmFiles)
	{

		global $langs, $user, $db;

		$this->error = null;
		$this->response = null;

		$target_url = $this->solr_server_url . "/update/extract";
		$file = $this->getFullFilePath($ecmFiles);
		if (!file_exists($file)) {
			$this->error = "File not found: " . $file;
			return false;
		}
		$post = array(
			"file" => new \CurlFile($file, mime_content_type($file))
		);
		$revision = null;
		$active = true;
		$filemapid = null;
		$fileUser = $user;
		if (!empty($ecmFiles->fk_user_c)) {
			$fileUser = new User($db);
			$fileUser->fetch_user($ecmFiles->fk_user_c);
		}
		$src_object_type = !empty($ecmFiles->src_object_type) ? $ecmFiles->src_object_type : GETPOST('object_type', 'alpha');
		$src_object_id = !empty($ecmFiles->src_object_id) ? $ecmFiles->src_object_id : GETPOST('object_id', 'int');
		if($ecmFiles->gen_or_uploaded == 'unknown') {
			$filename = basename($ecmFiles->fullpath_orig);
		} else {
			$filename = $ecmFiles->filename;
		}

		$params = array(
			"literal.id" => $ecmFiles->id,
			"commit" => "true",
			"commitWithin" => 1000,
			"uprefix" => "attr_",
			"fmap.content" => "attr_content",
			"literal.elb_fileid" => $ecmFiles->id,
			"literal.elb_name" => $filename,
			"literal.elb_description" => $ecmFiles->description,
			"literal.elb_revision" => $revision,
			"literal.elb_active" => $active,
			"literal.elb_type" => mime_content_type($file),
			"literal.elb_md5" => md5($file),
			"literal.elb_path" => $ecmFiles->filepath . DIRECTORY_SEPARATOR . $ecmFiles->filename,
			"literal.elb_filemapid" => $filemapid,
			"literal.elb_object_type" => $src_object_type,
			"literal.elb_object_id" => $src_object_id,
			"literal.elb_created_date" => $ecmFiles->date_c,
			"literal.elb_user" => $fileUser->getFullName($langs),
			"literal.elb_server" => $_SERVER['SERVER_NAME'],
			"wt" => "json"
		);

		$this->getAdditionalParams($ecmFiles, $params);

		$url_params = http_build_query($params, null, "&");
		$url_params = preg_replace('/%5B(?:[0-9]|[1-9][0-9]+)%5D=/', '=', $url_params);
		$target_url = $target_url . "?" . $url_params;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $target_url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		if (!empty($this->solr_server_auth)) {
			curl_setopt($ch, CURLOPT_USERPWD, $this->solr_server_auth);
		}
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		@curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
		$result = curl_exec($ch);
		$this->handleCurlResponse($ch, $result);
		curl_close($ch);
		return true;

	}

	private function getAdditionalParams($ecmFiles, &$params) {
		global $hookmanager;
		$parameters = array (
			'ecmFiles' => $ecmFiles,
		);
		$object=null;
		$action=null;
		$reshook = $hookmanager->executeHooks('getSolrIndexingAdditionalParams', $parameters, $object, $action);
		if ($reshook > 0) {
			$params = array_merge($params, $hookmanager->resArray['additionalParams']);
		}
	}

	private function handleCurlResponse($ch, $result)
	{
		//If curl can not be executed
		if ($result == false) {
			$err = curl_error($ch);
			$this->error = $err;
			return false;
		}

		//If curl is executed but response is not json, which can happen if there is some server error
		$result_json = json_decode($result, true);
		if ($result_json === false || $result_json === null || !is_array($result_json)) {
			$this->error = $result;
			return false;
		}

		//If Solr has some error status will be non zero
		if (!isset($result_json['responseHeader']['status']) || $result_json['responseHeader']['status'] <> 0) {
			if($result_json['error']['code']!=500) { //Highlighting not available: maxClauseCount is set to 1024
				$this->error = $result_json['error'];
				return false;
			}
		}

		$this->response = $result_json['response'];
		$this->responseHeader = $result_json['responseHeader'];
		$this->highlighting = @$result_json['highlighting'];
		return true;
	}

	function getFullFilePath($ecmFiles)
	{
		$file = $ecmFiles->filepath . DIRECTORY_SEPARATOR . $ecmFiles->filename;
		return DOL_DATA_ROOT . DIRECTORY_SEPARATOR . $file;
	}

	function getErrorMessage()
	{
		if (is_array($this->error) && isset($this->error['msg'])) {
			return $this->error['msg'];
		} else {
			return $this->error;
		}
	}

	function getStatus()
	{
		$target_url = $this->solr_server_url . "/admin/ping";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $target_url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		if (!empty($this->solr_server_auth)) {
			curl_setopt($ch, CURLOPT_USERPWD, $this->solr_server_auth);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);
		$res = $this->handleCurlResponse($ch, $result);
		curl_close($ch);

		$status = array();
		$status['success'] = $res;
		return $status;

	}

	function getNumberOfDocuments()
	{
		$target_url = $this->solr_server_url . "/select?q=*:*&rows=0";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $target_url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		if (!empty($this->solr_server_auth)) {
			curl_setopt($ch, CURLOPT_USERPWD, $this->solr_server_auth);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);
		$res = $this->handleCurlResponse($ch, $result);
		curl_close($ch);
		return $this->response['numFound'];
	}

	function clearAllIndexedDocuments()
	{
		$target_url = $this->solr_server_url . "/update?commit=true&wt=json";
		$post_data = '<delete><query>*:*</query></delete>';
		$post_headers = array(
			'Content-Type: text/xml'
		);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $target_url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $post_headers);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		if (!empty($this->solr_server_auth)) {
			curl_setopt($ch, CURLOPT_USERPWD, $this->solr_server_auth);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);
		$res = $this->handleCurlResponse($ch, $result);
		curl_close($ch);
		return $res;
	}

	function deleteDocumentById($id)
	{
		$target_url = $this->solr_server_url . "/update?commit=true&wt=json";
		$post_data = '<delete><query>id:'.$id.'</query></delete>';
		$post_headers = array(
			'Content-Type: text/xml'
		);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $target_url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $post_headers);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		if (!empty($this->solr_server_auth)) {
			curl_setopt($ch, CURLOPT_USERPWD, $this->solr_server_auth);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);
		$res = $this->handleCurlResponse($ch, $result);
		curl_close($ch);
		return $res;
	}

	function getIndexingStatus()
	{
		global $langs;
		$indexing_status_file = $this->getIndexingStatusFile();
		if (!file_exists($indexing_status_file)) {
			return $langs->trans('IndexingStatusNone');
		}
		$status = json_decode(file_get_contents($indexing_status_file), true);
		if ($status === false) {
			return $langs->trans('IndexingStatusErrorReading');
		}
		if (isset($status['pid']) && file_exists( "/proc/" . $status['pid'])) {
			$remain_time = gmdate("H:i:s", (($status['elapsed']) / $status['processed']) * ($status['count'] - $status['processed']));
			$s = $langs->trans('IndexingStatusRunning', $status['processed'], $status['count'], $remain_time, $this->convertBytes($status['memory']) . " / " . $this->memory_limit);
			$s.= ", PID: ".$status['pid'];
			return $s;
		}
		return $langs->trans('IndexingStatusFinished', $status['processed'], $status['errors'], dol_print_date($status['started'], 'dayhoursec'),
			convertSecondToTime($status['elapsed'], 'allhourmin'));

	}

	private function convertBytes($size)
	{
		$unit = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
		return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
	}

	public function getIndexingStatusFile() {
		$status_folder = DOL_DATA_ROOT . "/elbsolr/temp";
		if (!file_exists($status_folder)) {
			dol_mkdir($status_folder);
		}
		return $status_folder . "/indexing.json";
	}

	public function isIndexingInProgress()
	{
		$indexing_status_file = $this->getIndexingStatusFile();
		if (!file_exists($indexing_status_file)) {
			return false;
		}
		$status = json_decode(file_get_contents($indexing_status_file), true);
		if (empty($status)) {
			return false;
		}
		if (isset($status['pid']) && file_exists( "/proc/" . $status['pid'])) {
			return true;
		}
		return false;
	}

	public function isIndexingFinished()
	{
		$indexing_status_file = $this->getIndexingStatusFile();
		if (!file_exists($indexing_status_file)) {
			return false;
		}
		$status = json_decode(file_get_contents($indexing_status_file), true);
		if ($status === false) {
			return false;
		}
		if (!isset($status['pid']) || !file_exists( "/proc/" . $status['pid'])) {
			return true;
		}
		return false;
	}

	public function getIndexingScriptFile() {
		return realpath(dirname(__FILE__) . "/../scripts/solr_indexing.php");
	}

	public function getIndexingErrors() {
		$indexing_status_file = $this->getIndexingStatusFile();
		if (!file_exists($indexing_status_file)) {
			return false;
		}
		$status = json_decode(file_get_contents($indexing_status_file), true);
		if (empty($status)) {
			return false;
		}
		if(isset($status['error_files'])) {
			return $status['error_files'];
		}
		return false;
	}


	public function search($name='', $content='', $rev=null, $limit=50, $page=0, $sortfield=null, $sortorder=null) {

		global $hookmanager;

		$query_parts=array();

		if(empty($name)) $name='*';

		if(!empty($name)) {
			$query_parts[]="elb_name:".$name;
		}
		if(!empty($content)) {
			$query_parts[]="attr_content:".$content;
		}
		if(!empty($rev)) {
			$query_parts[]="elb_revision:".$rev;
		}

		$parameters = array ();
		$object=null;
		$action=null;
		$reshook = $hookmanager->executeHooks('solrExecuteAdditionalSearch', $parameters, $object, $action);
		if ($reshook > 0) {
			$query_parts = array_merge($query_parts, $hookmanager->resArray['query_parts']);
		}

		$query=implode(" AND ", $query_parts);

		$url=$this->solr_server_url."/select?indent=0&q=".urlencode($query)."&wt=json";
		$url.="&fl=elb_*,id";
		if(!empty($content)) {
			$url.="&hl=on&hl.fl=attr_content&hl.simple.pre=<strong>&hl.simple.post=</strong>&hl.requireFieldMatch=true";
		}
		$url.="&rows=".$limit;
		$url.="&start=".$page * $limit;
		if(!empty($sortfield)) {
			$sort_string = null;
			if(empty($sortorder)) {
				$sortorder="asc";
			}
			if($sortfield=='size') {
				$sort_string = "stream_size ".$sortorder;
			}
			if($sortfield=='file') {
				$sort_string = "elb_name ".$sortorder;
			}
			if($sortfield=='date') {
				$sort_string = "elb_created_date ".$sortorder;
			}
			if(!empty($sort_string)) {
				$url.="&sort=".urlencode($sort_string);
			}
		}
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		if (!empty($this->solr_server_auth)) {
			curl_setopt($ch, CURLOPT_USERPWD, $this->solr_server_auth);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);
		$res = $this->handleCurlResponse($ch, $result);
		curl_close($ch);
		return $res;
	}

}


