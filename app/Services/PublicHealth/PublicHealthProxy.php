<?php

namespace App\Services\PublicHealth;
use App\Mail\PublicHealthPostFeedback;
use App\Services\GetPostToken;
use Goutte;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Mail;

/**
 * 抓取公卫系统的蜘蛛抽象类
 */
abstract class PublicHealthProxy {
	protected $client;
	protected $cookies;
	protected $params;
	protected $pages;
	protected $currentPage = 1;
	protected $crawler;

	public function __construct() {
		$this->params = $this->getParams();
	}
	/**
	 * 由子类指定不同的url参数
	 * @return $this->params
	 */
	abstract public function getParams();
	/**
	 * 获取某时间段的数据
	 * @param  string $start 开始时间
	 * @param  string $end   结束时间
	 */
	public function period($start) {
		return $this->scrape($start);
	}

	public function show() {
		$this->login();
		// $crawler = Goutte::request('POST', $this->url('2017-01-01'));
		// dd($crawler->html());
		return $this->fetchArchive(request('fn'), request('id'));
	}

	/**
	 * 依次抓取患者病例
	 * 第一步：根据传入参数获取到index(第一页)页面
	 * 第二步：根据index页面依次进入病例页面获取到病例
	 * 第三步：将病例入库
	 * 第四步：重复第二步，直至本页结束
	 * @param  [type] $start
	 * @param  string $end   [description]
	 * @return [type]        [description]
	 */
	private function scrape($start, $end = '') {
		$this->login();
		$crawler = Goutte::request('POST', $this->url($start, $end));
		// dd($crawler->html());
		$pages = $crawler->filter('#all')->attr('value');
		$count = 0; // 记录条数
		for ($i = 1; $i <= $pages; $i++) {
			$crawler = Goutte::request('POST', $this->url($start, $end, $i));
			echo '共有' . $pages . '页,正在抓取第' . $i . "页\n";
			$crawler->filter('.QueryTable tr')->siblings()->each(function ($tr) use (&$count, $crawler) {
				$link = $crawler->selectLink($tr->filter('td')->eq(1)->text())->link()->getUri();
				preg_match('/\?dGrdabh=(\d+).*id=(\d+)/', $link, $matches);
				// 正则匹配出的后两组，一个是患者的公卫档案号，一个是该患者在当前页面中存在的病例记录号
				$final = $this->fetchArchive($matches[1], $matches[2]);
				if ($final->success) {
					echo $final->data . "\n";
					$count++;
				} else {
					echo $final->data . "\n";
					Mail::to(env('ADMIN_EMAIL'))->send(new PublicHealthPostFeedback(['time' => date('Y-m-d H:i:s'), 'content' => $final->data]));
					exit;
				}
			});
		}
		Mail::to(env('ADMIN_EMAIL'))->send(new PublicHealthPostFeedback(['time' => date('Y-m-d H:i:s'), 'content' => '成功写入' . $count . '条记录']));
		unset($count);
	}

