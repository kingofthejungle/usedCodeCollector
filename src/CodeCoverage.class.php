<?php

/**
 * Code Coverage Tool
 *
 * @author Tomas Horacek <t.horacek@pixvalley.com>
 */
class CodeCoverage {
	
	/**
	 * Full path to the coverage file
	 * @var string
	 */
	private $coverageFile;
	
	private $skipPath;
	
	private $showCoverage = false;
	
	
	/**
	 * Constructor
	 * @param string $coverageFile 
	 */
	public function __construct($coverageFile, $skipPath)
	{
		$this->coverageFile = $coverageFile;
		$this->skipPath = $skipPath;
		
		
		if(!file_exists($this->coverageFile)) {
			$this->createCoverageFile();
		}
		
		if(isset($_GET['showCoverage'])) {
			$this->showCoverage = true;
		}
	}
	
	
	/**
	 * Creatre Coverage File 
	 */
	private function createCoverageFile()
	{
		$f = fopen($this->coverageFile, "w+");
		fclose($f);
	}
	
	
	/**
	 * Encode array in format better reading in Java
	 * @param array $coverage
	 * @return array 
	 */
	public static function encode($coverage)
	{
		$encodedConverted = array();
		if(is_array($coverage))
		{
			foreach($coverage as $key => $value)
			{
				$encodedConverted[] = array(
					'file' => $key,
					'lines' => array_keys($value),
				);
			}
		}
		return $encodedConverted;
	}

	
	/**
	 * Decode array readable in java plugin to original format returned from
	 * xdebug_start_code_coverage();
	 * 
	 * @param array $aEncodedCoverage
	 * @return array 
	 */
	public static function decode($encodedCoverage)
	{
		$decodedCoverage = array();
		if(is_array($encodedCoverage)) 
		{
			foreach($encodedCoverage as $fileCoverage)
			{
				$decodedCoverage[$fileCoverage['file']] = array_fill_keys($fileCoverage['lines'], 1);
			}
		}
		return $decodedCoverage;
	}
	

	/**
	 * Trim File Name
	 * get file path from string generated by xdebug
	 * 
	 * @param string $sFileName
	 * @return string 
	 */
	private function trimFileName($fileName)
	{	
		$exploded = explode('(', $fileName);
		$tmpFileName = array_shift($exploded);
		
		$fileName = !is_null($tmpFileName) ? $tmpFileName : $fileName;
		$fileName = substr($fileName, strlen($this->skipPath));
		
		return $fileName;
	}
	
	
	/**
	 * File read and truncate it to 0 lenght for future usage
	 * @return string
	 */
	private function fileRead($truncate = false)
	{
		$data = '';
		if(($fileSize = filesize($this->coverageFile)) > 0)
		{	
			$f = fopen($this->coverageFile, 'r+');
			$data = fread($f, $fileSize);
			if($truncate) {
				ftruncate($f, 0);
			}
			fclose($f);
		}

		return $data;
	}
	
	
	/**
	 * @param string $coverage 
	 */
	private function fileWrite($coverage)
	{
		$f = fopen($this->coverageFile, 'w+');
		fwrite($f, $coverage);
		fclose($f);
	}
	
	
	
