<?php

require 'includes.php';

$layout = new Layout('html/','str/');
$layout->SetContentView('survey-results');

if(isset($_GET['x'])){
	$id = intval(base64_decode($_GET['x'])) - 54321;
	$layout->AddContentById('id_query', '?x='.$_GET['x']);
}elseif(isset($_GET['id'])){
	$id = intval($_GET['id']);
	$layout->AddContentById('id_query', '?id='.$_GET['id']);
}else{
	$id = null;
}

if(ALLOW_PUBLIC_VIEW_RESULTS == false){
	Leave('take_survey.php?id='.$_GET['id']);
}

if(isset($_POST)){
	$_POST = NewCleanXSS($_POST);
}

if(isset($_GET["submitted"])){
	$layout->AddContentById('alert', $layout->GetContent('alert'));
	$layout->AddContentById('alert_nature', ' alert-success');
	$layout->AddContentById('alert_heading', '{{ST:thank_you}}!');
	$layout->AddContentById('alert_message', '{{ST:thanks_for_taking_survey}}');
	$layout->AddContentById('hide_buttons', 'style="display:none;"');
        
        
}

$layout->AddContentById('current_year', date('Y'));
$layout->AddContentById('site_url', SITE_URL);

$survey = $db->get_row("SELECT * FROM " . TABLES_PREFIX . "survey WHERE id = $id AND status = 'published' ORDER BY id DESC LIMIT 0,1");
if(1==1){
	$layout->AddContentById('id', $id);
	$layout->AddContentById('name', $survey->title);
	$layout->AddContentById('description', preg_replace('/\v+|\\\[rn]/','<br/>',$survey->description));
	
	
	
	$questions = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "question WHERE survey_id = $id ORDER BY order_by ASC, id ASC");

