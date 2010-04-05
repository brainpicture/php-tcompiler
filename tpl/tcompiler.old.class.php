<?php
/**
 * Компилятор шаблонов
 *
 * @package ll
 * @link http://emby.ru
 * @autor Oleg Illarionov <oleg@emby.ru>
 * @version 1.0
 */
 
 
class tcompiler {
	var $object=array();
	var $class;
	var $methods;
	var $vars;
	var $tmp_path;
	
	function tcompiler(&$obj, $tmp_path='../tmp') {
		$this->object=&$obj;
		$this->class=get_class($this->object);
		$this->methods=get_class_methods($this->class);
		$this->vars=get_class_vars($this->class);
		$this->tmp_path=$tmp_path;
	}
	
	# Рекурсивная функция, компилирующая блоки
	function compileBlock(&$code, $level=0, $parent='') {
		//echo "\n-----------\n".$code."\n------------\n";
		preg_match_all('~<!--[\s]*begin[\s]*:[\s]*'.$parent.'([a-zA-Z0-9\.]*)[\s]*-->(((?R)|.*)*)<!--[\s]*end[^>]*-->~Uis',$code,$matches);
		//print_r($matches);
		if ($level==0)
			$prefix = '$this->';
		else
			$prefix = '$this->blockValue['.($level -1).']';
		foreach ($matches[0] as $key=>$str) {
			$var=explode(".",$matches[1][$key]);
			foreach ($var as $num=>$i) {
				if ($num!=0 || $level!=0) $var[$num]='[\''.$var[$num].'\']';
			}
			$blockname=$var[0];
			$var = implode('',$var);
			$out = '<?php if (!empty('.$prefix.$var.')) foreach('.$prefix.$var.' as $this->blockValue['.$level.']) { ?>';
			$in_code=$matches[2][$key];
			# Шаг 1
			# Компилируем вложенные блоки внутри блока
			$this->compileBlock($in_code, $level+1, $parent.$matches[1][$key].'.');
			# Шаг 2
			# Компилируем обычные блоки внутри блока
			$this->compileBlock($in_code);
			
			$out.=$in_code;
			$out.='<?php } ?>';
			$code=str_replace($str,$out,$code);
		}
		
		# Шаг №2
		# Компилирование инклудов
		preg_match_all('|<!--([\s]*)([a-zA-Z]*)([\s]*):([\s]*)([a-zA-Z0-9{}_\./\\\]*)([\s]*)-->|Uis',$code,$matches);
		foreach ($matches[0] as $k=>$str) {
			switch ($matches[2][$k]) {
				case 'include':
					# скомпилируем переменныe внутри адреса
					$matches[5][$k]=str_replace(array('\\\'','\''),array('\'','\\\''),$matches[5][$k]);
					if (preg_match_all('|{([a-zA-Z_0-9\.]*)}|Uis',$matches[5][$k],$inMatches)) {
						//die('not right');
						foreach ($inMatches[1] as $kk=>$var) {
							$var=explode(".",$var);
							foreach ($var as $num=>$i) {
								if ($num!=0) $var[$num]='[\''.$var[$num].'\']';
							}
							$var=implode('',$var);
							$newstr='\'.$this->'.$var.'.\'';
							$matches[5][$k]=str_replace($inMatches[0][$kk],$newstr,$matches[5][$k]);
						}
						//die($matches[5][$k]);
						if ($level) $args=','.$level.',\''.$parent.'\'';
						else $args='';
						$code=str_replace($matches[0][$k],'<?php $this->render(\''.$matches[5][$k].'\''.$args.');?>',$code);
					} else {
						$code=str_replace($str,file_get_contents($this->object->tplDir.'/'.$matches[5][$k]),$code);
						$this->collideTpl[]='if (filemtime(\''.str_replace(array("'","\\"),array("\'","\\\\"),$this->object->tplDir.'/'.$matches[5][$k]).'\') != '.filemtime($this->object->tplDir.'/'.$matches[5][$k]).') $needCompile=true;';
					}
				break;
				case 'require':
					$code=str_replace($str,file_get_contents($this->object->tplDir.'/'.$matches[5][$k]),$code);
				break;
			}
		}

		# Шаг 3
		# Компилируем переменные внутри блока
		preg_match_all('|{([a-zA-Z_0-9\.]*)}|Uis',$code,$matches);
		foreach ($matches[1] as $k=>$var) {	
			if (!empty($parent) && strpos($var, $parent)===(int)0) {
				$var=substr($var,strlen($parent));
				$thisvar=true;
			} else $thisvar=false;
			$var=explode(".",$var);
			foreach ($var as $num=>$i) {
				if ($num!=0) $var[$num]='[\''.$var[$num].'\']';
			}
			if ($thisvar) $var[0]='$this->blockValue['.($level-1).'][\''.$var[0].'\']';
			else $var[0]='$this->'.$var[0];
			//if ($thisvar) die($var[0]);
			$var=implode('',$var);
			$newstr='<?php echo '.$var.';?>';
			$code=str_replace($matches[0][$k],$newstr,$code);
		}
		
		# Шаг 4
		# Компилирование условий
		preg_match_all('~<!--[\s]*if[\s]*:[\s]*([a-zA-Z0-9\.]*)[\s]*-->(((?R)|.*)*)<!--[\s]*end[^>]*-->~Uis',$code,$matches);
		foreach ($matches[0] as $key=>$str) {
			$var=explode(".",substr($matches[1][$key],strlen($parent)));
			foreach ($var as $num=>$i) {
				if ($num) $var[$num]='[\''.$var[$num].'\']';
			}
			if (!empty($parent) && strpos($matches[1][$key], $parent)===(int)0)
				$var[0]='$this->blockValue['.($level-1).']['.$var[0].']';
			else
				$var[0]='$this->'.$var[0];
			$newstr='<?php if ('.implode('',$var).') { ?>'.$matches[2][$key].'<?php } ?>';
			$code=str_replace($str, $newstr, $code);
		}
		
		# Шаг 5
		# Компилирование сниппетов модулей
		preg_match_all('~<!--[\s]*module[\s]*:[\s]*([a-zA-Z0-9\.]*)[\s]*-->(((?R)|.*)*)<!--[\s]*end[^>]*-->~Uis',$code,$matches);
		foreach ($matches[0] as $key=>$str) {
			$var=explode(".",substr($matches[1][$key],strlen($parent)));
			
			$newstr='wow'.$matches[2][$key].'wow';
			$code=str_replace($str, $newstr, $code);
		}
		
	}
	
