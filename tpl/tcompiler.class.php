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
		# Шаг №1
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
		# Шаг №2
		# Компилирование Блоков и Условий
		preg_match_all('~<!--[\s]*([a-zA-Z]*)[\s]*:[\s]*'.$parent.'([a-zA-Z0-9\._]*)[\s]*-->(((?R)|.)*)<!--[\s]*end[^>]*-->~Uis',$code,$matches);
		if ($level==0)
			$prefix = '$this->';
		else
			$prefix = '$this->blockValue['.($level -1).']';
		foreach ($matches[0] as $key=>$str) {
			$command=strtolower($matches[1][$key]);
			if ($command=='begin') {
				$var=explode(".",$matches[2][$key]);
				foreach ($var as $num=>$i) {
					if ($num!=0 || $level!=0) $var[$num]='[\''.$var[$num].'\']';
				}
				$blockname=$var[0];
				$var = implode('',$var);
				$out = '<?php if (!empty('.$prefix.$var.')) foreach('.$prefix.$var.' as $this->blockValue['.$level.']) { ?>';
				$in_code=$matches[3][$key];
				# Шаг 1
				# Компилируем вложенные блоки внутри текущего
				$this->compileBlock($in_code, $level+1, $parent.$matches[2][$key].'.');
				# Шаг 2
				# Компилируем обычные блоки внутри текущего
				$this->compileBlock($in_code);
			
				$out.=$in_code;
				$out.='<?php } ?>';
				$code=str_replace($str,$out,$code);
			} elseif($command=='if' || $command=='ifnot') {
				$else_split=preg_split('~<!--[\s]*else[^>]*-->~Uis',$matches[3][$key],2);
				if (count($else_split)>1) $matches[3][$key]=implode('<? } else { ?>',$else_split);
					
				$var=explode(".",$matches[2][$key]);
				foreach ($var as $num=>$i) {
					if ($num) $var[$num]='[\''.$var[$num].'\']';
				}
				if (!empty($parent))
					$var[0]='$this->blockValue['.($level-1).']['.$var[0].']';
				else
					$var[0]='$this->'.$var[0];
				$newstr='<?php if ('.(($command=='ifnot')?'! ':'').implode('',$var).') { ?>'.$matches[3][$key].'<?php } ?>';
				$code=str_replace($str, $newstr, $code);
			}
		}

		# Шаг 3
		# Компилируем переменные внутри блока
		preg_match_all('|{([a-zA-Z_\-0-9\.]*)}|Uis',$code,$matches);
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
		
		# Шаг 5
		# Компилирование сниппетов модулей
		preg_match_all('~<!--[\s]*module[\s]*:[\s]*([a-zA-Z0-9\.]*)[\s]*(\([a-zA-Z0-9\.,\'"]*\))*[\s]*-->(((?R)|.)*)<!--[\s]*end[^>]*-->~Uis',$code,$matches);
		//print_pre($matches);
		foreach ($matches[0] as $key=>$str) {
			$var=explode(".",substr($matches[1][$key],strlen($parent)));
			if (count($var)==1) $var[]='index';
			$moduleName=$var[0];
			unset($var[0]);
			if (is_dir(root.'/admin/modules/'.$moduleName)) {
				if (is_readable(root.'/admin/modules/'.$moduleName.'/snippets/'.implode('/',$var).'.php')) {
					$newstr=str_ireplace('{code}', $matches[3][$key], file_get_contents(root.'/admin/modules/'.$moduleName.'/snippets/'.implode('/',$var).'.php'));
					if (empty($matches[2][$key])) $matches[2][$key]='()';
					$newstr=str_ireplace('{values}',$matches[2][$key],$newstr);
				} else {
					$newstr='<div>Модуль '.$moduleName.' подключить не удалось, не существует интерфейса '.implode('.',$var).'</div>';
				}
			} else {
				global $$moduleName;
				if ($$moduleName->table==$moduleName) {
					$snippetName=engine.'/build/default/snippets/'.implode('/',$var).'.php';
					if (is_readable($snippetName)) {
						$newstr=str_ireplace('{code}', $matches[3][$key], file_get_contents($snippetName));
						if (empty($matches[2][$key])) $matches[2][$key]='()';
						$newstr=str_ireplace('{values}',$matches[2][$key],$newstr);
						$newstr=str_ireplace('{object}',$moduleName,$newstr);
					} else {
						$newstr='<div>Прототиипируемые модули не имеют данного интерфейса в текущей версиии цмс</div>';
					}
				} else {
					$newstr='<div>Модуль '.$moduleName.' не найден, разместите его в /admin/modules, либо создайте объект в /engine/_class.php</div>';
				}
			}
			$code=str_replace($str, $newstr, $code);
			//die($newstr);
		}
		
	}
	
	function getBlock(&$code,$blockname) {
		preg_match_all('~<!--[\s]*(begin|if|module)[\s]*:[\s]*'.$blockname.'([a-zA-Z0-9\.]*)[\s]*-->(((?R)|.)*)<!--[\s]*end[^>]*-->~Uis',$code,$matches);
	}
	
	function compile($Path,$tmpPath,$level=0,$parent='',$blockname='') {
		$code=file_get_contents($Path);
		if (!empty($blockname)) $code=$this->getBlock($code,$blockname);
		# Шаг №1
		# Спрячем экранированные скобки, чтобы серьёзно  облегчить себе жизнь
		$code=str_replace(array('\{','\}'),array('&bseparator;','&eseparator;'),$code);

		# Шаг №2
		# Компилирование контента
		# Вид: {content}
		$code=preg_replace('|{[\s]*content[\s]*}|Uis','<?php $this->content();?>',$code);

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
		if (!is_writable(dirname($tmpPath))) {
			if (!@chmod(dirname($tmpPath),0777)) {
				echo '<head><META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=utf-8"></head><br /><div style="background:#e6d9d9; border:1px solid #ab8a8a; color:#752f2f;">Директория для хранения кеша шаблонов "'.dirname($tmpPath).'" не имеет прав на запись!</div><br />';
			};
		}
		# PHP4 compability
		if (!function_exists('file_put_contents')) {
			function file_put_contents($filename, $data) {
				$f = @fopen($filename, 'w');
				if (!$f) {
				    return false;
				} else {
				    $bytes = fwrite($f, $data);
				    fclose($f);
				    return $bytes;
				}
			}
		}
		
		file_put_contents($tmpPath, $code);
		# Поменяем вермя
		$tplChange=filemtime($Path);
		@touch($tmpPath,$tplChange);
		
		@chmod($tmpPath,0777);
		if (!empty($this->collideTpl)) {
			@touch($tmpPath,$tplChange+1);
			@file_put_contents($tmpPath.'.coll.php', '<?php'."\n".implode("\n",$this->collideTpl)."\n".'?>');
			@chmod($tmpPath.'.coll.php',0777);
		}
	}
}
?>
