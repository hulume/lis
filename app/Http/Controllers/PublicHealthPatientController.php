<?php
namespace App\Http\Controllers;

use App\Services\PublicHealthProxy;

/**
 * 公共卫生系统抓取患者数据
 */
class PublicHealthPatientController extends Controller {

	protected $proxy;
	public function __construct(PublicHealthProxy $proxy) {
		$this->proxy = $proxy;
	}

	public function edit($id) {
		return $this->proxy->show($id);
	}
	public function index() {
		return $this->proxy->index();
	}
}