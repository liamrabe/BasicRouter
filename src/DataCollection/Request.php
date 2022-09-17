<?php
namespace LiamRabe\BasicRouter\DataCollection;

use LiamRabe\BasicRouter\HTTP\Entity\Server;
use LiamRabe\BasicRouter\HTTP\Entity\Cookie;
use LiamRabe\BasicRouter\HTTP\Entity\Post;
use LiamRabe\BasicRouter\HTTP\Entity\Get;

class Request {

	protected function __construct(
		protected Get $get,
		protected Post $post,
		protected Cookie $cookies,
		protected Server $server,
		protected ?Parameters $named_parameters = null,
		protected ?string $body = null
	) {}

	public function getCookies(): array {
		return $this->cookies->getData();
	}

	public function getServer(): array {
		return $this->server->getData();
	}

	public function getPost(): array {
		return $this->post->getData();
	}

	public function getGet(): array {
		return $this->get->getData();
	}

	public function getParameters(): Parameters {
		return $this->named_parameters;
	}

	public function setParameters(Parameters $parameters): void {
		$this->named_parameters = $parameters;
	}

	public function getBody(): string {
		return $this->body;
	}

	/* Static methods */

	public static function createFromGlobals(?Parameters $named_parameters = null): self {
		return new self(Get::assemble(), Post::assemble(), Cookie::assemble(), Server::assemble(), $named_parameters, null);
	}

}
