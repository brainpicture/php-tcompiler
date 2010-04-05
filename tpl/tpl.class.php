<?php
class tpl {
	function tpl($tplDir,$tmpDir) {
		$this->tplDir=$tplDir;
		$this->tmpDir=$tmpDir;
	}
	function Render($Path='index.html', $level=0, $parent='', $blockname='') {
		$Path=$this->tplDir.$Path;
		$tmpName='tpl_'.(!empty($blockname)?$blockname.'_':'').str_replace(array('/','\\'),'.',$Path).'.php';
		$tmpPath=$this->tmpDir.'/'.$tmpName;

		if (file_exists($tmpPath))
		$tmpChange=filemtime($tmpPath);
		$tplChange=filemtime($Path);
		if ($tplChange+1==$tmpChange) include($tmpPath.'.coll.php');
		elseif ($tplChange!=$tmpChange) $needCompile=true;
		if ($needCompile) {
			# Вызов компилятора
			include_once 'tcompiler.class.php';
			$compiler = new tcompiler($this,$this->tmpDir);
			$compiler->compile($Path,$tmpPath,$level,$parent,$blockname);
			
		}		
		include $tmpPath;
	}
	function set($name,$value) {
		$this->$name=&$value;
	}
	function text($Path,$blockname) {
		return $this->render($Path,0,'',$blockname);
	}
	function out($Path,$blockname) {
		echo $this->text($Path,&$blockname);
	}
}
?>
