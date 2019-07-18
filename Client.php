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
	
	
	
	/**
	 * Set cache dir
	 */
	public function setCacheDir($dir)
	{
		return $this->copy([
			"cookie_file" => $dir . "/amocrm.cookie",
			"account_info_file" => $dir . "/amocrm.data",
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
		if ($id != 0) $args[] = "id=" . $id;
		if ($query) $args[] = "query=" . $query;
		if ($offset) $args[] = "limit_offset=" . $offset;
		if ($limit) $args[] = "limit_rows=" . $limit;
		
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
	
}