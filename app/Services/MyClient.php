<?php
namespace App\Services;

use Goutte\Client;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\BrowserKit\Response;

class MyClient extends Client {
	protected function createResponse(ResponseInterface $response) {
		$headers = $response->getHeaders();
		if (!isset($headers['Content-Type'])) {
			$headers['Content-Type'][0] = 'text/html; charset=UTF-8';
		} elseif (stripos($headers['Content-Type'][0], 'charset=') === false) {
			if (trim($headers['Content-Type'][0]) && substr(trim($headers['Content-Type'][0]), -1) !== ';') {
				$headers['Content-Type'][0] = trim($headers['Content-Type'][0]) . ';';
			}
			$headers['Content-Type'][0] .= ' charset=UTF-8';
		}
		return new Response((string) $response->getBody(), $response->getStatusCode(), $headers);
	}
}