	/**
	 * 抓取病例
	 * @param  string $fn 患者档案号
	 * @param  string $id 病例号
	 * @return [type]     [description]
	 */
	private function fetchArchive($fn, $id) {
		$url = config('publicHealth.rootUrl') . 'health/selecttjlistforId.action?dGrdabh=' . $fn . '&id=' . $id;
		// 基本资料抓取
		$attempt = 0;
		$base = null;
		$crawler = Goutte::request('GET', $url);
		// dd($crawler->html());
		do {
			try
			{
				$crawler->filter('#table2')->text();
			} catch (\InvalidArgumentException $e) {
				$attempt++;
				print_r("抓取失败，进行第" . $attempt . "次重试\n");
				print_r($crawler->html());
				sleep(2);
				$this->login();
				continue;
			}

			break;

		} while ($attempt < 10);

		$base = $crawler->filter('#table2');

		$baseInfo['name'] = $this->clean($base->filter('tr td')->eq(5)->text());
		$baseInfo['gender'] = $this->clean($base->filter('tr td')->eq(7)->text());
		$baseInfo['identify'] = $this->clean($base->filter('tr td')->eq(9)->text());
		$baseInfo['birthday'] = $this->clean($base->filter('tr td')->eq(11)->text());
		$baseInfo['phone'] = $this->clean($base->filter('tr td')->eq(13)->text());
		$baseInfo['village'] = preg_split('/\s+/', $this->clean($base->filter('tr td')->eq(15)->text()))[4];
		/**
		 * 弃用部分
		 */
		// $base = $crawler->filter('#table2')->text();
		// $baseMapper = [
		// 	'name' => '姓名',
		// 	'gender' => '性别',
		// 	'identify' => '身份证号',
		// 	'birthday' => '出生日期',
		// 	'phone' => '联系电话',
		// 	'village' => '居住地址',
		// ];
		// $baseInfo = [];
		// foreach ($baseMapper as $key => $mapper) {
		// 	if ($key === 'village') {
		// 		dd($base);
		// 		preg_match_all('/(办事处|马店镇).*?\\r\\n(\s+.*)/', $base, $match);
		// 		$match = array_last(explode(' ', array_flatten($match)[0]));
		// 		$baseInfo['village'] = preg_replace('/胶东街道办事处|马店镇/', '', trim($match));
		// 		// 去除有村的字符，但如果只有两个字则忽略（防止"大村"变成"大"）
		// 		if (mb_strlen($baseInfo['village'], 'UTF-8') > 2) {
		// 			$baseInfo['village'] = preg_replace('/村/', '', $baseInfo['village']);
		// 		}
		// 	} else {
		// 		preg_match_all('/' . $mapper . '(.*)?\\r\\n(\s+.*)/', $base, $match);
		// 		$baseInfo[$key] = preg_replace('/\\r|\\t/', '', array_last($match))[0];
		// 	}
		// }
		// 当前病例
		$archiveInfo = $crawler->filter('#tableAllAreaWord')->text();
		// dd($archiveInfo);
		// 其中一些项目为老年人特有
		$archiveMapper = [
			'createtime' => '体检日期',
			'doctor' => '责任医生',
			'temperature' => '体温',
			'pulse' => '脉率',
			'breathe' => '呼吸频率',
			'blood_pressure_left' => '左侧',
			'blood_pressure_right' => '右侧',
			'height' => '身高',
			'weight' => '体重',
			'waistline' => '腰围',
			'bmi' => '体重指数',
			'self_assessment' => '自我评估',
			'beat' => '心率',
			'hgb' => '血红蛋白',
			'wbc' => '白细胞',
			'plt' => '血小板',
			'fbg' => '空腹血糖',
			'ecg' => '心电图',
			'alt' => '血清谷丙转氨酶',
			'ast' => '血清谷草转氨酶',
			'stb' => '总胆红素',
			'scr' => '血清肌酐',
			'bun' => '血尿素氮',
			'rut' => '尿常规',
			'ua' => '尿酸',
			'tcho' => '总胆固醇',
			'trig' => '甘油三酯',
			'ldl' => '血清低密度脂蛋白胆固醇',
			'hdl' => '血清高密度脂蛋白胆固醇',
			'bray' => 'B 超',
			'cn_medicine' => '平和质',
			'brain_sickness' => '脑血管疾病',
			'kidney_sickness' => '肾脏疾病',
			'heart_sickness' => '心脏疾病',
			'vessel_sickness' => '血管疾病',
			'eye_sickness' => '眼部疾病',
			'neural_sickness' => '神经系统疾病',
			// 'other_sickness' => '其他系统疾病',
			'comment' => '健康评价',
			'control' => '健康指导',
		];
		$archive = [];
		foreach ($archiveMapper as $key => $keyword) {
			if ($keyword == '健康评价') {
				preg_match_all('/异常1(.*)?(\s+.*){3}/', $archiveInfo, $match);
				$archive[$key] = trim(preg_replace('/\s+/', '', array_first($match[0])));
			}
			//  else if ($keyword == '其他系统疾病') {
			// 	if ($this->pickData('其他系统疾病', $archiveInfo) == '未发现') {
			// 		$archive[$key] = '未发现';
			// 	} else {
			// 		preg_match_all('/(其他系统疾病).*?\\r\\n(\s+.*){2}/', $archiveInfo, $match);
			// 		preg_match_all('/[^\\t]+\\r/', array_last($match[0]), $result);
			// 		$archive[$key] = trim(preg_replace('/\\r/', '', array_last($result[0])));
			// 	}

			// }
			else {
				$archive[$key] = $this->pickData($keyword, $archiveInfo);
			}
		}
		$final['baseInfo'] = $baseInfo;
		$final['baseInfo']['fn'] = $fn;
		$final['archive'] = $archive;
		$final['archive']['pub_id'] = $id;
		$final['abnormal'] = DetectSickness::handle($final['archive']);
		return $this->persistData($final);
	}

	private function persistData($result) {
		$http = new Client;
		$response = $http->post(env('PUBLIC_UPLOAD_URL'), [
			'headers' => [
				'Accept' => 'application/json',
				'Authorization' => 'Bearer ' . GetPostToken::get(),
			],
			'json' => $result,
		]);
		// sleep(1); //休息1秒，API 限制1秒2条
		return json_decode($response->getBody());
	}

	private function url($startDate, $endDate = '', $page = 1) {
		// 从Cookie中的当前用户登录名截取前12位作为机构编码
		$unitCode = substr($this->cookies[1]->getValue('zljyLoginname'), 0, 12);
		return config('publicHealth.rootUrl') . 'health/healthQuery.action?page.currentPage=' . $page . '&dqjg=' . $unitCode . '&crestarttime=' . $startDate . '&creendtime=' . $endDate . '&' . $this->params;
	}

	private function pickData($keyword, $string) {
		preg_match_all('/' . $keyword . '(.*)?\\r\\n(\s+.*)/', $string, $match);
		return trim(preg_replace('/\s+/', '', array_last($match))[0]);
	}

	/**
	 * 模拟登陆
	 * @return $this->client
	 */
	private function login() {
		$url = config('publicHealth.rootUrl') . 'login.action';
		$crawler = Goutte::request('GET', $url);
		$form = $crawler->filter('#loginForm')->form();
		$crawler = Goutte::submit($form, array('loginname' => config('publicHealth.username'), 'password' => config('publicHealth.password')));
		$this->cookies = Goutte::getCookieJar()->all();
		Goutte::getCookieJar()->updateFromSetCookie($this->cookies);
	}

	private function clean($string) {
		return trim(preg_replace('/\s\s+/', ' ', $string));
	}
}