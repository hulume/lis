<?php

namespace App\Console\Commands;

use App\Services\PostData;
use Illuminate\Console\Command;

class PostLisData extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'postlis:all';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Post all LIS data from sql-server database';

	protected $post;

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct(PostData $post) {
		parent::__construct();
		$this->post = $post;
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle() {
		$this->post->all();
	}
}
