<?php

/**
 * This file is part of the Nette Tester.
 * Copyright (c) 2009 David Grudl (http://davidgrudl.com)
 */

namespace Tester\Runner;


/**
 * phpdbh command-line executable.
 */
class PhpDbgInterpreter implements PhpInterpreter
{
	/** @var string  PHP arguments */
	public $arguments;

	/** @var string  PHP executable */
	private $path;

	/** @var string  PHP version */
	private $version;

	/** @var string */
	private $error;


	public function __construct($path, $args = NULL)
	{
		$this->path = \Tester\Helpers::escapeArg($path);
		$proc = proc_open(
				"$this->path -qrr -n $args --version",
				array(array('pipe', 'r'), array('pipe', 'w'), array('pipe', 'w')),
				$pipes,
				NULL,
				NULL,
				array('bypass_shell' => TRUE)
		);
		$output = stream_get_contents($pipes[1]);
		$this->error = trim(stream_get_contents($pipes[2]));
		if (proc_close($proc)) {
			throw new \Exception("Unable to run '$path': " . preg_replace('#[\r\n ]+#', ' ', $this->error));
		} elseif (!preg_match('#^PHP (\S+)#im', $output, $matches)) {
			throw new \Exception("Unable to detect PHP version (output: $output).");
		}

		$this->version = $matches[1];
		$this->arguments = $args;
	}


	/**
	 * @return string
	 */
	public function getCommandLine()
	{
		return $this->path . ' -qrr ' . $this->arguments;
	}


	/**
	 * @return string
	 */
	public function getVersion()
	{
		return $this->version;
	}


	/**
	 * @return bool
	 */
	public function hasXdebug()
	{
		// TODO test in info.php: return function_exists('phpdbg_start_oplog');
		return TRUE; // coverage needs this method // TODO
	}


	/**
	 * @return bool
	 */
	public function isCgi()
	{
		return FALSE; // TODO
	}


	/**
	 * @return string
	 */
	public function getErrorOutput()
	{
		return $this->error;
	}

}
