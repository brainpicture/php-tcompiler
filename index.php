<?php
require_once 'tpl/tpl.class.php';

$tpl=new tpl('./','tpl/tmp');
$tpl->tpl='My';
$tpl->num=4815162342;
$tpl->post['page']['id']=316;
for ($i=1; $i<30; $i++) $tpl->bin[]=array('dec'=>$i, 'bin'=>decbin($i));
for ($i=1; $i<10; $i++) for ($j=1; $j<10; $j++) $tpl->table[$i]['row'][$j]['num']=$i*$j;
$tpl->incfile='sm';
$tpl->render('template.html');
?>
