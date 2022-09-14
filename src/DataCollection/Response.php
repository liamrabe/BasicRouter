<?php
namespace LiamRabe\BasicRouter\DataCollection;

use LiamRabe\BasicRouter\Router as Router;

class Response {

	public const EXPOSE_ROUTER_HEADER = 'X-Powered-By';

	protected array $headers = [
		'Content-Type' => 'text/html',
	];

	protected string $body = '';

	protected int $status_code = 200;

	public function getHeaders(): array {
		if (Router::getExposeRouter()) {
			$this->headers[self::EXPOSE_ROUTER_HEADER] = sprintf('BasicRouter (%s)', Router::getVersion());
		}

		return $this->headers;
	}

	public function getHeader(string $header): string {
		return $this->headers[$header] ?? '';
	}

	public function setHeader(string $header, string $value): void {
		$this->headers[$header] = $value;
	}

	public function setStatus(int $status_code): void {
		$this->status_code = $status_code;
	}

	public function getStatus(): int {
		return $this->status_code;
	}

	public function getBody(): string {
		return $this->body;
	}

	public function setBody(string $body): void {
		$this->body = $body;
	}

}
