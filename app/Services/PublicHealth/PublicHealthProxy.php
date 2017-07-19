<?php

namespace App\Services\PublicHealth;
use Goutte;

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
	 * 获取从2017至今全部的数据
	 */
	public function all() {
		$start = '2017-01-01';
		return $this->scrape($start);
		// $this->postArchives();
	}
	/**
	 * 获取某时间段的数据
	 * @param  string $start 开始时间
	 * @param  string $end   结束时间
	 */
	public function period($start) {
		return $this->scrape($start);
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
	private function scrape() {
		$this->login();
		$crawler = Goutte::request('POST', $this->url('2017-01-01'));
		$pages = $crawler->filter('#all')->attr('value');
		for ($i = 0; $i < $pages + 1; $i++) {
			if ($i != 0) {
				$crawler = Goutte::request('POST', $this->url('2017-01-01', '', $i));
				echo '第' . $i . '页抓取完毕，共有：' . $pages . '页' . "\n";
			}
			$crawler->filter('.QueryTable tr')->siblings()->each(function ($tr) use ($i, $crawler) {
				$link = $crawler->selectLink($tr->filter('td')->eq(1)->text())->link()->getUri();
				preg_match('/\?dGrdabh=(\d+).*id=(\d+)/', $link, $matches);
				// 正则匹配出的后两组，一个是患者的公卫档案号，一个是该患者在当前页面中存在的病例记录号
				$this->fetchArchive($matches[1], $matches[2]);
			});
		}
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

		} while ($attempt < 29);
		$base = $crawler->filter('#table2')->text();
		$baseMapper = [
			'name' => '姓名',
			'gender' => '性别',
			'identify' => '身份证号',
			'birthday' => '出生日期',
			'phone' => '联系电话',
			'village' => '居住地址',
		];
		$baseInfo = [];
		foreach ($baseMapper as $key => $mapper) {
			if ($key === 'village') {
				// preg_match_all('/青岛市\s+.*/', $base, $match);
				preg_match_all('/(办事处|镇).*?\\r\\n(\s+.*)/', $base, $match);
				$match = array_last(explode(' ', array_flatten($match)[0]));
				$baseInfo[$key] = trim($match);
			} else {
				preg_match_all('/' . $mapper . '(.*)?\\r\\n(\s+.*)/', $base, $match);
				$baseInfo[$key] = preg_replace('/\\r|\\t/', '', array_last($match))[0];
			}
		}
		// 当前病例
		// if ( $crawler->filter('#tableAllAreaWord')->count() <= 0) {
		// 	return;
		// }
		$archiveInfo = $crawler->filter('#tableAllAreaWord')->text();
		// dd($archiveInfo);
		// 其中一些项目为老年人特有
		$archiveMapper = [
			'check_date' => '体检日期',
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
			'ua' => '尿酸',
			'tcho' => '总胆固醇',
			'trig' => '甘油三酯',
			'ldl' => '血清低密度脂蛋白胆固醇',
			'hdl' => '血清高密度脂蛋白胆固醇',
			'b_ray' => 'B 超',
			'brain_sickness' => '脑血管疾病',
			'kidney_sickness' => '肾脏疾病',
			'heart_sickness' => '心脏疾病',
			'vessel_sickness' => '血管疾病',
			'eye_sickness' => '眼部疾病',
			'neural_sickness' => '神经系统疾病',
			'other_sickness' => '其他系统疾病',
			'comment' => '健康评价',
			'control' => '健康指导',
		];
		$archive = [];
		foreach ($archiveMapper as $key => $value) {
			if ($value == '健康评价') {
				preg_match_all('/异常1(.*)?(\s+.*){3}/', $archiveInfo, $match);
				$archive[$key] = trim(preg_replace('/\s+/', '', array_first($match[0])));
			} else {
				preg_match_all('/' . $value . '(.*)?\\r\\n(\s+.*)/', $archiveInfo, $match);
				$archive[$key] = trim(preg_replace('/\s+/', '', array_last($match))[0]);
			}
		}
		$result['baseInfo'] = $baseInfo;
		$result['baseInfo']['fn'] = $fn;
		$result['archive'] = $archive;
		dd($result);
		// return $this->persistData($result);
	}

	private function persistData($result) {
		print_r('done with' . $result['baseInfo']['fn'] . "\n");
	}

	private function url($startDate, $endDate = '', $page = 1) {
		// 从Cookie中的当前用户登录名截取前12位作为机构编码
		$unitCode = substr($this->cookies[1]->getValue('zljyLoginname'), 0, 12);
		return config('publicHealth.rootUrl') . 'health/healthQuery.action?page.currentPage=' . $page . '&dqjg=' . $unitCode . '&crestarttime=' . $startDate . '&creendtime=' . $endDate . '&' . $this->params;
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
}