<?php

require 'includes.php';
/*
if(!IsLoggedIn()){
	Leave('signin.php');
}
*/
if(isset($_GET['id'])){
	$id = intval($_GET['id']);
}else{
	$id = null;
}

$csv = '';
$csv_array = array();

$survey = $db->get_row("SELECT * FROM " . TABLES_PREFIX . "survey WHERE id = $id ORDER BY id DESC LIMIT 0,1");
if(!$survey){
	Leave('index.php');
}

$results = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "results WHERE survey_id = $id ORDER BY id ASC");

$questions = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "question WHERE survey_id = $id ORDER BY id ASC");

$csv_array_count = 0;
$csv_array[$csv_array_count][] = str_replace(array(',','"'), "",$tmpl_strings->Get('date_taken'));
if($questions){
	foreach($questions as $question){
		$csv_array[$csv_array_count][] = strip_tags(str_replace(array(',','"'), " ",$question->question));
	}
}

if($results){
	foreach($results as $a){
		$csv_array_count++;
		$csv_array[$csv_array_count][] = date('j M Y H:i',strtotime($a->date_taken));
		
		if($questions){
			foreach($questions as $q){
				$answer = '';
				$ans = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "answers WHERE results_id = ".$a->id." AND question_id = ".$q->id." ORDER BY id ASC");
					
				if($ans){
					if($q->question_type == 'ma'){
						$ma_ans = array();
						foreach($ans as $a2){
							$ma_ans[] = $db->get_var("SELECT choice FROM " . TABLES_PREFIX . "choices WHERE id = ".$a2->choice_id);
						}
						$answer = implode(", ", $ma_ans);
					}elseif($q->question_type == 'tf'){
						if($ans[0]->answer == 'f'){
							$answer = $tmpl_strings->Get('false');
						}else{
							$answer = $tmpl_strings->Get('true');
						}
					}elseif($q->question_type == 'mp' OR $q->question_type == 'dd'){
						$answer = $db->get_var("SELECT choice FROM " . TABLES_PREFIX . "choices WHERE id = ".$ans[0]->choice_id);
					}else{
						$answer = $ans[0]->answer;	
					}
				}	
				
				$csv_array[$csv_array_count][] = str_replace(array(',','"'), "",$answer);
			}
		}
	}
}

$filename = Slug($survey->title).'_' . time() . '.csv';

if(count($csv_array) > 0){
	foreach($csv_array as $c){
		$csv .= array_to_CSV($c);
	}
}

header("content-type:application/csv;charset=UTF-8");
header("Content-Disposition: attachment; filename=$filename");
echo "\xEF\xBB\xBF";
echo $csv;
//echo mb_convert_encoding($csv , "UTF-8", "UTF-8");
exit;
