<?php
namespace App\Services;

use Goutte\Client;

/**
 * 模拟抓取公卫系统小蜘蛛
 * 可以实现：
 * 1. 全部抓取数据或指定开始日期抓取（开始日期--现在）
 * 2. 抓取的数据类型 （老年人、幼儿或其他）
 */
class PublicHealthProxy {
	protected $client;
	protected $cookies;
	public function __construct(Client $client) {
		$this->client = $client;
		$this->login();
	}

	public function show($fn) {
		$index = 'http://35.1.175.236:9080/sdcsm/health/showTjkjktjSkip.action?grdabh=' . $fn;
		$crawler = $this->client->request('GET', $index);
		// 获取用户所有的体检记录ID
		$archiveFnList = $crawler->filter('#yearContainer .content a')->each(function ($node) {
			preg_match('/\((\d+)\)/', $node->attr('onclick'), $matches);
			return $matches[1];
		});
		// 详细的体检记录结果由用户档案号和体检记录ID组成
		$id = $archiveFnList[0];
		$url = 'http://35.1.175.236:9080/sdcsm/health/selecttjlistforId.action?dGrdabh=' . $fn . '&id=' . $id;
		$crawler = $this->client->request('GET', $url);
		// 基本资料抓取
		$base = $crawler->filter('#table2')->text();
		dd($crawler->html());
		$baseMapper = [
			'fn' => '个人档案号',
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
				preg_match_all('/青岛市\s+.*/', $base, $match);
				$match = array_last(explode(' ', array_flatten($match)[0]));
				$baseInfo[$key] = trim($match);
			} else {
				preg_match_all('/' . $mapper . '(.*)?\\r\\n(\s+.*)/', $base, $match);
				$baseInfo[$key] = preg_replace('/\\r|\\t/', '', array_last($match))[0];
			}
		}
		// 当前病例
		$archiveMapper = [
			'check_date' => '体检日期',
		];
		dd($baseInfo);
	}

	/**
	 * 获取索引表
	 * POST 到$url中，参数拼接在url地址里
	 * @return [type] [description]
	 */
	public function index() {

		// 从Cookie中的当前用户登录名截取前12位作为机构编码
		$unitCode = substr($this->cookies[1]->getValue('zljyLoginname'), 0, 12);
		// 老年人年龄为65岁
		$agedBoundary = date('Y', strtotime('-65 years')) . '-12-31';
		$starDate = '2017-01-01';
		$currentPage = 22;
		// $url = 'http://35.1.175.236:9080/sdcsm/health/healthQuery.action?page.currentPage=' . $currentPage . '&status=ajax&dqjg=' . $unitCode;
		$url = 'http://35.1.175.236:9080/sdcsm/health/healthQuery.action?page.currentPage=' . $currentPage . '&dqjg=' . $unitCode . '&birstarttime=1900-01-01&birendtime=' . $agedBoundary . '&crestarttime=' . $starDate . '&creendtime=&dSfzh=&sfhg=&happenstarttime=&happenendtime=&dDazt=&selectChange=&updatestarttime=&updateendtime=&sfzdgl=&ssjd=&ssjwh=&ssxxdz=&tjqk=&jsbtjqk=&gxytjqk=&tnbtjqk=&lnrzlnl=&hb=hb&_hb=on&wbc=wbc&_wbc=on&plt=plt&_plt=on&gXcgqt=gXcgqt&_gXcgqt=on&gNdb=gNdb&_gNdb=on&gNt=gNt&_gNt=on&gNtt=gNtt&_gNtt=on&gNqx=gNqx&_gNqx=on&AFP=AFP&CEA=CEA&alt=alt&_alt=on&ast=ast&_ast=on&tbil=tbil&_tbil=on&scr=scr&_scr=on&bun=bun&_bun=on&niaosuan=niaosuan&cho=cho&_cho=on&tg=tg&_tg=on&ldlc=ldlc&_ldlc=on&hdlc=hdlc&_hdlc=on&gKfxt=gKfxt&_gKfxt=on&gXindt=gXindt&_gXindt=on&gBchao=gBchao&_gBchao=on&all1=on';
		$crawler = $this->client->request('POST', $url);
		// 获取记录列表总页数
		$totalPages = $crawler->filter('#all')->attr('value');
		/**
		 * 爬取表格，获得当前页面数据
		当前格式：
		0 => "序号"
		1 => "档案号"
		2 => "姓名"
		3 => "性别"
		4 => "出生日期"
		5 => "居住地址"
		6 => "身份证号"
		7 => "联系电话"
		8 => "体检日期"
		9 => "当前所属机构"
		10 => "录入人"
		11 => "录入时间"
		 */
		$result = [];
		$data = $crawler->filter('.QueryTable tr')->siblings()->each(function ($tr, $i) {
			$result['fn'] = $tr->filter('td')->eq(1)->text();
			$result['name'] = $tr->filter('td')->eq(2)->text();
			$result['gender'] = $tr->filter('td')->eq(3)->text();
			$result['birthday'] = $tr->filter('td')->eq(4)->text();
			$result['birthplace'] = $tr->filter('td')->html();
			$result['identify'] = $tr->filter('td')->eq(6)->text();
			$result['phone'] = $tr->filter('td')->eq(7)->text();
			$result['check_date'] = $tr->filter('td')->eq(8)->text();
			return $result;
		});
		dd($data);
	}

	private function formatData($td, $index) {
		$result = [];
		if ($index > 0 && $index < 8) {
			$result[] = trim($td->text());
		}
	}

	private function fetchData($currentPage = 1) {
		$date = date('Y-m-d');
		return $currentPage++;
	}
	/**
	 * 模拟登陆
	 * @return $this->client
	 */
	private function login() {
		$url = 'http://35.1.175.236:9080/sdcsm/login.action';
		$crawler = $this->client->request('GET', $url);
		$form = $crawler->filter('#loginForm')->form();
		$crawler = $this->client->submit($form, array('loginname' => config('publicHealth.username'), 'password' => config('publicHealth.password')));
		$this->cookies = $this->client->getCookieJar()->all();
		$this->client->getCookieJar()->updateFromSetCookie($this->cookies);
	}
}