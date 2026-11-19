<?php
/**
 * Hook loader – registers actions and filters without collisions.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_Loader
 */
class SentryGate_Loader {

	/**
	 * Registered actions.
	 *
	 * @var array<int, array{hook: string, component: object|string, callback: string, priority: int, accepted_args: int}>
	 */
	protected array $actions = array();

	/**
	 * Registered filters.
	 *
	 * @var array<int, array{hook: string, component: object|string, callback: string, priority: int, accepted_args: int}>
	 */
	protected array $filters = array();

	/**
	 * Add an action.
	 *
	 * @param string        $hook          Hook name.
	 * @param object|string $component     Object or class.
	 * @param string        $callback      Method name.
	 * @param int           $priority      Priority.
	 * @param int           $accepted_args Accepted args.
	 */
	public function add_action( string $hook, object|string $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Add a filter.
	 *
	 * @param string        $hook          Hook name.
	 * @param object|string $component     Object or class.
	 * @param string        $callback      Method name.
	 * @param int           $priority      Priority.
	 * @param int           $accepted_args Accepted args.
	 */
	public function add_filter( string $hook, object|string $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Register all hooks with WordPress.
	 */
	public function run(): void {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
	}
}
