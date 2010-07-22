<?php

class Osmosis
{
	protected $filecontents = array();

	const CODE_START_PATTERN = '#^\s{4}#';
	const HEADING_START_PATTERN = '#^[\d\.]+\s+#';
	const PARA_START_PATTERN = '#^\S#';
	const BLANK_LINE_PATTERN = '#^\s+$#';

	const HEADING_TOKEN = 1;
	const CODE_START_TOKEN = 2;
	const CODE_END_TOKEN = 3;
	const PARA_START_TOKEN = 4;
	const PARA_END_TOKEN = 5;

	protected $intermediate = array();

	public $charset = 'UTF-8';

	public function html($str)
	{
		return htmlspecialchars($str, ENT_QUOTES, $this->charset);
	}

	public function LoadFile($filename)
	{
		if (!file_exists($filename)) {
			throw new FileNotFoundException($filename);
		}

		$this->filecontents = file($filename);
	}

	public function Load($string)
	{
		$this->filecontents = explode("\n", $string);
	}

	public function Lexer()
	{
		$state = '';
		$buffer = '';

		foreach ($this->filecontents as $linenum => $line) {
			# code is terminated by a paragraph or a heading
			if ($state == 'code') {
				if (preg_match(self::HEADING_START_PATTERN, $line) || preg_match(self::PARA_START_PATTERN, $line)) {
					$state = '';
					$this->intermediate[] = self::CODE_END_TOKEN;
				} else {
					$this->intermediate[] = preg_replace(self::CODE_START_PATTERN, '', rtrim($line));
				}
			}

			# A blank line marks the end of a paragraph
			if ($state == 'para') {
				if (preg_match(self::BLANK_LINE_PATTERN, $line)) {
					$this->intermediate[] = self::PARA_END_TOKEN;
					$state = '';
				} else {
					$this->intermediate[] = trim($line);
				}
				continue;
			}

			if (!$state) {
				if (preg_match(self::CODE_START_PATTERN, $line)) {
					$state = 'code';
					$this->intermediate[] = self::CODE_START_TOKEN;
					$this->intermediate[] = trim($line);
				} elseif (preg_match(self::HEADING_START_PATTERN, $line)) {
					$this->intermediate[] = self::HEADING_TOKEN;
					$this->intermediate[] = preg_replace(self::HEADING_START_PATTERN, '', trim($line));
				} elseif (preg_match(self::PARA_START_PATTERN, $line)) {
					$state = 'para';
					$this->intermediate[] = self::PARA_START_TOKEN;
					$this->intermediate[] = trim($line);
				}
				continue;
			}
		}

		switch ($state) {
			case 'code': { $this->intermediate[] = self::CODE_END_TOKEN; break; }
			case 'para': { $this->intermediate[] = self::PARA_END_TOKEN; break; }
		}
	}

	public function Parser()
	{
		$output = '';
		$doneHeading = false;
		$inHeading = false;
		$inBlock = false;
		foreach ($this->intermediate as $key => $token) {
			if ($inHeading) {
				$output .= $token;
				if ($doneHeading) {
					$output .= "</h2>\n";
				} else {
					$output .= "</h1>\n";
					$doneHeading = true;
				}
				$inHeading = false;
				continue;
			}
			switch ($token) {
				case self::HEADING_TOKEN: {
					if ($doneHeading) {
						$output .= '<h2>';
					} else {
						$output .= '<h1>';
					}
					$inHeading = true;
					break;
				}
				case self::CODE_START_TOKEN: {
					$inBlock = true;
					$output .= '<pre>';
					break;
				}
				case self::CODE_END_TOKEN: {
					$inBlock = false;
					$output .= "</pre>\n";
					break;
				}
				case self::PARA_START_TOKEN: {
					$output .= '<p>';
					$inBlock = true;
					break;
				}
				case self::PARA_END_TOKEN: {
					$output .= "</p>\n";
					$inBlock = false;
					break;
				}
				default: {
					$output .= $this->html($token);
					if ($inBlock) {
						$output .= "\n";
					}
				}
			}
		}
		return $output;
	}

	public function GetIntermediateCode()
	{
		return $this->intermediate;
	}
}

class FileNotFoundException extends Exception {}