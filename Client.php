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

use Elberos\Core\Struct;


class Client extends Struct
{
	
	protected $__auth = false;
	protected $__auth_fail = false;
	protected $__domain = "";
	protected $__login = "";
	protected $__api_key = "";
	protected $__account_info_file = "";
	protected $__cookie_file = "";
	protected $__phone_id = "";
	protected $__email_id = "";
	protected $__utm_source_id = "";
	protected $__utm_medium_id = "";
	protected $__utm_campaign_id = "";
	protected $__utm_content_id = "";
	protected $__utm_term_id = "";
	protected $__utm_host_id = "";
	
	
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
	public function getSearchUrl($type)
	{
		return "https://" . $this->getDomain() . '/api/v2/' . $type;
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
		
		list($users, $custom_fields, $pipelines, $success) = 
			\Elberos\Amo\Helper::loadAccountInfo($this->account_info_file);
		
		
		if (!$success)
		{
			$amocrm = $amocrm->auth();
			if ($amocrm->isAuth())
			{
				list($users, $custom_fields, $pipelines) = $this->getAccountInfo();
				\Elberos\Amo\Helper::saveAccountInfo($this->account_info_file, $users, $custom_fields, $pipelines);
				$success = true;
			}
		}
		
		
		return [$amocrm, $users, $custom_fields, $pipelines, $success];
	}
	
	
	const SEARCH_CONTACT = "contacts";
	const SEARCH_COMPANY = "companies";
	const SEARCH_LEADS = "leads";
	
	
	/**
     * @param string           $type
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
		$type = isset($params['type']) ? $params['type'] : "";
		$id = isset($params['id']) ? (int)$params['id'] : 0;
		$query = isset($params['query']) ? $params['query'] : "";
		$offset = isset($params['offset']) ? (int)$params['offset'] : 0;
		$limit = isset($params['limit']) ? (int)$params['limit'] : 0;
		$modified_since = isset($params['modified_since']) ? (int)$params['modified_since'] : null;
		if ($limit > 500) $limit = 500;
		
		$url = $this->getSearchUrl($type);
		
		$args = [];
		if ($id != 0) $args[] = "id=" . urlencode($id);
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
			$headers[] = 'IF-MODIFIED-SINCE: ' . $modified_since->format(DateTime::RFC1123);
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
			'type'=>static::SEARCH_LEADS,
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
			'type'=>static::SEARCH_CONTACT,
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
			'type'=>static::SEARCH_CONTACT,
			'query'=>$query,
		]);
		if ($items == null) return [];
		return $items;
	}
	
	
	
	/**
	 * Returns field value
	 */
	public function getItemFieldValue($item, $id)
	{
		$result = [];
		$custom_fields = isset($item['custom_fields']) ? $item['custom_fields'] : [];
		foreach ($custom_fields as $field)
		{
			$field_id = isset($field['id']) ? $field['id'] : 0;
			$values = isset($field['values']) ? $field['values'] : [];
			if ($field_id == $id)
			{
				foreach ($values as $item)
				{
					$value = isset($item['value']) ? $item['value'] : '';
					if ($value != "")
					{
						$result[] = $value;
					}
				}
				break;
			}
		}
		return $result;
	}
	
	
	
	/**
	 * Returns contact by query
	 */
	public function parseContactsFields($item)
	{
		$result = [
			'item' => $item,
		];
		
		$result['id'] = isset($item['id']) ? $item['id'] : '';
		$result['name'] = isset($item['name']) ? $item['name'] : '';
		$result['phones'] = $this->getItemFieldValue($item, $this->phone_id);
		$result['emails'] = $this->getItemFieldValue($item, $this->email_id);
		
		return $result;
	}
	
	
	
