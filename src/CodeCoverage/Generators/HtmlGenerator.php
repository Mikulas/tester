<?php

/**
 * This file is part of the Nette Tester.
 * Copyright (c) 2009 David Grudl (http://davidgrudl.com)
 */

namespace Tester\CodeCoverage\Generators;


/**
 * Code coverage report generator.
 */
class HtmlGenerator extends AbstractGenerator
{
	/** @var string */
	private $title;

	/** @var array */
	private $files = array();

	/** @var int */
	private $totalSum = 0;

	/** @var int */
	private $coveredSum = 0;

	/** @var array */
	private $aggregates = array();


	/**
	 * @param  string  path to coverage.dat file
	 * @param  string  path to source file/directory
	 * @param  string
	 */
	public function __construct($file, $source = NULL, $title = NULL)
	{
		parent::__construct($file, $source);
		$this->title = $title;
	}


	protected function renderSelf()
	{
		$this->setupHighlight();
		$this->parse();
		$this->aggregate();

		$title = $this->title;
		$files = $this->files;
		$totalSum = $this->totalSum;
		$coveredSum = $this->coveredSum;
		$aggregates = $this->aggregates;

		$highlightedFiles = array_flip($this->getChangedFiles());

		include __DIR__ . '/template.phtml';
	}


	private function aggregate()
	{
		$dirs = [];
		foreach ($this->files as $file) {
			$dir = $file->file;
			do {
				$dir = dirname($dir);
				$dirs[$dir][] = $file->coverage;
			} while ($dir !== $this->source);
		}

		$aggs = [];
		$prefix = strlen(rtrim(dirname($this->source), '/\\')) + 1;
		foreach ($dirs as $dir => $percentages) {
			$path = substr($dir, $prefix);

			$limit = strpos($path, '/services') === FALSE ? 2 : 3;
			if (count(explode('/', $path)) > $limit) {
				continue;
			}
			$aggs[$path] = array_sum($percentages) / count($percentages);
		}

		ksort($aggs);
		$this->aggregates = $aggs;
	}


	/**
	 * @return \string[] $fromRef file paths
	 */
	private function getChangedFiles()
	{
		$root = dirname(dirname(dirname(dirname(dirname(dirname(__DIR__))))));

		$proc = proc_open(
			'git diff --name-only master | sort | uniq',
			array(array('pipe', 'r'), array('pipe', 'w'), array('pipe', 'w')),
			$pipes,
			$root,
			NULL,
			array('bypass_shell' => TRUE)
		);
		$output = stream_get_contents($pipes[1]);

		if (proc_close($proc)) {
			return [];
		}

		return array_map(function($m) use ($root) {
			return "$root/$m";
		}, array_filter(explode("\n", $output)));
	}


	private function setupHighlight()
	{
		ini_set('highlight.comment', '#999; font-style: italic');
		ini_set('highlight.default', '#000');
		ini_set('highlight.html', '#06B');
		ini_set('highlight.keyword', '#D24; font-weight: bold');
		ini_set('highlight.string', '#080');
	}


	private function parse()
	{
		if (count($this->files) > 0) {
			return;
		}

		$this->files = array();
		foreach ($this->getSourceIterator() as $entry) {
			$entry = (string) $entry;

			$coverage = $covered = $total = 0;
			$loaded = isset($this->data[$entry]);
			$lines = array();
			if ($loaded) {
				$lines = $this->data[$entry];
				foreach ($lines as $flag) {
					$total++;
					if ($flag > 0) {
						$covered++;
					}
				}
				$coverage = $total ? round($covered * 100 / $total) : 100;
				$this->totalSum += $total;
				$this->coveredSum += $covered;
			}

			$light = $total ? $total < 5 : count(file($entry)) < 50;
			$this->files[] = (object) array(
				'name' => str_replace((is_dir($this->source) ? $this->source : dirname($this->source)) . DIRECTORY_SEPARATOR, '', $entry),
				'file' => $entry,
				'lines' => $lines,
				'coverage' => $coverage,
				'total' => $total,
				'class' => $light ? 'light' : ($loaded ? NULL : 'not-loaded'),
			);
		}
	}

}
