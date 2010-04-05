# Another one php template engine (my old stuff)
*(note: ./tpl/tmp should be writable)*

## Syntax:
### Variables
	== Logic ==							|	== Template ==
	$tpl->var_name='...';				|	{var_name}
	$tpl->var_name['sub_var']='...';	|	{var_name.sub_var}
	
### Blocks
	== Logic ==							|	== Template ==
	$tpl->block_name[]['num']='4';		|	<!--begin:block_name-->
	$tpl->block_name[]['num']='8';		|	{block_name.num}
	$tpl->block_name[]['num']='15';		|	<!--end:block_name-->
										|
	$tpl->words['block']=array(			|	<!--begin:words.block-->
    	O=>array('word'=>'A'),			|	{words.block.word}
    	1=>array('word'=>'B'),			|	<!--end:words.block-->
    	2=>array('word'=>'C'),			|
	);									|
	
### Example
	== Logic ==											|	== Template ==
	for ($i=1; $i<10; $i++)								|	<table>
		 for ($j=1; $j<10; $j++)						|		<!--begin:table-->
			$tpl->table[$i]['row'][$j]['num']=$i*$j;	|		<tr>
														|			<!--begin:table.row-->
														|				<td>{table.row.num}</td>
														|			<!--end:table.row-->
														|		</tr>
														|		<!--end:table-->
														|	</table>
														
### Checks
	== Logic ==							|	== Template ==
	$tpl->f_text=true;					|	<!--if:f_text-->
										|	Hello world
										|	<!--end:f_text-->

### Functions
	== Logic ==							|	== Template ==
	function up($text) {				|	{up}text{/up}
		return strtoupper($text);		|
	}									|
	
	