	/**
	 * Получение оценки совпадения карточек
	 * $item - контакт из амосрм
	 * $client - данные клиента, которые нужно найти
	 */
	public function clientCalcGrade($item, $client)
	{
		$grade = 0;
		
		$client_name = mb_trim(isset($client['name']) ? $client['name'] : '');
		$client_phone = mb_trim(isset($client['phone']) ? $client['phone'] : '');
		$client_email = mb_trim(isset($client['email']) ? $client['email'] : '');
		
		$item_name = mb_trim(isset($item['name']) ? $item['name'] : '');
		$item_phones = isset($item['phones']) ? $item['phones'] : [];
		$item_emails = isset($item['emails']) ? $item['emails'] : [];
		
		$client_name = mb_strtolower($client_name);
		$client_email = mb_strtolower($client_email);
		$item_name = mb_strtolower($item_name);
		
		# Поиск по имени
		if ($client_name == $item_name && $item_name != "") $grade += 2;
		else if ($item_name == "" || $client_name == "") {}
		else
		{
			if ( (strpos($item_name, $client_name) !== false || strpos($client_name, $item_name) !== false) &&
				mb_strlen($item_name) > 2 && mb_strlen($client_name) > 2
			)
				$grade += 1;
		}
		
		# Поиск по телефону
		$find = false;
		$client_phone = preg_replace("/[^0-9]/", '', $client_phone);
		foreach ($item_phones as $val)
		{
			$val = preg_replace("/[^0-9]/", '', $val);
			if ($client_phone == $val && $val != "")
			{
				$grade += 7;
				$find = true;
				break;
			}
		}
		if (!$find && $client_phone != "")
		{
			$grade = -100;
		}
		
		# Поиск по email
		$find = false;
		foreach ($item_emails as $val)
		{
			$val =  mb_strtolower(mb_trim($val));
			if ($client_email == $val && $val != "")
			{
				$grade += 4;
				$find = true;
				break;
			}
		}
		if (!$find && $client_email != "")
		{
			$grade = -100;
		}
		
		return $grade;
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
			$item = $this->parseContactsFields($item);
			$grade = $this->clientCalcGrade($item, $client);
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
		$phone = isset($client['phone']) ? $client['phone'] : '';
		$email = isset($client['email']) ? $client['email'] : '';
		$manager_id = isset($client['manager_id']) ? $client['manager_id'] : 0;
		
		if ($manager_id == 0)
		{
			throw new \Exception("Create client error. Manager id is null");
		}
		
		
		$contact = [
			'name' => $name,
			'tags' => "клиент с сайта",
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
		
		/* Utm Source */
		if (isset($data['utm_source']) and $this->utm_source_id != ""){
			$send['custom_fields'][] = [
				'id' => $this->utm_source_id,
				'values' => [
					[ 'value' => $data['utm_source'] ],
				],
			];
		}
		
		/* Utm Medium */
		if (isset($data['utm_medium']) and $this->utm_medium_id != ""){
			$send['custom_fields'][] = [
				'id' => $this->utm_medium_id,
				'values' => [
					[ 'value' => $data['utm_medium'] ],
				],
			];
		}
		
		/* Utm Campaign */
		if (isset($data['utm_campaign']) and $this->utm_campaign_id != ""){
			$send['custom_fields'][] = [
				'id' => $this->utm_campaign_id,
				'values' => [
					[ 'value' => $data['utm_campaign'] ],
				],
			];
		}
		
		/* Utm Content */
		if (isset($data['utm_content']) and $this->utm_content_id != ""){
			$send['custom_fields'][] = [
				'id' => $this->utm_content_id,
				'values' => [
					[ 'value' => $data['utm_content'] ],
				],
			];
		}
		
		/* Utm Term */
		if (isset($data['utm_term']) and $this->utm_term_id != ""){
			$send['custom_fields'][] = [
				'id' => $this->utm_term_id,
				'values' => [
					[ 'value' => $data['utm_term'] ],
				],
			];
		}
		
		/* Utm Host */
		if (isset($data['utm_host']) and $this->utm_host_id != ""){
			$send['custom_fields'][] = [
				'id' => $this->utm_host_id,
				'values' => [
					[ 'value' => $data['utm_host'] ],
				],
			];
		}
		
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


/**
 * Trim UTF-8 string
 */
function mb_trim($name)
{
	if ($name == null) return "";
	$name = preg_replace('/^[\x00-\x1F\x7F\s]+/u', '', $name);
	$name = preg_replace('/[\x00-\x1F\x7F\s]+$/u', '', $name); 
	return $name;
}