	/**
	 * Start Coverage 
	 */
	public function start()
	{
		xdebug_start_code_coverage();
	}
	
	
	/**
	 * Stop Coverage 
	 */
	public function stop()
	{
		$coverage = xdebug_get_code_coverage();
		
		$prevCoverage = $this->decode(json_decode($this->fileRead(true), true));

		//
		// Coverage merging
		//
		$tempCoverage = array();
		foreach($coverage as $key => $value) {
			$key = $this->trimFileName($key);	// File name

			// Merge coveraged lines from current coverage and previous coverage
			if(isset($data[$key])) {
				$tempCoverage[$key] = $value + $prevCoverage[$key];
				unset($prevCoverage[$key]);
			} else {
				$tempCoverage[$key] = $value;
			}
		}
		// Attend the new coverage files
		if(isset($prevCoverage) && is_array($prevCoverage)) {
			$coverage = $tempCoverage + $prevCoverage;	
		} else {
			$coverage = $tempCoverage;
		}
		
		$coverage = json_encode($this->encode($coverage));

		$this->fileWrite($coverage);
		
		
		if($this->showCoverage) {
			$this->show();
		}
	}
	
	
	private function show()
	{
		$coverage = $this->decode(json_decode($this->fileRead(), true));
		
		$analysator = new Analysator($coverage, "/(.*)(".$_GET['pattern'].")(.*)/");

		$output = '
			<link rel="stylesheet" href="http://code.jquery.com/ui/1.9.0/themes/base/jquery-ui.css" />
			<script src="http://code.jquery.com/jquery-1.8.2.js"></script>
			<script src="http://code.jquery.com/ui/1.9.0/jquery-ui.js"></script>
			<link type="text/css" rel="stylesheet" href="http://alexgorbatchev.com/pub/sh/current/styles/shCore.css"/>
			<link href="http://alexgorbatchev.com/pub/sh/current/styles/shThemeDefault.css" rel="stylesheet" type="text/css" />
			<script type="text/javascript" src="http://alexgorbatchev.com/pub/sh/current/scripts/shCore.js"></script>
			<script src="http://alexgorbatchev.com/pub/sh/current/scripts/shAutoloader.js" type="text/javascript"></script>
			<script type="text/javascript" src="http://alexgorbatchev.com/pub/sh/current/scripts/shBrushJScript.js"></script>
			<script type="text/javascript" src="http://alexgorbatchev.com/pub/sh/current/scripts/shBrushPhp.js"></script>
			<script type="text/javascript">SyntaxHighlighter.all();</script>
			<style>
				#coverageBox{font-family:arial; position: relative;}
				#coverageBox pre { overflow: hidden; }
				#coverageBox .title, #coverageBox .heading {position: relative;	display: block; width: 100%; height: 20px; background: #eee; margin: 0px 0px 1px 0px; padding: 5px; cursor: pointer;}
				#coverageBox .total {float: right;width:500px;background: #4a8cdb;margin: 0px 0px 1px 0px;padding: 5px;font-weight: bold;color: #fff;}		
				#coverageBox .heading {font-weight: bold;background-color: #FF9900;}		
				#coverageBox .usage {width: 150px;display: block;positon: absolute;	float:right;}
				#coverageBox .percent {width: 150px;display: block;	positon: absolute;float:right;}
			</style>	
			
			<div id="coverageBox">
				<div class="heading">
					<span class="fileName"><strong>File Name</strong></span>
					<span class="percent">Percent</span>
					<span class="usage">Usage</span>
				</div>
			';

		$totalUsed = 0;
		$totalSourceLines = 0;

		foreach($analysator->files as $file)
		{
			$output .= '
				<div class="title">
					<span class="fileName">'.$file->name.'</span>
					<span class="percent">'.$file->percentateUsed.'%</span>
					<span class="usage">'.$file->used.' / '.$file->sourceLines.'</span>
				</div>
				<div class="code">
					<pre class="brush: php highlight:'.$file->highlight.'">'.htmlspecialchars($file->source).'</pre> 
				</div>
				';
		
			$totalUsed += $file->used;
			$totalSourceLines += $file->sourceLines;
		}
		
		$output .= '
				<div class="total">
					<span>Used files: '.$analysator->numFiles.'</span>
					<span class="percent">'.round($totalUsed / $totalSourceLines * 100, 1).'%</span>
					<span class="usage">'.$totalUsed.' / '.$totalSourceLines.'</span>
				</div>
			</div>
	
			<script>
				$(function() {
					$(".code").hide();
					$(".title").click(function() {
						$(this).next(".code").toggle();
					});
				});
			</script>
			';
		
		echo $output;
	}
	
}


class File {
	public $name;
	public $source;
	public $highlight = array();
	
	public $used = 0;
	public $percentateUsed = 0;
	public $sourceLines = 0;
	
	public function __construct($name, $source, $highlight) {
		$this->name = $name;
		$this->source = $source;
		
		$this->used = count($highlight);
		$this->sourceLines = count(explode("\n", $source));
		$this->percentateUsed = round(($this->used / $this->sourceLines) * 100, 1);
		
		$this->highlight = '['.implode(', ',array_keys($highlight)).']';		
	}
}

class Analysator {
	public $files = array();
	public $numFiles = 0;
	private $pattern;
	
	public function __construct($coverage, $pattern)
	{
		$this->pattern = $pattern;
		
		$prefix = substr(__DIR__, 0, strpos(__DIR__, '/fo-monnier'));
		
		foreach($coverage as $key => $value) {
			$key = $prefix.$key;
			
			if(file_exists($key)) {
				$f = fopen($key, 'r');
				$source = fread($f, filesize($key));
				$this->files[] = new File($key, $source, $value);
			}
		}
		
		$this->numFiles = count($this->files);
	}
	
	public function filter($sourceData)
	{
		foreach($sourceData as $key => $value) {
			if(!preg_match($this->pattern, $key)) {
				unset($sourceData[$key]);
			}
		}
		return $sourceData;
	}	
}
