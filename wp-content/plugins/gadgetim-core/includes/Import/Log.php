<?php
/**
 * Import log: buffered lines plus an optional live sink (CLI output, AJAX response).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import;

/**
 * Levels mirror admin notice types so the batch UI can colour them.
 */
final class Log {

	public const INFO    = 'info';
	public const SUCCESS = 'success';
	public const WARNING = 'warning';
	public const ERROR   = 'error';

	public const MAX_LINES = 2000;

	/** @var array<int,array{level:string,step:string,message:string,time:int}> */
	private array $lines = [];

	/** @var callable|null */
	private $sink;

	private int $errors = 0;

	private int $warnings = 0;

	/**
	 * @param callable|null $sink Receives each line as [ level, step, message, time ].
	 */
	public function __construct( ?callable $sink = null ) {
		$this->sink = $sink;
	}

	/**
	 * Adds a line.
	 */
	public function add( string $level, string $message, string $step = '' ): void {
		$line = [
			'level'   => $level,
			'step'    => $step,
			'message' => $message,
			'time'    => time(),
		];
		if ( self::ERROR === $level ) {
			++$this->errors;
		} elseif ( self::WARNING === $level ) {
			++$this->warnings;
		}
		$this->lines[] = $line;
		if ( count( $this->lines ) > self::MAX_LINES ) {
			array_shift( $this->lines );
		}
		if ( null !== $this->sink ) {
			call_user_func( $this->sink, $line );
		}
	}

	/**
	 * Informational line.
	 */
	public function info( string $message, string $step = '' ): void {
		$this->add( self::INFO, $message, $step );
	}

	/**
	 * Success line.
	 */
	public function success( string $message, string $step = '' ): void {
		$this->add( self::SUCCESS, $message, $step );
	}

	/**
	 * Warning line.
	 */
	public function warning( string $message, string $step = '' ): void {
		$this->add( self::WARNING, $message, $step );
	}

	/**
	 * Error line.
	 */
	public function error( string $message, string $step = '' ): void {
		$this->add( self::ERROR, $message, $step );
	}

	/**
	 * All buffered lines.
	 *
	 * @return array<int,array{level:string,step:string,message:string,time:int}>
	 */
	public function lines(): array {
		return $this->lines;
	}

	/**
	 * Returns and clears the buffered lines (the AJAX runner ships them per batch).
	 *
	 * @return array<int,array{level:string,step:string,message:string,time:int}>
	 */
	public function take(): array {
		$lines       = $this->lines;
		$this->lines = [];
		return $lines;
	}

	/**
	 * Number of error lines so far.
	 */
	public function errors(): int {
		return $this->errors;
	}

	/**
	 * Number of warning lines so far.
	 */
	public function warnings(): int {
		return $this->warnings;
	}

	/**
	 * Plain-text rendering of a line ("[step] message").
	 */
	public static function format( array $line ): string {
		$prefix = '' !== (string) ( $line['step'] ?? '' ) ? '[' . $line['step'] . '] ' : '';
		$marker = match ( (string) ( $line['level'] ?? self::INFO ) ) {
			self::ERROR   => '!! ',
			self::WARNING => '! ',
			self::SUCCESS => '+ ',
			default       => '  ',
		};
		return $marker . $prefix . (string) ( $line['message'] ?? '' );
	}
}
