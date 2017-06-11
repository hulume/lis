<?php
namespace App\Services;
use App\Mail\PostLisFeedback;
use Cache;
use DB;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Mail;

class PostData {
	private $token;
	private $http;

	public function __construct(Client $http) {
		$this->http = $http;
		$this->token = $this->getToken();
	}
	/**
	 * 上传所有Lis数据
	 * @return collection
	 */
	public function all() {
		// 取出姓名如1701011类型的记录, 用于初始化时调用
		$query = DB::table('as_report')
			->select('rep_no', 'name', 'rep_date')
			->where('name', 'like', '[1-9]%')
			->get();
		return $this->postData($query);
	}
	public function daily() {
		$day = date('Y.m.d');
		$query = DB::table('as_report')
			->select('rep_no', 'name', 'rep_date')
			->where('rep_date', 'like', $day . '%')
			->get();
		// ->select("select rep_no, name, rep_date from as_report where rep_date like '$day%' ")->get();
		return $this->postData($query);
	}

	/**
	 * 过滤并上传数据,其中自动获取远程服务器Token(远端服务器约束IP地址为：221.215.162.38)
	 * @param  Collection $query lis中的初筛数据
	 * @return 远端服务器返回值（约定为字符串，成功存入多少条）
	 */
	private function postData($query) {
		$data = $query->map(function ($item, $key) {
			if (!empty($item)) {
				if (!empty(preg_match("/^[0-9]{7,11}$/", $item->name))) {
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
		Mail::to(env('ADMIN_EMAIL'))->send(new PostLisFeedback(['time' => date('Y-m-d H:i:s'), 'content' => $r->getBody()]));
	}

	private function getToken() {
		if (Cache::has('client_credentials_token')) {
			return Cache::get('client_credentials_token');
		}
		$response = $this->http->get(env('GET_TOKEN_URL'));
		// dd($response->getBody());
		$token = json_decode($response->getBody(), true)['access_token'];
		Cache::forever('client_credentials_token', $token);
		return $token;
	}
}