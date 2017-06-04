<?php
namespace App\Services;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;

class GrabData {
	public function all() {
		// 取出姓名如1701011类型的记录, 用于初始化时调用
		$query = DB::table('as_report')
			->select('rep_no', 'name', 'rep_date')
			->where('name', 'like', '[1-9]%')
			->get();
		return $this->postData($query);
	}

	private function postData($query) {
		$data = $query->map(function ($item, $key) {
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
				];
			}
		})->reject(function ($item) {
			return empty($item);
		})->values()->all();

		$client = new Client();
		$res = $client->post('http://jd.wemesh.cn/api/lis', [
			'headers' => [
				'Accept' => 'application/json',
			],
			'form_params' => [
				'grant_type' => 'password',
				'client_id' => 1,
				'client_secret' => 'kkSgxps3HzCeuManqknZJ3vW5mtMloBI7Y1Vpw3l',
				'username' => 'admin@stario.net',
				'password' => 'password',
			],
			'json' => $data,
			// 'auth' => ['user', 'pass'],
		]);
		return $res;
	}
}