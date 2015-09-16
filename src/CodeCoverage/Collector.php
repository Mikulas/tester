<?php

namespace Tester\CodeCoverage;

use Tester\Helpers;


class Collector
{
	const COVER_NOTHING = 1;
	const COVER_ALL = 2;

	/** @var resource */
	protected static $file;


	/**
	 * Starts gathering the information for code coverage.
	 * @param  string
	 * @return void
	 */
	public static function start($file)
	{
		if (!function_exists('phpdbg_start_oplog')) {
			throw new \Exception('Code coverage functionality requires phpdbg.');
		} elseif (self::$file) {
			throw new \LogicException('Code coverage collector has been already started.');
		}

		self::$file = fopen($file, 'a+');

		phpdbg_start_oplog();

		register_shutdown_function(function () {
			register_shutdown_function([__CLASS__, 'save']);
		});
	}


	protected static function getCoverAnnotations()
	{
		global $argv;
		$testFile = $argv[0];
		return Helpers::parseDocComment(file_get_contents($testFile));
	}


	/**
	 * @return int|array[] (filename => \Reflector[])
	 * @throws \ReflectionException
	 */
	protected static function getCoverFilters()
	{
		$annotations = static::getCoverAnnotations();
		if (isset($annotations['coversnothing'])) {
			if (isset($annotations['covers'])) {
				throw new \Exception('Using both @covers and @coversNothing is not supported');
			}

			return self::COVER_NOTHING;
		}

		if (!isset($annotations['covers'])) {
			// TODO warn user to use covers
			return self::COVER_ALL;
		}

		$filters = array();
		foreach ((array) $annotations['covers'] as $name) {
			$ref = NULL;
			try {
				if (strpos($name, '::') !== FALSE) {
					$ref = new \ReflectionMethod(rtrim($name, '()'));

				} else {
					$ref = new \ReflectionClass($name);
				}

			} catch (\ReflectionException $e) {
				throw new \Exception("Failed to find '$name' when generating coverage", NULL, $e);
			}

			$filters[$ref->getFileName()][] = $ref;
		}

		return $filters;
	}


	/**
	 * @param \ReflectionClass[]|\ReflectionMethod[] $refs
	 * @param int                                    $line
	 * @return bool
	 */
	private static function isCovered(array $refs, $line)
	{
		foreach ($refs as $ref) {
			if ($line >= $ref->getStartLine() && $line <= $ref->getEndLine()) {
				return TRUE;
			}
		}
		return FALSE;
	}


	/**
	 * @param string $filename
	 * @param array  $lines
	 * @return array
	 */
	private static function removeUncoverable($filename, array $lines)
	{
		foreach (explode("\n", file_get_contents($filename)) as $i => $source) {
			if (preg_match('~^\W*$~', $source)) {
				unset($lines[$i + 1]);
			}
		}
		return $lines;
	}


	/**
	 * Saves information about code coverage. Do not call directly.
	 * @return void
	 * @internal
	 */
	public static function save()
	{
		$filters = static::getCoverFilters();

		$negative = array();
		$positive = array();

		/** @var array $coverage */
		$positive = phpdbg_end_oplog(array(
			'show_unexecuted' => FALSE
		));
		foreach ($positive as $file => &$lines) {
			foreach ($lines as &$line) {
				if ($line > 1) {
					$line = 1;
				}
			}
			ksort($lines);
		}

		$negative = phpdbg_get_executable();

		foreach ($positive as $filename => &$lines) {
			$refs = isset($filters[$filename]) ? $filters[$filename] : array();
			foreach ($lines as $num => $val) {
				if ($filters === self::COVER_NOTHING || ($filters !== self::COVER_ALL && !static::isCovered($refs, $num))) {
					unset($lines[$num]);
				}
			}
		}

		flock(self::$file, LOCK_EX);
		fseek(self::$file, 0);
		$original = @unserialize(stream_get_contents(self::$file)) ?: array(); // @ file may be empty

		$coverage = array_replace_recursive($negative, $original, $positive);

		ftruncate(self::$file, 0);
		fwrite(self::$file, serialize($coverage));
		fclose(self::$file);
	}

}
