<?php

namespace App\Http\Controllers;

use Cache;
use DB;
use GuzzleHttp\Client;

class LisController extends Controller {

	private $token;
	private $http;

	public function __construct(Client $http) {
		$this->http = $http;
		// $this->token = $this->getToken();
	}

	public function index() {
		// $day = Carbon::yesterday()->format('Y.m.d');
		// $query = DB::select("select rep_no, name, rep_date from as_report where rep_date like '$day%' ");
		// return $query;

		// 取出姓名如1701011类型的记录, 用于初始化时调用
		$query = DB::table('as_report')
			->select('rep_no', 'name', 'rep_date')
			->where('name', 'like', '[1-9]%')
			->get();
		$data = $query->map(function ($item, $key) {
			if (!empty($item)) {
				if (!empty(preg_match("/\d{7,11}/", $item->name))) {
					return [
						'rep_no' => $item->rep_no,
						'rep_date' => $item->rep_date,
						'name' => $item->name,
						'result' => DB::table('as_repentry')
							->leftJoin('as_code_item', 'as_repentry.item_code', '=', 'as_code_item.item_code')
							->where('rep_no', '=', $item->rep_no)
							->select('as_repentry.result', 'as_code_item.item_name', 'as_repentry.normal')
							->get(),
						// 'result' => DB::select("select as_repentry.result, as_code_item.item_name, as_repentry.normal from as_repentry
						// inner join as_code_item on as_repentry.item_code = as_code_item.item_code
						// where rep_no = " . $item->rep_no)->get(),
					];
				}
			}
		})->reject(function ($item) {
			return empty($item);
		})->values()->all();
		$r = $this->http->post(env('API_URL'), [
			'headers' => [
				'Accept' => 'application/json',
				'Authorization' => 'Bearer ' . $this->token,
			],
			'json' => $data,
		]);
		return $r->getBody();
	}

	private function getToken() {
		if (Cache::has('client_credentials_token')) {
			return Cache::get('client_credentials_token');
		}
		$response = $this->http->get(env('GET_TOKEN_URL'));
		$token = json_decode($response->getBody(), true)['access_token'];
		Cache::forever('client_credentials_token', $token);
		return $token;
	}
}
