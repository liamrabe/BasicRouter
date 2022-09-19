<?php
namespace LiamRabe\BasicRouter\Trait;

use LiamRabe\BasicRouter\Route\Route;
use LiamRabe\BasicRouter\DataCollection\Request;
use LiamRabe\BasicRouter\DataCollection\Response;

trait LifecycleTrait {

	protected bool $skip_lifecycle = true;

	public function setSkipLifecycle(bool $skip_lifecycle): void {
		$this->skip_lifecycle = $skip_lifecycle;
	}

	/**
	 * Handle Route, Request & Response before output
	 */
	protected function preWrite(?Route $route = null, ?Request $request = null, ?Response $response = null): void {
		if ($this->expose_router) {
			/**
			 * Expose router name and version in HTTP headers
			 */
			$response->setHeader('X-Router-Name', 'BasicRouter');
			$response->setHeader('X-Router-Version', $this->getVersion());
		}
	}

	/**
	 * Handle Route, Request & Response after output
	 */
	protected function postWrite(?Route $route = null, ?Request $request = null, ?Response $response = null): void {}

	/**
	 * Handle Route, Request & Response before handling headers
	 */
	protected function preHeaders(?Route $route = null, ?Request $request = null, ?Response $response = null): void {}

	/**
	 * Handle Route, Request & Response after handling headers
	 */
	protected function postHeaders(?Route $route = null, ?Request $request = null, ?Response $response = null): void {}

}
