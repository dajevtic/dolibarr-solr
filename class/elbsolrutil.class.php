<?php

class ElbSolrUtil
{

	private $solr_server_url;
	private $solr_server_auth;
	private $error;
	private $response;
	private $responseHeader;


	function __construct()
	{
		global $conf;
		$this->solr_server_url = $conf->global->ELBSOLR_SOLR_SERVER_URL;
		$this->solr_server_auth = $conf->global->ELBSOLR_SOLR_SERVER_AUTH;
	}

	function addToSearchIndex(EcmFiles $ecmFiles)
	{

		global $langs, $user;

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
		$tags = array();
		$fileUser = $user;
		if (!empty($ecmFiles->fk_user_c)) {
			$fileUser = new User($langs);
			$fileUser->fetch_user($ecmFiles->fk_user_c);
		}
		$src_object_type = GETPOST('elbsolr_object_type', 'alpha');
		$src_object_id = GETPOST('elbsolr_object_id', 'int');
		$params = array(
			"literal.id" => $ecmFiles->id,
			"commit" => "true",
			"commitWithin" => 1000,
			"uprefix" => "attr_",
			"fmap.content" => "attr_content",
			"literal.elb_fileid" => $ecmFiles->id,
			"literal.elb_name" => $ecmFiles->filename,
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
			"literal.elb_tag" => $tags,
			"literal.elb_server" => $_SERVER['SERVER_NAME'],
			"wt" => "json"
		);
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
			$this->error = $result_json['error'];
			return false;
		}

		$this->response = $result_json['response'];
		$this->responseHeader = $result_json['responseHeader'];
		return true;
	}

	function getFullFilePath(EcmFiles $ecmFiles)
	{
		$file = $ecmFiles->filepath . DIRECTORY_SEPARATOR . $ecmFiles->filename;
		return DOL_DATA_ROOT . DIRECTORY_SEPARATOR . $file;
	}

	function getErrorMessage()
	{
		if(is_array($this->error) && isset($this->error['msg'])) {
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

	function clearAllIndexedDocuments() {
		$target_url=$this->solr_server_url."/update?commit=true&wt=json";
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


}