$arr_FCColors[] = "AFD8F8";
$arr_FCColors[] = "F6BD0F";
$arr_FCColors[] = "8BBA00";
$arr_FCColors[] = "FF8E46";
$arr_FCColors[] = "008E8E";
$arr_FCColors[] = "D64646";
$arr_FCColors[] = "8E468E";
$arr_FCColors[] = "588526";
$arr_FCColors[] = "B3AA00";
$arr_FCColors[] = "008ED6";
$arr_FCColors[] = "9D080D";
$arr_FCColors[] = "A186BE";
$arr_FCColors[] = "CC6600";
$arr_FCColors[] = "FDC689";
$arr_FCColors[] = "ABA000";
$arr_FCColors[] = "F26D7D";
$arr_FCColors[] = "FFF200";
$arr_FCColors[] = "0054A6";
$arr_FCColors[] = "F7941C";
$arr_FCColors[] = "CC3300";
$arr_FCColors[] = "006600";
$arr_FCColors[] = "663300";
$arr_FCColors[] = "6DCFF6";

	$stats_rows_html = '';
	if($questions){
		$count = 1;
		foreach($questions as $question){
			$cookie_name = 'surveyengine_'.$id.'_name_'.$question->id;
			$row_layout = new Layout('html/','str/');
			$row_layout->SetContentView('survey-results-piece');
			$row_layout->AddContentById('number', $count);
			$count++;
			
			$row_layout->AddContentById('question', $question->question);
			
			
			if($question->question_type == 'st'){
				$row_layout->AddContentById('results', '<p>{{ST:text_question}}</p>');
			}elseif($question->question_type == 'lt'){
				$row_layout->AddContentById('results', '<p>{{ST:text_question}}</p>');
			}elseif($question->question_type == 'dt'){
                $row_layout->AddContentById('results', '<p>{{ST:text_question}}</p>');
            }elseif($question->question_type == 'em'){
                $row_layout->AddContentById('results', '<p>{{ST:text_question}}</p>');
            }
			
			if($question->question_type == 'nc' OR  
			   $question->question_type == 'nd' OR 
			   $question->question_type == 'tf' OR  
			   $question->question_type == 'mp' OR
               $question->question_type == 'dd' OR
               $question->question_type == 'ma'){
				$q_stats = array();
				$stats_total = 0;
				$stats_results_html = '';
				if(!$question->stats){
					if($question->question_type == 'tf'){
						$q_stats = array('t'=>0,'f'=>0);
					}elseif($question->question_type == 'dd' OR $question->question_type == 'mp' OR $question->question_type == 'ma'){
						$choices = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "choices WHERE question_id = ".$question->id." ORDER BY id ASC");
						if(is_array($choices)){
						foreach($choices as $c){
							$q_stats[$c->id] = 0;
						}
						}
						if($question->question_type == 'ma'){
							$q_stats['_total_selects'] = 0;
						}
					}elseif($question->question_type == 'nc' OR $question->question_type == 'nd'){
						$q_stats = array('total'=>0,'n'=>0,'max'=>0,'min'=>9999999999);
					}
				}else{
					$q_stats = unserialize($question->stats);
				}
				
				if($question->question_type == 'tf'){
                    $stats_total = $db->get_var("SELECT COUNT(Distinct results_id) FROM " . TABLES_PREFIX . "answers WHERE question_id = ".$question->id." ");
                    $fake_total = 0;
                    $q_stats['t'] = $db->get_var("SELECT COUNT(DISTINCT results_id) FROM " . TABLES_PREFIX . "answers WHERE answer = 't' AND question_id = ".$question->id." ");
                    $q_stats['f'] = $db->get_var("SELECT COUNT(DISTINCT results_id) FROM " . TABLES_PREFIX . "answers WHERE answer = 'f' AND question_id = ".$question->id." ");

                    //$stats_total = intval($q_stats['t']) + intval($q_stats['f']);
                    if($stats_total == 0){
                        $fake_total = 1;
                    }else{
                        $fake_total = $stats_total;
                    }
                    $true_perc = 0;
                    $false_perc = 0;
                    if($stats_total > 0){
                        $true_perc = round((intval($q_stats['t'])/$fake_total) * 100);
                        $false_perc = round((intval($q_stats['f'])/$fake_total) * 100);
                    }
                    $stats_results_html = '<p><b>'.intval($q_stats['t']).'/'.$stats_total.' ('.$true_perc.'%)</b> - {{ST:true}}</p><p><b>'.intval($q_stats['f']).'/'.$stats_total.' ('.$false_perc.'%)</b> - {{ST:false}}</p>';

                    $chart_layout = new Layout('html/','str/');
						$chart_layout->SetContentView('survey-results-charts');
						$chart_layout->AddContentById('id', $question->id);
						$chart_layout->AddContentById('type', 'FCF_Pie2D');
						$chart_layout->AddContentById('width', '300');
						$chart_layout->AddContentById('height', '300');
						$chart_data = '<graph showNames="1"  decimalPrecision="0"><set name="{{ST:true}}" value="'.intval($q_stats['t']).'"/><set name="{{ST:false}}" value="'.intval($q_stats['f']).'"/></graph>';
						$chart_layout->AddContentById('data', str_replace("'","",$chart_data));
						$row_layout->AddContentById('charts', $chart_layout->ReturnView());
				
				}elseif($question->question_type == 'nc' OR $question->question_type == 'nd'){
                    $question_stats = $db->get_row("SELECT AVG(answer) as Mean, MAX(answer) as Maximum, MIN(answer) as Minimum, SUM(answer) as Total,  VARIANCE(answer) as Var, STDDEV(answer) as StandardDeviation FROM " . TABLES_PREFIX . "answers WHERE question_id = ".$question->id." ");

                    $stats_results_html = '<p><b>'.$question_stats->Mean.'</b> - {{ST:average}}</p>';
                    $stats_results_html .= '<p><b>'.$question_stats->Maximum.'</b> - {{ST:max}}</p>';
                    $stats_results_html .= '<p><b>'.$question_stats->Minimum.'</b> - {{ST:min}}</p>';
                    $stats_results_html .= '<p><b>'.$question_stats->Total.'</b> - {{ST:total}}</p>';
                    $stats_results_html .= '<p><b>'.$question_stats->Var.'</b> - {{ST:variance}}</p>';
                    $stats_results_html .= '<p><b>'.$question_stats->StandardDeviation.'</b> - {{ST:standard_deviation}}</p>';

                    /*
                    if($q_stats['n'] > 0){
						$stats_results_html = '<p><b>'.round(($q_stats['total']/$q_stats['n']),2).'</b> - {{ST:average}}</p>';
					}else{
						$stats_results_html = '<p><b>0</b> - {{ST:average}}</p>';
					}
					$stats_results_html .= '<p><b>'.$q_stats['max'].'</b> - {{ST:max}}</p>';
					if($q_stats['min'] != 9999999999){
						$stats_results_html .= '<p><b>'.$q_stats['min'].'</b> - {{ST:min}}</p>';
					}
                    */
				}elseif($question->question_type == 'dd' OR $question->question_type == 'mp' OR $question->question_type == 'ma'){
                    $stats_total = $db->get_var("SELECT COUNT(Distinct results_id) FROM " . TABLES_PREFIX . "answers WHERE question_id = ".$question->id." ");
                    $fake_total = 0;

                    /*
					if($question->question_type == 'ma'){
						$stats_total = intval($q_stats['_total_selects']);
					}else{
						if(count($q_stats) > 0){
							foreach($q_stats as $k=>$v){
								$stats_total = $stats_total + intval($v);
							}
						}
					}
                    */

                    if($stats_total == 0){
                        $fake_total = 1;
                    }else{
                        $fake_total = $stats_total;
                    }
                    $choices = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "choices WHERE question_id = ".$question->id." ORDER BY id ASC");
                    if(is_array($choices)){
                        foreach($choices as $c){
                            $q_stats[$c->id] = $db->get_var("SELECT COUNT(DISTINCT results_id) FROM " . TABLES_PREFIX . "answers WHERE choice_id = ".$c->id." AND question_id = ".$question->id." ");
                            $stats_results_html .= '<p><b>'.intval($q_stats[$c->id]).'/'.$stats_total.' ('.round((intval($q_stats[$c->id])/$fake_total) * 100).'%)</b> - '.$c->choice.'</p>';
                        }
                    }
					
						$chart_layout = new Layout('html/','str/');
						$chart_layout->SetContentView('survey-results-charts');
						$chart_layout->AddContentById('id', $question->id);
						if($question->question_type == 'mp' OR $question->question_type == 'dd'){
							$chart_layout->AddContentById('width', '300');
							$chart_layout->AddContentById('height', '300');
							$chart_layout->AddContentById('type', 'FCF_Pie2D');
							$chart_data = '<graph showNames="1"  decimalPrecision="0">';
							foreach($choices as $c){
								$chart_data .= '<set name="'.$c->choice.'" value="'.intval($q_stats[$c->id]).'"/>';
							}
							$chart_data .= '</graph>';
						}else{
							$chart_layout->AddContentById('width', '600');
							$chart_layout->AddContentById('height', '300');
							$chart_layout->AddContentById('type', 'FCF_Column2D');
							$chart_data = '<graph showNames="1"  decimalPrecision="0">';
							if(is_array($choices)){
								foreach($choices as $c){
									$chart_data .= '<set name="'.$c->choice.'" value="'.intval($q_stats[$c->id]).'"/>';
								}
							}
							$chart_data .= '</graph>';
						}
						$chart_layout->AddContentById('data', str_replace("'","",$chart_data));
						$row_layout->AddContentById('charts', $chart_layout->ReturnView());
					
					
				}
				$row_layout->AddContentById('results', $stats_results_html);
			}
				
			
			
			
			$stats_rows_html .= $row_layout->ReturnView();
		}
	}else{
		$stats_rows_html = '<p>{{ST:no_items}}</p>';
	}
	
	
	$layout->AddContentById('questions', $stats_rows_html);
}else{
	$layout->AddContentById('hide_buttons', 'style="display:none;"');
}


$layout->RenderViewAndExit();