	function getBlock(&$code,$blockname) {
		preg_match_all('~<!--[\s]*(begin|if|module)[\s]*:[\s]*'.$blockname.'([a-zA-Z0-9\.]*)[\s]*-->(((?R)|.*)*)<!--[\s]*end[^>]*-->~Uis',$code,$matches);
	}
	
	function compile($Path,$tmpPath,$level=0,$parent='',$blockname='') {
		$code=file_get_contents($Path);
		if (!empty($blockname)) $code=$this->getBlock($code,$blockname);
		# Шаг №1
		# Спрячем экранированные скобки, чтобы серьёзно  облегчить себе жизнь
		$code=str_replace(array('\{','\}'),array('&bseparator;','&eseparator;'),$code);

		# Шаг №3
		# Компилирование функций
		# Вид: {somefunc}some text{/somefunc}
		preg_match_all('|{([a-zA-Z_0-9]*)}([^{}]*){/([a-zA-Z_0-9]*)}|Uis',$code,$matches);
		foreach ($matches[1] as $k=>$func) {
			$newstr='<?php echo $this->'.$func.'(\''.str_replace("'","\'",$matches[2][$k]).'\');?>';
			$code=str_replace($matches[0][$k],$newstr,$code);
		}
		
		# Шаг №4
		# Компилирование блоков
		$this->compileBlock($code, $level, $parent);

		# Шаг №5
		# Вернём экранированные скобки
		$code=str_replace(array('&bseparator;','&eseparator;'),array('\{','\}'),$code);
		
		# Шаг №6
		# Добавление панели
		$code=preg_replace('|<([\s]*)body([^<>]*)>|Uis','\0 '."\n".'<?php $this->panel();?>',$code);
		
		# Сохраним откомпиленный шаблон
		file_put_contents($tmpPath, $code);
		# Поменяем вермя
		$tplChange=filemtime($Path);
		touch($tmpPath,$tplChange);
		
		
		chmod($tmpPath,0777);
		if (!empty($this->collideTpl)) {
			touch($tmpPath,$tplChange+1);
			file_put_contents($tmpPath.'.coll.php', '<?php'."\n".implode("\n",$this->collideTpl)."\n".'?>');
			chmod($tmpPath.'.coll.php',0777);
		}
	}
}
?>
