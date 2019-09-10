<?php

/*!
 *  Elberos AmoCRM API Client
 *
 *  (c) Copyright 2019 "Ildar Bikmamatov" <support@bayrell.org>
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *      https://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

namespace Elberos\Amo;

use Elberos\Amo\Helper;
use Elberos\Core\Struct;


class Client extends Struct
{
	
	protected $__auth = false;
	protected $__auth_fail = false;
	protected $__domain = "";
	protected $__login = "";
	protected $__api_key = "";
	protected $__cache_prefix = "";
	protected $__cache_timeout = 24*60*60;
	protected $__account_info_file = "";
	protected $__cookie_file = "";
	protected $__phone_id = "";
	protected $__email_id = "";
	protected $__users = null;
	protected $__custom_field = null;
	protected $__pipelines = null;
	
	
	/**
	 * Set cache dir
	 */
	public function setCacheDir($dir, $cookie_file = "client")
	{
		return $this->copy([
			"cookie_file" => $dir . "/amocrm." . $cookie_file . ".cookie",
			"account_info_file" => $dir . "/amocrm." . $cookie_file . ".data",
		]);
	}
	
	
	
	/**
	 * Returns true if is auth
	 */
	public function isAuth()
	{
		return $this->auth and !$this->auth_fail;
	}
	
	
	
	/**
	 * Returns User Agent
	 */
	public static function getUserAgent()
	{
		return 'AmoCRM-API-client/1.0';
	}
	
	
	
	/**
	 * Returns amocrm domain
	 */
	public function getDomain()
	{
		return $this->domain . ".amocrm.ru";
	}
	
	
	
	/**
	 * Returns AmoCRM API Auth Url
	 */
	public function getAuthUrl()
	{
		return "https://" . $this->getDomain() . '/private/api/auth.php?type=json';
	}
	
	
	
	/**
	 * Returns search url
	 */
	public function getSearchUrl($kind)
	{
		return "https://" . $this->getDomain() . '/api/v2/' . $kind;
	}
	
	
	
	/**
	 * Returns AmoCRM API Account Url
	 */
	public function getAccountUrl($fields = [])
	{
		$str = implode(",", $fields);
		return "https://" . $this->getDomain() . '/api/v2/account?with='.$str;
	}
	
	
	
	
	/**
	 * Send curl
	 */
	public function curl($url, $post = null, $headers = null)
	{
		var_dump($url);
		
		# Сохраняем дескриптор сеанса cURL
		$curl = curl_init();
		
		# Устанавливаем необходимые опции для сеанса cURL
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_USERAGENT, static::getUserAgent());
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_COOKIEFILE, $this->cookie_file); # PHP>5.3.6 dirname(__FILE__) -> __DIR__
		curl_setopt($curl, CURLOPT_COOKIEJAR, $this->cookie_file); # PHP>5.3.6 dirname(__FILE__) -> __DIR__
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		
		if ($post != null)
		{
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($post));
		}
		else
		{
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
		}
		
		if ($headers != null && count($headers) > 0)
		{
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		}
		
		# Инициируем запрос к API и сохраняем ответ в переменную
		$out = curl_exec($curl);
		
		# Получим HTTP-код ответа сервера
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		
		# Завершаем сеанс cURL
		curl_close($curl); 
		
		$response = null;
		$code = (int)$code;
		if ($code == 200 || $code == 204)
		{
			$response = @json_decode($out, true);
		}
		
		return [$out, $code, $response];
	}
	
	
	
	/**
	 * Авторизация в AmoCRM
	 * https://www.amocrm.ru/developers/content/api/auth
	 */
	public function auth()
	{
		if ($this->domain == "" || $this->login == "" || $this->api_key == "")
		{
			return $this->copy([ "auth_fail"=>true, "auth"=>false ]);
		}
		if ($this->auth or $this->auth_fail)
		{
			return $this;
		}
		
		# Подготовим запрос
		$url = $this->getAuthUrl();
		$post = array(
			'USER_LOGIN' => $this->login,
			'USER_HASH' => $this->api_key
		);
		
		list($out, $code, $response) = $this->curl($url, $post);
		if ($response != null)
		{
			$response = $response['response'];
			if (isset($response['auth']))
			{
				return $this->copy([ "auth_fail"=>false, "auth"=>true, ]);
			}
		}
		
		return $this->copy([ "auth_fail"=>true, "auth"=>false ]);
	}
	
	
	
	/**
	 * Возвращает данные AmoCRM
	 */
	public function getAccountInfo()
	{
		$url = $this->getAccountUrl(['custom_fields', 'users', 'pipelines']);
		list($out, $code, $response) = $this->curl($url);
		
		if (!$response || !isset($response['_embedded']))
		{
			throw new \Exception('Data failed');
			return false;
		}
		
		$data = $response['_embedded'];
		$users = isset($data['users']) ? $data['users'] : [];
		$custom_fields = isset($data['custom_fields']) ? $data['custom_fields'] : [];
		$pipelines = isset($data['pipelines']) ? $data['pipelines'] : [];
		
		return [$users, $custom_fields, $pipelines];
	}
	
	
	
	/**
	 * Возвращает данные AmoCRM
	 */
	public function getAccountInfoCache()
	{
		$amocrm = $this;
		$success = false;
		$users = null;
		$custom_field = null;
		$pipelines = null;
		$update = false;
		
		list($users, $custom_fields, $pipelines, $success) = 
			\Elberos\Amo\Helper::loadAccountInfo($this->account_info_file, $this->cache_timeout);
		
		
		if (!$success)
		{
			$amocrm = $amocrm->auth();
			if ($amocrm->isAuth())
			{
				list($users, $custom_fields, $pipelines) = $this->getAccountInfo();
				\Elberos\Amo\Helper::saveAccountInfo($this->account_info_file, $users, $custom_fields, $pipelines);
				$update = true;
				$success = true;
			}
		}
		
		
		if ($success)
		{
			$amocrm = $amocrm->copy([
				"users"=>$users,
				"custom_field"=>$custom_field,
				"pipelines"=>$pipelines,
			]);
		}
		
		return [$amocrm, $users, $custom_fields, $pipelines, $success, $update];
	}
	
	
	const SEARCH_CONTACT = "contacts";
	const SEARCH_COMPANY = "companies";
	const SEARCH_LEADS = "leads";
	const SEARCH_NOTES = "notes";
	
	
	/**
     * @param string           $kind
     * @param string           $query
     * @param integer          $limit
     * @param integer          $offset
     * @param array|string|int $responsibleUsers
     * @param DateTime|null    $modifiedSince
     * @param bool             $isRaw
     * @param int|string|null  $pipelineIdOrName
     * @param array            $statuses
	 * @return array
     */
    public function search($params)
    {
		$kind = isset($params['kind']) ? $params['kind'] : "";
		$type = isset($params['type']) ? $params['type'] : "";
		$id = isset($params['id']) ? (int)$params['id'] : 0;
		$element_id = isset($params['element_id']) ? (int)$params['element_id'] : null;
		$query = isset($params['query']) ? $params['query'] : "";
		$offset = isset($params['offset']) ? (int)$params['offset'] : null;
		$limit = isset($params['limit']) ? (int)$params['limit'] : null;
		$modified_since = isset($params['modified_since']) ? (int)$params['modified_since'] : null;
		if ($limit > 500) $limit = 500;
		
		$url = $this->getSearchUrl($kind);
		
		$args = [];
		if ($id != 0) $args[] = "id=" . urlencode($id);
		if ($type) $args[] = "type=" . urlencode($type);
		if ($element_id) $args[] = "element_id=" . urlencode($element_id);
		if ($query) $args[] = "query=" . urlencode($query);
		if ($offset) $args[] = "limit_offset=" . urlencode($offset);
		if ($limit) $args[] = "limit_rows=" . urlencode($limit);
		
		if (count($args) > 0)
		{
			$url .= "?" . implode("&", $args);
		}
		
		$headers = [];
		if ($modified_since !== null)
		{
			$dt = new \DateTime();
			$dt->setTimestamp($modified_since);
			$headers[] = 'IF-MODIFIED-SINCE: ' . $dt->format(\DateTime::RFC1123);
		}
		
		list($out, $code, $response) = $this->curl($url, null, $headers);
		if ($response === null)
		{
			if ($code != 200 and $code != 204)
			{
				throw new \Exception("Amocrm search response error code " . $code);
			}
			return [];
		}
		
		return $response['_embedded']['items'];
    }
	
	
	
	/**
	 * Returns deal
	 */
	public function getDeal($deal_id)
	{
		$items = $this->search([
			'kind'=>static::SEARCH_LEADS,
			'id'=>$deal_id,
		]);
		if ($items == null) return null;
		return array_shift($items);
	}
	
	
	
	/**
	 * Returns contact
	 */
	public function getContact($contact_id)
	{
		$items = $this->search([
			'kind'=>static::SEARCH_CONTACT,
			'id'=>$contact_id,
		]);
		if ($items == null) return null;
		return array_shift($items);
	}
	
	
	
	/**
	 * Returns contact by query
	 */
	public function findContacts($query)
	{
		$items = $this->search([
			'kind'=>static::SEARCH_CONTACT,
			'query'=>$query,
		]);
		if ($items == null) return [];
		return $items;
	}
	
	
	
	/**
	 * Find client
	 */
	public function findClient($client)
	{
		$name = isset($client['name']) ? $client['name'] : '';
		$phone = isset($client['phone']) ? $client['phone'] : '';
		$email = isset($client['email']) ? $client['email'] : '';
		
		$result = [];
		if ($phone != "") $result = array_merge($result, $this->findContacts($phone));
		if ($email != "") $result = array_merge($result, $this->findContacts($email));
		
		$candidate = null;
		$candidate_grade = 0;
		foreach ($result as $item)
		{
			$item = Helper::parseContactsFields($item);
			$grade = Helper::clientCalcGrade($item, $client);
			if ($grade > $candidate_grade)
			{
				$candidate_grade = $grade;
				$candidate = $item;
			}
			
		}
		
		return [$candidate, $candidate_grade];
	}
	
	
	
	/**
	 * Create client
	 */
	public function createClient($client)
	{
		$name = isset($client['name']) ? $client['name'] : '';
		$tags = isset($client['tags']) ? $client['tags'] : '';
		$phone = isset($client['phone']) ? $client['phone'] : '';
		$email = isset($client['email']) ? $client['email'] : '';
		$manager_id = isset($client['manager_id']) ? $client['manager_id'] : 0;
		
		if ($manager_id == 0)
		{
			throw new \Exception("Create client error. Manager id is null");
		}
		
		$contact = [
			'name' => $name,
			'tags' => $tags,
			'created_at' => time(),
			'custom_fields' => [],
			'responsible_user_id' => $manager_id,
		];
		
		if ($phone != "" and $this->phone_id != ""){
			$contact['custom_fields'][] = [
				'id' => $this->phone_id,
				'values' => array(
					array(
						'value' => $phone,
						'enum' => "MOB"
					)
				),
			];
		}
		
		if ($email != ""and $this->email_id != ""){
			$contact['custom_fields'][] = [
				'id' => $this->email_id,
				'values' => array(
					array(
						'value' => $email,
						'enum' => "WORK"
					)
				),
			];
		}
		
		// Send request
		$url = $this->getSearchUrl('contacts');
		list($out, $code, $response) = $this->curl($url, ['add'=>[$contact]]);
		if ($response)
		{
			$Items = $response['_embedded']['items'];
			$Item = array_shift($Items);
			if ($Item){
				return $Item['id'];
			}
		}
		else
		{
			throw new \Exception("Create client error. Response error " . $code);
			//var_xdump($code);
			//var_dump($out);
		}
		
		return 0;
	}
	
	
	
	/**
	 * Create client
	 */
	public function createDeal($deal)
	{
		$deal_name = isset($deal['deal_name']) ? $deal['deal_name'] : "Заказ";
		$contact_id = isset($deal['contact_id']) ? $deal['contact_id'] : 0;
		$pipeline_id = isset($deal['pipeline_id']) ? $deal['pipeline_id'] : 0;
		$status_id = isset($deal['status_id']) ? $deal['status_id'] : 0;
		$manager_id = isset($deal['manager_id']) ? $deal['manager_id'] : 0;
		$data = isset($deal['data']) ? $deal['data'] : [];
		
		if ($contact_id == 0)
		{
			throw new \Exception("Create deal error. Contact id is null");
		}
		
		if ($pipeline_id == 0)
		{
			throw new \Exception("Create deal error. Pipeline id is null");
		}
		
		if ($status_id == 0)
		{
			throw new \Exception("Create deal error. Status id is null");
		}
		
		if ($manager_id == 0)
		{
			throw new \Exception("Create deal error. Manager id is null");
		}
		
		
		$send = [
			'name' => $deal_name,
			'created_at' => time(),
			'sale' => 0,
			'pipeline_id' => $pipeline_id,
			'status_id' => $status_id,
			'responsible_user_id' => $manager_id,
			'contacts_id' => [
				$contact_id,
			],
			'custom_fields' => [],
		];
		
		$url = $this->getSearchUrl('leads');
		list($out, $code, $response) = $this->curl($url, ['add'=>[$send]]);
		if ($response)
		{
			$response = isset($response['_embedded']) ? $response['_embedded'] : null;
			if ($response)
			{
				$items = isset($response['items']) ? $response['items'] : null;
				if ($items && count($items) > 0)
				{
					$item = $items[0];
					$deal_id = isset($item['id']) ? $item['id'] : null;
					return $deal_id;
				}
			}
		}
		else
		{
			throw new \Exception("Create deal error. Response error " . $code);
			//var_dump( @json_decode($out, true) );
		}
		
		return 0;
	}
	
}
