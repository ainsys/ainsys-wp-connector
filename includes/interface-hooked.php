<?php

namespace Ainsys\Connector\Master;

interface Hooked {


	/**
	 * Initializes WordPress hooks for plugin/components.
	 *
	 * @return void
	 */
	public function init_hooks();

}