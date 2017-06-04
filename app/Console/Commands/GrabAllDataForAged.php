<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GrabAllDataForAged extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'lis:allAged';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Grab all Aged LIS data from sql-server database';

	protected $grab;

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct(GrabData $grab) {
		parent::__construct();
		$this->grab = $grab;
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle() {
		$this->grab->all();
	}
}
